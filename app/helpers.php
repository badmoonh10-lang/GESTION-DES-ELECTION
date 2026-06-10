<?php
declare(strict_types=1);

function base_url(array $config, string $path = ''): string
{
    $base = rtrim($config['app']['base_url'], '/');
    $path = '/' . ltrim($path, '/');
    return $base . ($path === '/' ? '' : $path);
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function e(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

function flash_set(string $key, string $message, string $type = 'info'): void
{
    $_SESSION['_flash'][$key] = ['message' => $message, 'type' => $type];
}

function flash_get(string $key): ?array
{
    if (!isset($_SESSION['_flash'][$key])) {
        return null;
    }
    $v = $_SESSION['_flash'][$key];
    unset($_SESSION['_flash'][$key]);
    return $v;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['_csrf'];
}

function csrf_verify(?string $token): bool
{
    return isset($_SESSION['_csrf']) && is_string($token) && hash_equals($_SESSION['_csrf'], $token);
}

function require_post_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Method Not Allowed');
    }
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        http_response_code(403);
        exit('CSRF invalid');
    }
}

function setting(PDO $pdo, string $key, ?string $default = null): ?string
{
    $st = $pdo->prepare('SELECT key_value FROM settings WHERE key_name = ?');
    $st->execute([$key]);
    $row = $st->fetch();
    return $row ? (string)$row['key_value'] : $default;
}

function setting_set(PDO $pdo, string $key, string $value): void
{
    $st = $pdo->prepare('INSERT INTO settings(key_name, key_value) VALUES(?, ?) ON DUPLICATE KEY UPDATE key_value=VALUES(key_value)');
    $st->execute([$key, $value]);
}

function gen_dossier_code(PDO $pdo): string
{
    // Format: DOS-000001
    $st = $pdo->query("SELECT MAX(id) AS max_id FROM enrollments");
    $max = (int)($st->fetch()['max_id'] ?? 0);
    $n = $max + 1;
    return 'DOS-' . str_pad((string)$n, 6, '0', STR_PAD_LEFT);
}

function gen_agent_matricule(PDO $pdo): string
{
    $st = $pdo->query('SELECT MAX(id) AS max_id FROM field_agents');
    $max = (int)($st->fetch()['max_id'] ?? 0);
    $n = $max + 1;
    return 'AGT-' . str_pad((string)$n, 6, '0', STR_PAD_LEFT);
}

function list_is_closed(PDO $pdo): bool
{
    return setting($pdo, 'list_closed', '0') === '1';
}

function voting_is_open(PDO $pdo): bool
{
    return setting($pdo, 'voting_open', '0') === '1';
}

/** Élections en cours : liste arrêtée + vote ouvert (inscriptions et pré-enrôlements fermés). */
function elections_en_cours(PDO $pdo): bool
{
    return list_is_closed($pdo) && voting_is_open($pdo);
}

function media_url(array $config, ?string $relPath): ?string
{
    if ($relPath === null || $relPath === '') {
        return null;
    }
    return base_url($config, 'media.php?f=' . urlencode(ltrim($relPath, '/')));
}

function resolve_storage_path(string $relativePath, string $publicRoot): ?string
{
    $rel = ltrim($relativePath, '/');
    $candidates = [
        $publicRoot . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . $rel,
        $publicRoot . DIRECTORY_SEPARATOR . $rel,
    ];
    foreach ($candidates as $candidate) {
        $resolved = realpath($candidate);
        if ($resolved !== false && is_file($resolved)) {
            return $resolved;
        }
    }
    return null;
}
/**
 * @return array{path: string, rel: string}|null
 */
function save_uploaded_image(array $config, string $prefix, ?array $file): ?array
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Upload invalide.');
    }
    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
        throw new RuntimeException('Formats autorisés : JPG, PNG.');
    }
    $safeName = $prefix . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $target = rtrim($config['app']['upload_dir'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $safeName;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('Impossible d\'enregistrer le fichier.');
    }
    return ['path' => $target, 'rel' => 'storage/uploads/' . $safeName];
}

function trigger_backup(PDO $pdo, array $config, string $reason): void
{
    require_once __DIR__ . '/backup.php';
    backup_database_xml($pdo, $config, $reason);
}

/** Statistiques du déroulement des élections en temps réel. */
function election_live_stats(PDO $pdo): array
{
    $st = $pdo->query(
        "SELECT COUNT(DISTINCT e.id) AS total
         FROM electors e
         JOIN enrollments en ON en.elector_id = e.id
         WHERE en.status = 'APPROVED'"
    );
    $totalElectors = (int)($st->fetch()['total'] ?? 0);

    $st = $pdo->query('SELECT COUNT(DISTINCT elector_id) AS total FROM votes');
    $totalVoters = (int)($st->fetch()['total'] ?? 0);

    $st = $pdo->query(
        "SELECT c.id, c.party_name, e.nom, e.prenom, COUNT(v.id) AS votes_count
         FROM candidates c
         JOIN electors e ON e.id = c.elector_id
         LEFT JOIN votes v ON v.candidate_id = c.id
         GROUP BY c.id, c.party_name, e.nom, e.prenom
         ORDER BY votes_count DESC"
    );
    $candidates = $st->fetchAll();

    $st = $pdo->query(
        "SELECT vote_type, COUNT(*) AS cnt FROM votes GROUP BY vote_type"
    );
    $byChannel = ['ONLINE' => 0, 'PHYSICAL' => 0];
    foreach ($st->fetchAll() as $r) {
        $byChannel[(string)$r['vote_type']] = (int)$r['cnt'];
    }

    $participation = $totalElectors > 0 ? round(($totalVoters / $totalElectors) * 100, 1) : 0.0;

    return [
        'total_electors' => $totalElectors,
        'total_voters' => $totalVoters,
        'abstentions' => max(0, $totalElectors - $totalVoters),
        'participation_rate' => $participation,
        'candidates' => $candidates,
        'online_votes' => $byChannel['ONLINE'],
        'physical_votes' => $byChannel['PHYSICAL'],
    ];
}


