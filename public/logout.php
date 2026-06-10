<?php
require_once __DIR__ . '/../app/bootstrap.php';

logout();
flash_set('main', 'Déconnecté.', 'info');
redirect(base_url($config, 'index.php'));


