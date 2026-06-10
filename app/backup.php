<?php
declare(strict_types=1);

/** Tables exportées dans l'ordre (respect des clés étrangères à la restauration). */
const BACKUP_TABLES = [
    'settings',
    'users',
    'electors',
    'enrollments',
    'attachments',
    'cards',
    'field_agents',
    'agent_cards',
    'candidates',
    'votes',
];

function backup_dir(array $config): string
{
    $dir = dirname($config['app']['upload_dir']) . DIRECTORY_SEPARATOR . 'backups';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function backup_database_xml(PDO $pdo, array $config, string $reason): ?string
{
    try {
        $dir = backup_dir($config);
        $safeReason = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $reason) ?: 'crud';
        $filename = 'backup_' . date('Ymd_His') . '_' . $safeReason . '.xml';
        $filepath = $dir . DIRECTORY_SEPARATOR . $filename;

        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        $root = $xml->createElement('daveelection_backup');
        $root->setAttribute('generated_at', date('c'));
        $root->setAttribute('reason', $reason);
        $xml->appendChild($root);

        foreach (BACKUP_TABLES as $table) {
            $tableNode = $xml->createElement('table');
            $tableNode->setAttribute('name', $table);
            $root->appendChild($tableNode);

            try {
                $st = $pdo->query('SELECT * FROM `' . $table . '`');
                $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {
                continue;
            }

            foreach ($rows as $row) {
                $rowNode = $xml->createElement('row');
                foreach ($row as $col => $val) {
                    $colNode = $xml->createElement('column');
                    $colNode->setAttribute('name', (string)$col);
                    if ($val === null) {
                        $colNode->setAttribute('null', 'true');
                    } else {
                        $colNode->appendChild($xml->createTextNode((string)$val));
                    }
                    $rowNode->appendChild($colNode);
                }
                $tableNode->appendChild($rowNode);
            }
        }

        $xml->save($filepath);
        return $filename;
    } catch (Throwable $e) {
        return null;
    }
}

function list_backup_files(array $config): array
{
    $dir = backup_dir($config);
    $files = glob($dir . DIRECTORY_SEPARATOR . 'backup_*.xml') ?: [];
    rsort($files);
    return $files;
}

function restore_database_xml(PDO $pdo, string $filepath): void
{
    if (!is_file($filepath)) {
        throw new RuntimeException('Fichier de sauvegarde introuvable.');
    }

    $dom = new DOMDocument();
    if (!$dom->load($filepath)) {
        throw new RuntimeException('XML invalide.');
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

    $reverseTables = array_reverse(BACKUP_TABLES);
    foreach ($reverseTables as $table) {
        try {
            $pdo->exec('DELETE FROM `' . $table . '`');
        } catch (Throwable $e) {
            // table absente
        }
    }

    $xpath = new DOMXPath($dom);
    foreach (BACKUP_TABLES as $table) {
        $tableNodes = $xpath->query("//table[@name='" . $table . "']/row");
        if (!$tableNodes || $tableNodes->length === 0) {
            continue;
        }

        foreach ($tableNodes as $rowNode) {
            $cols = [];
            $vals = [];
            foreach ($rowNode->childNodes as $colNode) {
                if ($colNode->nodeName !== 'column') {
                    continue;
                }
                /** @var DOMElement $colNode */
                $name = $colNode->getAttribute('name');
                $cols[] = '`' . $name . '`';
                if ($colNode->getAttribute('null') === 'true') {
                    $vals[] = null;
                } else {
                    $vals[] = $colNode->textContent;
                }
            }
            if (!$cols) {
                continue;
            }
            $placeholders = implode(',', array_fill(0, count($cols), '?'));
            $sql = 'INSERT INTO `' . $table . '` (' . implode(',', $cols) . ') VALUES (' . $placeholders . ')';
            $st = $pdo->prepare($sql);
            $st->execute($vals);
        }
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
}
