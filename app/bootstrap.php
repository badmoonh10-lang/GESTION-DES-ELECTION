<?php
declare(strict_types=1);

session_start();

$config = require __DIR__ . '/../config/config.php';

if (!is_dir($config['app']['upload_dir'])) {
    @mkdir($config['app']['upload_dir'], 0775, true);
}

$backupDir = dirname($config['app']['upload_dir']) . DIRECTORY_SEPARATOR . 'backups';
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0775, true);
}

$qrDir = dirname($config['app']['upload_dir']) . DIRECTORY_SEPARATOR . 'qr';
if (!is_dir($qrDir)) {
    @mkdir($qrDir, 0775, true);
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';

$pdo = db($config);


