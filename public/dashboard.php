<?php
require_once __DIR__ . '/../app/bootstrap.php';

require_login($config);
$user = current_user($pdo);

if (!$user) {
    redirect(base_url($config, 'login.php'));
}

switch ($user['role']) {
    case 'DIRECTION':
        redirect(base_url($config, 'direction/index.php'));
    case 'AGENT':
        redirect(base_url($config, 'agent/index.php'));
    case 'ELECTOR':
        redirect(base_url($config, 'elector/index.php'));
    default:
        http_response_code(403);
        exit('Rôle inconnu');
}


