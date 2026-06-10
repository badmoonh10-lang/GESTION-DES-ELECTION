<?php
require_once __DIR__ . '/../../app/bootstrap.php';

$user = require_role($pdo, $config, 'AGENT');
$title = 'Dashboard Agent';

$electionsLive = elections_en_cours($pdo);
$listClosed = list_is_closed($pdo);

require __DIR__ . '/../_layout_top.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Dashboard Agent de terrain</h3>
  <?php if ($electionsLive): ?>
    <span class="badge text-bg-success">Élections en cours</span>
  <?php endif; ?>
</div>

<?php if ($electionsLive): ?>
  <?php require __DIR__ . '/../_partials/election_live.php'; ?>
<?php endif; ?>

<div class="row g-4">
  <?php if (!$listClosed): ?>
  <div class="col-md-6">
    <div class="card card-soft">
      <div class="card-body p-4">
        <h5>Enregistrer un électeur</h5>
        <p class="text-muted">Créer un dossier en pré‑enrôlement (photo et compte en ligne optionnels).</p>
        <a class="btn btn-primary" href="<?= e(base_url($config, 'agent/new.php')) ?>">Nouveau pré‑enrôlement</a>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card card-soft">
      <div class="card-body p-4">
        <h5>Mes dossiers</h5>
        <p class="text-muted">Consulter, soumettre à la Direction, générer une carte.</p>
        <a class="btn btn-outline-primary" href="<?= e(base_url($config, 'agent/list.php')) ?>">Voir la liste</a>
      </div>
    </div>
  </div>
  <?php endif; ?>
  <?php if ($electionsLive): ?>
  <div class="col-md-6">
    <div class="card card-soft border-success">
      <div class="card-body p-4">
        <h5 class="text-success">Vote physique</h5>
        <p class="text-muted">Cocher et synchroniser le vote d'un électeur au bureau de vote.</p>
        <a class="btn btn-success" href="<?= e(base_url($config, 'agent/vote_sync.php')) ?>">Synchroniser les votes</a>
      </div>
    </div>
  </div>
  <?php endif; ?>
  <?php if ($listClosed): ?>
  <div class="col-md-6">
    <div class="card card-soft">
      <div class="card-body p-4">
        <h5>Ma carte d'agent</h5>
        <p class="text-muted">Télécharger votre carte officielle d'agent de terrain.</p>
        <?php
        $st = $pdo->prepare('SELECT matricule FROM field_agents WHERE user_id = ?');
        $st->execute([(int)$user['id']]);
        $fa = $st->fetch();
        ?>
        <?php if ($fa): ?>
          <a class="btn btn-dark" href="<?= e(base_url($config, 'agent_card.php?matricule=' . urlencode($fa['matricule']))) ?>">Carte PDF</a>
        <?php else: ?>
          <span class="text-muted small">Profil agent non configuré par la Direction.</span>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../_layout_bottom.php'; ?>
