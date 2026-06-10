<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/backup.php';

$user = require_role($pdo, $config, 'DIRECTION');

$file = basename((string)($_GET['file'] ?? ''));
if ($file === '' || !preg_match('/^backup_[\w\-]+\.xml$/', $file)) {
    http_response_code(400);
    exit('Fichier invalide.');
}

$filepath = backup_dir($config) . DIRECTORY_SEPARATOR . $file;
if (!is_file($filepath)) {
    http_response_code(404);
    exit('Fichier introuvable.');
}

header('Content-Type: application/xml; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $file . '"');
readfile($filepath);
