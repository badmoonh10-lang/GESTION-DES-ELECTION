<?php
/**
 * Serveur de fichiers media (photos de profil, assets).
 *
 * Ce fichier NE charge PAS bootstrap.php/session/DB car il est appelé
 * en parallèle par le navigateur pour chaque image d'une page.
 * Charger la session ici bloquerait toutes les images simultanément
 * (PHP file-based session locking).
 */

$path = trim((string)($_GET['f'] ?? ''));

// Sécurité : refuser les chemins vides ou avec traversal
if ($path === '' || str_contains($path, '..') || str_contains($path, "\0")) {
    http_response_code(400);
    exit('Fichier invalide.');
}

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    http_response_code(500);
    exit('Erreur serveur.');
}

$full = realpath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
if ($full === false || !is_file($full)) {
    http_response_code(404);
    exit('Fichier introuvable.');
}

// Seuls ces deux répertoires sont autorisés
$allowedDirs = [
    realpath($root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads'),
    realpath($root . DIRECTORY_SEPARATOR . 'public'  . DIRECTORY_SEPARATOR . 'assets'),
];

$ok = false;
foreach ($allowedDirs as $dir) {
    if ($dir !== false && str_starts_with($full, $dir . DIRECTORY_SEPARATOR)) {
        $ok = true;
        break;
    }
}
if (!$ok) {
    http_response_code(403);
    exit('Accès refusé.');
}

$ext   = strtolower(pathinfo($full, PATHINFO_EXTENSION));
$types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];

// Cache navigateur 7 jours (les photos ne changent pas souvent)
header('Content-Type: '   . ($types[$ext] ?? 'application/octet-stream'));
header('Content-Length: ' . (string)filesize($full));
header('Cache-Control: private, max-age=604800');
readfile($full);
