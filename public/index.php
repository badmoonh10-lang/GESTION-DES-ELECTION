<?php
require_once __DIR__ . '/../app/bootstrap.php';

$user = current_user($pdo);
$title = 'Accueil';

require __DIR__ . '/_layout_top.php';
?>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="card card-soft">
      <div class="card-body p-4">
        <h3 class="mb-2">Système d’enrôlement des électeurs</h3>
        <p class="text-muted mb-4">
         <b>Bienvenue dans le systeme automatiser des elections .</b>
<br>
Veuillez choisir une action en fonction des rôles sur la petite carte sur le côte
        </p>

        <div class="d-flex flex-wrap gap-2">
          <a class="btn btn-primary" href="<?= e(base_url($config, 'register.php')) ?>">Créer un compte Électeur</a>
          <a class="btn btn-outline-primary" href="<?= e(base_url($config, 'login.php')) ?>">Connexion</a>
          <a class="btn btn-outline-secondary" href="<?= e(base_url($config, 'track.php')) ?>">Suivre une demande</a>
          <a class="btn btn-dark" href="<?= e(base_url($config, 'download.php')) ?>">Télécharger carte</a>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card card-soft">
      <div class="card-body p-4">
        <h5 class="mb-3">Rôles</h5>
        <ul class="mb-0">
          <li><strong>Direction</strong></li>
          <li><strong>Agent</strong></li>
          <li><strong>Électeur</strong></li>
        </ul>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>


