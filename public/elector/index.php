<?php
require_once __DIR__ . '/../../app/bootstrap.php';

$user = require_role($pdo, $config, 'ELECTOR');
$title = 'Dashboard Électeur';

$votingOpen = voting_is_open($pdo);
$electionsLive = elections_en_cours($pdo);

$st = $pdo->prepare(
    'SELECT e.id AS elector_id, e.nom, e.prenom, e.genre, e.age, e.cni,
            en.id AS enrollment_id, en.dossier_code, en.status, en.direction_comment
     FROM electors e
     LEFT JOIN enrollments en ON en.elector_id = e.id
     WHERE e.user_id = ?
     ORDER BY en.id DESC
     LIMIT 1'
);
$st->execute([(int)$user['id']]);
$row = $st->fetch();

require __DIR__ . '/../_layout_top.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Dashboard Électeur</h3>
  <div class="d-flex flex-wrap gap-2">
    <a class="btn btn-outline-secondary" href="<?= e(base_url($config, 'track.php')) ?>">Suivi dossier</a>
    <a class="btn btn-outline-primary" href="<?= e(base_url($config, 'candidate/index.php')) ?>">Espace candidat</a>
  </div>
</div>

<?php if ($votingOpen && $row && $row['status'] === 'APPROVED'): ?>
  <div class="alert alert-success border-success mb-4">
    <div class="d-flex align-items-center gap-2">
      <strong class="fs-5">Les élections sont ouvertes !</strong>
      <span class="badge text-bg-success">VOTE EN COURS</span>
    </div>
    <p class="mb-2 mt-2">Vous pouvez participer au vote en ligne dès maintenant.</p>
    <a class="btn btn-success" href="<?= e(base_url($config, 'elector/vote.php')) ?>">Accéder au vote</a>
  </div>
<?php endif; ?>

<?php if ($electionsLive): ?>
  <?php require __DIR__ . '/../_partials/election_live.php'; ?>
<?php endif; ?>

<?php if (!$row): ?>
  <div class="alert alert-warning">Aucun profil électeur trouvé.</div>
<?php else: ?>
  <div class="row g-4">
    <div class="col-lg-5">
      <div class="card card-soft">
        <div class="card-body p-4">
          <h5 class="mb-3">Mes informations</h5>
          <div><strong>Nom:</strong> <?= e($row['nom']) ?></div>
          <div><strong>Prénom:</strong> <?= e($row['prenom']) ?></div>
          <div><strong>Genre:</strong> <?= e($row['genre']) ?></div>
          <div><strong>Âge:</strong> <?= e((string)$row['age']) ?></div>
          <div><strong>CNI:</strong> <?= e($row['cni']) ?></div>
        </div>
      </div>
    </div>

    <div class="col-lg-7">
      <div class="card card-soft">
        <div class="card-body p-4">
          <h5 class="mb-2">Ma demande</h5>
          <?php if (empty($row['dossier_code'])): ?>
            <div class="alert alert-warning mb-0">Aucune demande trouvée.</div>
          <?php else: ?>
            <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
              <span class="badge text-bg-primary">Dossier: <?= e($row['dossier_code']) ?></span>
              <span class="badge text-bg-secondary">Statut: <?= e($row['status']) ?></span>
            </div>

            <?php if (!empty($row['direction_comment'])): ?>
              <div class="alert alert-info"><?= e($row['direction_comment']) ?></div>
            <?php endif; ?>

            <div class="d-flex flex-wrap gap-2">
              <a class="btn btn-outline-primary" href="<?= e(base_url($config, 'elector/upload.php')) ?>">Fournir pièces jointes</a>
              <a class="btn btn-success" href="<?= e(base_url($config, 'elector/submit.php')) ?>">Soumettre la demande</a>
              <?php if ($row['status'] === 'APPROVED'): ?>
                <a class="btn btn-dark" href="<?= e(base_url($config, 'card.php?dossier=' . urlencode($row['dossier_code'])) ) ?>">Télécharger la carte (PDF)</a>
                <?php if ($votingOpen): ?>
                  <a class="btn btn-outline-success" href="<?= e(base_url($config, 'elector/vote.php')) ?>">Accéder au vote</a>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../_layout_bottom.php'; ?>
