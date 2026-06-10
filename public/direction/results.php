<?php
require_once __DIR__ . '/../../app/bootstrap.php';

$user = require_role($pdo, $config, 'DIRECTION');
$title = 'Résultats du vote';

$votingOpen = setting($pdo, 'voting_open', '0') === '1';

// Nombre total d'électeurs approuvés
$st = $pdo->query(
    "SELECT COUNT(DISTINCT e.id) AS total
     FROM electors e
     JOIN enrollments en ON en.elector_id = e.id
     WHERE en.status = 'APPROVED'"
);
$totalElectors = (int)($st->fetch()['total'] ?? 0);

// Nombre de votants (électeurs ayant déposé un vote)
$st = $pdo->query("SELECT COUNT(DISTINCT elector_id) AS total FROM votes");
$totalVoters = (int)($st->fetch()['total'] ?? 0);
$abstentions = max(0, $totalElectors - $totalVoters);

$participationRate = $totalElectors > 0 ? round(($totalVoters / $totalElectors) * 100, 2) : 0.0;
$abstentionRate = $totalElectors > 0 ? round(($abstentions / $totalElectors) * 100, 2) : 0.0;

// Résultats par candidat
$st = $pdo->query(
    "SELECT c.id, c.party_name,
            e.nom, e.prenom,
            COUNT(v.id) AS votes_count
     FROM candidates c
     JOIN electors e ON e.id = c.elector_id
     LEFT JOIN votes v ON v.candidate_id = c.id
     GROUP BY c.id, c.party_name, e.nom, e.prenom
     ORDER BY votes_count DESC, c.id ASC"
);
$results = $st->fetchAll();

require __DIR__ . '/../_layout_top.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Résultats du vote</h3>
  <a class="btn btn-outline-secondary" href="<?= e(base_url($config, 'direction/index.php')) ?>">Retour</a>
</div>

<?php if ($votingOpen): ?>
  <div class="alert alert-warning">
    Le vote est encore ouvert. Les résultats sont provisoires.
  </div>
<?php else: ?>
  <div class="alert alert-success">
    Le vote est clôturé. Les résultats ci-dessous sont définitifs (publiés).
  </div>
<?php endif; ?>

<div class="row g-4 mb-4">
  <div class="col-md-6">
    <div class="card card-soft">
      <div class="card-body p-4">
        <h5 class="mb-3">Participation</h5>
        <p class="mb-1"><strong>Électeurs inscrits (approuvés):</strong> <?= $totalElectors ?></p>
        <p class="mb-1"><strong>Votants:</strong> <?= $totalVoters ?></p>
        <p class="mb-3"><strong>Abstentions:</strong> <?= $abstentions ?></p>

        <div class="mb-2">Diagramme participation / abstention</div>
        <div class="progress" style="height: 24px;">
          <div class="progress-bar bg-success" role="progressbar"
               style="width: <?= $participationRate ?>%;"
               aria-valuenow="<?= $participationRate ?>" aria-valuemin="0" aria-valuemax="100">
            <?= $participationRate ?>% participation
          </div>
          <div class="progress-bar bg-secondary" role="progressbar"
               style="width: <?= $abstentionRate ?>%;"
               aria-valuenow="<?= $abstentionRate ?>" aria-valuemin="0" aria-valuemax="100">
            <?= $abstentionRate ?>% abstention
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card card-soft">
      <div class="card-body p-4">
        <h5 class="mb-3">Synthèse des voix</h5>
        <?php if (!$results): ?>
          <div class="text-muted">Aucun candidat / aucun vote.</div>
        <?php else: ?>
          <?php
          $totalExpressed = 0;
          foreach ($results as $r) {
              $totalExpressed += (int)$r['votes_count'];
          }

          // Palette de couleurs pour distinguer visuellement chaque candidat
          $palette = [
              '#0d6efd', // bleu
              '#198754', // vert
              '#ffc107', // jaune
              '#dc3545', // rouge
              '#6f42c1', // violet
              '#20c997', // turquoise
              '#fd7e14', // orange
              '#6610f2', // indigo
          ];
          ?>
          <p class="mb-1"><strong>Bulletins exprimés:</strong> <?= $totalExpressed ?></p>
          <p class="text-muted mb-3 small">Les pourcentages sont basés sur les bulletins valides exprimés.</p>
          <?php if ($totalExpressed > 0): ?>
            <div class="progress" style="height: 24px;">
          <?php
          $idx = 0;
          foreach ($results as $r):
              $pct = $totalExpressed > 0 ? round(((int)$r['votes_count'] / $totalExpressed) * 100, 1) : 0;
              $color = $palette[$idx % count($palette)];
              $idx++;
              ?>
                <div class="progress-bar" role="progressbar"
                     style="width: <?= $pct ?>%; background-color: <?= $color ?>;"
                     aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100">
                  <?= e($r['nom']) ?> (<?= $pct ?>%)
                </div>
          <?php endforeach; ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="card card-soft">
  <div class="card-body p-4">
    <h5 class="mb-3">Détail par candidat</h5>
    <?php if (!$results): ?>
      <div class="text-muted">Aucun candidat / aucun vote.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead>
            <tr>
              <th>Candidat</th>
              <th>Parti</th>
              <th>Voix obtenues</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($results as $r): ?>
              <tr>
                <td><?= e($r['nom'] . ' ' . $r['prenom']) ?></td>
                <td><?= e($r['party_name']) ?></td>
                <td><?= (int)$r['votes_count'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../_layout_bottom.php'; ?>


