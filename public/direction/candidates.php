<?php
require_once __DIR__ . '/../../app/bootstrap.php';

$user = require_role($pdo, $config, 'DIRECTION');
$title = 'Liste des candidats';

$st = $pdo->query(
    "SELECT c.id, c.party_name, c.program_text, c.caution_file, c.created_at,
            e.nom, e.prenom, e.age, e.cni
     FROM candidates c
     JOIN electors e ON e.id = c.elector_id
     ORDER BY c.created_at DESC"
);
$rows = $st->fetchAll();

require __DIR__ . '/../_layout_top.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Liste officielle des candidats</h3>
  <a class="btn btn-outline-secondary" href="<?= e(base_url($config, 'direction/index.php')) ?>">Retour</a>
</div>

<div class="card card-soft">
  <div class="card-body p-4">
    <?php if (!$rows): ?>
      <div class="text-muted">Aucun candidat enregistré.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead>
            <tr>
              <th>Candidat</th>
              <th>Parti</th>
              <th>Âge</th>
              <th>CNI</th>
              <th>Programme</th>
              <th>Preuve de caution</th>
              <th>Créé le</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?= e($r['nom'] . ' ' . $r['prenom']) ?></td>
                <td><?= e($r['party_name']) ?></td>
                <td><?= e((string)$r['age']) ?></td>
                <td><?= e($r['cni']) ?></td>
                <td class="small text-muted"><?= nl2br(e($r['program_text'] ?? '')) ?></td>
                <td class="small text-muted"><?= e($r['caution_file']) ?></td>
                <td class="small text-muted"><?= e($r['created_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../_layout_bottom.php'; ?>


