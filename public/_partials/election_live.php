<?php
/** @var PDO $pdo */
/** @var array $config */
if (!elections_en_cours($pdo)) {
    return;
}
$stats = election_live_stats($pdo);
$refreshUrl = $_SERVER['REQUEST_URI'] ?? '';
?>
<meta http-equiv="refresh" content="30">
<div class="card card-soft mb-4 border-primary">
  <div class="card-body p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="mb-0 text-primary">Déroulement des élections en temps réel</h5>
      <span class="badge text-bg-success">EN COURS</span>
    </div>
    <p class="text-muted small mb-3">Actualisation automatique toutes les 30 secondes.</p>

    <div class="row g-3 mb-3">
      <div class="col-md-3">
        <div class="p-3 bg-light rounded text-center">
          <div class="fs-4 fw-bold text-primary"><?= (int)$stats['total_voters'] ?></div>
          <div class="small text-muted">Votants</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="p-3 bg-light rounded text-center">
          <div class="fs-4 fw-bold"><?= (int)$stats['total_electors'] ?></div>
          <div class="small text-muted">Électeurs inscrits</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="p-3 bg-light rounded text-center">
          <div class="fs-4 fw-bold text-warning"><?= (int)$stats['abstentions'] ?></div>
          <div class="small text-muted">Abstentions</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="p-3 bg-light rounded text-center">
          <div class="fs-4 fw-bold text-success"><?= e((string)$stats['participation_rate']) ?>%</div>
          <div class="small text-muted">Participation</div>
        </div>
      </div>
    </div>

    <div class="mb-3">
      <div class="progress" style="height: 22px;">
        <div class="progress-bar bg-success" style="width: <?= min(100, (float)$stats['participation_rate']) ?>%;">
          Participation <?= e((string)$stats['participation_rate']) ?>%
        </div>
      </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3">
      <span class="badge text-bg-primary">En ligne : <?= (int)$stats['online_votes'] ?></span>
      <span class="badge text-bg-dark">Physique : <?= (int)$stats['physical_votes'] ?></span>
    </div>

    <?php if ($stats['candidates']): ?>
      <h6 class="mb-2">Classement provisoire</h6>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr>
              <th>Candidat</th>
              <th>Parti</th>
              <th class="text-end">Voix</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($stats['candidates'] as $c): ?>
              <tr>
                <td><?= e($c['nom'] . ' ' . $c['prenom']) ?></td>
                <td><?= e($c['party_name']) ?></td>
                <td class="text-end fw-bold"><?= (int)$c['votes_count'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
