<?php
require_once __DIR__ . '/../../app/bootstrap.php';

$user = require_role($pdo, $config, 'DIRECTION');
$title = 'Dashboard Direction';

$listClosed = list_is_closed($pdo);
$votingOpen = voting_is_open($pdo);
$electionsLive = elections_en_cours($pdo);

require __DIR__ . '/../_layout_top.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Dashboard Direction</h3>
  <div class="d-flex flex-wrap gap-2">
    <span class="badge <?= $listClosed ? 'text-bg-danger' : 'text-bg-success' ?>">
      Liste: <?= $listClosed ? 'ARRÊTÉE' : 'OUVERTE' ?>
    </span>
    <?php if ($votingOpen): ?>
      <span class="badge text-bg-success">Vote: OUVERT</span>
    <?php endif; ?>
  </div>
</div>

<?php if ($electionsLive): ?>
  <?php require __DIR__ . '/../_partials/election_live.php'; ?>
<?php endif; ?>

<div class="row g-4">
  <div class="col-md-6">
    <div class="card card-soft">
      <div class="card-body p-4">
        <h5>Demandes à traiter</h5>
        <p class="text-muted">Valider / rejeter et générer le modèle de carte.</p>
        <a class="btn btn-primary" href="<?= e(base_url($config, 'direction/requests.php')) ?>">Voir les demandes</a>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card card-soft">
      <div class="card-body p-4">
        <h5>Liste électorale officielle</h5>
        <p class="text-muted">Afficher les électeurs approuvés (mise à jour auto).</p>
        <a class="btn btn-outline-primary" href="<?= e(base_url($config, 'direction/official.php')) ?>">Afficher</a>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card card-soft">
      <div class="card-body p-4">
        <h5>Agents de terrain</h5>
        <p class="text-muted">Créer, modifier et supprimer les agents de terrain.</p>
        <a class="btn btn-outline-primary" href="<?= e(base_url($config, 'direction/agents.php')) ?>">Gérer les agents</a>
      </div>
    </div>
  </div>
  <?php if ($listClosed): ?>
  <div class="col-md-6">
    <div class="card card-soft">
      <div class="card-body p-4">
        <h5>Liste officielle des agents</h5>
        <p class="text-muted">Cartes et liste des agents (inscriptions arrêtées).</p>
        <a class="btn btn-outline-primary" href="<?= e(base_url($config, 'direction/agents_official.php')) ?>">Voir les agents</a>
      </div>
    </div>
  </div>
  <?php endif; ?>
  <div class="col-md-6">
    <div class="card card-soft">
      <div class="card-body p-4">
        <h5>Candidats</h5>
        <p class="text-muted">Publier et consulter la liste officielle des candidats.</p>
        <a class="btn btn-outline-primary" href="<?= e(base_url($config, 'direction/candidates.php')) ?>">Voir les candidats</a>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card card-soft">
      <div class="card-body p-4">
        <h5>Arrêter la liste</h5>
        <p class="text-muted">Ferme les inscriptions et soumissions.</p>
        <a class="btn btn-danger" href="<?= e(base_url($config, 'direction/close_list.php')) ?>">Arrêter</a>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card card-soft">
      <div class="card-body p-4">
        <h5>Vote & Résultats</h5>
        <p class="text-muted">Ouvrir / clôturer le vote et publier les résultats.</p>
        <div class="d-flex flex-wrap gap-2">
          <a class="btn btn-outline-success" href="<?= e(base_url($config, 'direction/voting.php')) ?>">Paramètres du vote</a>
          <a class="btn btn-outline-dark" href="<?= e(base_url($config, 'direction/results.php')) ?>">Voir les résultats</a>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card card-soft">
      <div class="card-body p-4">
        <h5>Sauvegardes XML</h5>
        <p class="text-muted">Consulter et restaurer les sauvegardes automatiques de la base.</p>
        <a class="btn btn-outline-warning" href="<?= e(base_url($config, 'direction/backup.php')) ?>">Gérer les sauvegardes</a>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../_layout_bottom.php'; ?>
