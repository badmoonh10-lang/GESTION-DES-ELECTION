<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$config = require $root . '/config/config.php';
require_once $root . '/app/db.php';

try {
    $pdo = db($config);
    $users = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    echo "DB OK - {$users} utilisateur(s)\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'DB ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}

$autoload = $root . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "FPDF: autoload manquant\n");
    exit(1);
}
require_once $autoload;
if (!class_exists('FPDF')) {
    fwrite(STDERR, "FPDF: classe introuvable\n");
    exit(1);
}
echo "FPDF OK\n";

foreach (['storage/uploads', 'storage/backups', 'storage/qr'] as $dir) {
    $path = $root . '/' . $dir;
    if (!is_dir($path)) {
        mkdir($path, 0775, true);
        echo "CREATED {$dir}/\n";
    } elseif (!is_writable($path)) {
        fwrite(STDERR, "WARN: {$dir}/ non inscriptible\n");
    }
}

echo "SMOKE TEST PASSED\n";
