<?php
declare(strict_types=1);

function current_user(PDO $pdo): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $st = $pdo->prepare('SELECT id, username, role FROM users WHERE id = ?');
    $st->execute([$_SESSION['user_id']]);
    $u = $st->fetch();
    return $u ?: null;
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function require_login(array $config): void
{
    if (!is_logged_in()) {
        redirect(base_url($config, 'login.php'));
    }
}

function require_role(PDO $pdo, array $config, string $role): array
{
    require_login($config);
    $u = current_user($pdo);
    if (!$u || $u['role'] !== $role) {
        http_response_code(403);
        exit('Accès refusé');
    }
    return $u;
}

function login(PDO $pdo, string $username, string $password): bool
{
    $st = $pdo->prepare('SELECT id, password_hash FROM users WHERE username = ?');
    $st->execute([$username]);
    $u = $st->fetch();
    if (!$u) {
        return false;
    }
    if (!password_verify($password, $u['password_hash'])) {
        return false;
    }
    $_SESSION['user_id'] = (int)$u['id'];
    return true;
}

function logout(): void
{
    unset($_SESSION['user_id']);
}


