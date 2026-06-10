<?php
/** @var array $config */
/** @var ?array $user */
?>

<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
  <title><?= e($title ?? 'DaveElection') ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../public/assets/css/bootstrap.min.css">
  <link href="<?= e(base_url($config, 'assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container">
    <a class="navbar-brand" href="<?= e(base_url($config, 'index.php')) ?>">DaveElection</a>
    <div class="ms-auto d-flex gap-2">
      <?php if (!empty($user)): ?>
        <span class="navbar-text text-white-50">
          <?= e($user['username']) ?> (<?= e($user['role']) ?>)
        </span>
        <a class="btn btn-outline-light btn-sm" href="<?= e(base_url($config, 'dashboard.php')) ?>">Dashboard</a>
        <a class="btn btn-warning btn-sm" href="<?= e(base_url($config, 'logout.php')) ?>">Déconnexion</a>
      <?php else: ?>
        <a class="btn btn-outline-light btn-sm" href="<?= e(base_url($config, 'login.php')) ?>">Connexion</a>
        <a class="btn btn-success btn-sm" href="<?= e(base_url($config, 'register.php')) ?>">Inscription (Électeur)</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<main class="container py-4">
  <?php if ($f = flash_get('main')): ?>
    <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
  <?php endif; ?>


