

<?php
// config/config.php


return [
    'db' => [
        'host' => '127.0.0.1',
        'name' => 'TestElecteur',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'base_url' => '/TestElecteur/public', // chemin public dans WAMP
        'upload_dir' => __DIR__ . '/../storage/uploads',
    ],
];


