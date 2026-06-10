<?php
require_once __DIR__ . '/../../app/bootstrap.php';

$user = require_role($pdo, $config, 'DIRECTION');
$title = 'Liste électorale officielle';

$st = $pdo->query(
    "SELECT en.dossier_code, en.approved_at,
            el.nom, el.prenom, el.genre, el.age, el.cni, el.profile_photo
     FROM enrollments en
     JOIN electors el ON el.id = en.elector_id
     WHERE en.status = 'APPROVED'
     ORDER BY en.approved_at DESC"
);
$rows = $st->fetchAll();

require __DIR__ . '/../_layout_top.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Liste électorale officielle</h3>
  <div class="d-flex flex-wrap gap-2">
    <a class="btn btn-outline-secondary" href="<?= e(base_url($config, 'direction/index.php')) ?>">Retour</a>
    <?php if ($rows): ?>
      <a class="btn btn-dark" href="<?= e(base_url($config, 'direction/official_pdf.php')) ?>" target="_blank" rel="noopener">
        Exporter / imprimer en PDF
      </a>
    <?php endif; ?>
  </div>
</div>

<div class="card card-soft">
  <div class="card-body p-4">
    <?php if (!$rows): ?>
      <div class="text-muted">Aucun électeur approuvé.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead>
            <tr>
              <th>Photo</th>
              <th>Dossier</th>
              <th>Nom</th>
              <th>Prénom</th>
              <th>Genre</th>
              <th>Âge</th>
              <th>CNI</th>
              <th>Approuvé</th>
              <th class="text-end">Carte</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td>
                  <?php if (!empty($r['profile_photo'])): ?>
                    <img src="<?= e(media_url($config, $r['profile_photo']) ?? '') ?>" alt="" class="rounded" width="40" height="40" style="object-fit:cover">
                  <?php else: ?>
                    <span class="text-muted small">N/A</span>
                  <?php endif; ?>
                </td>
                <th><?= e($r['dossier_code']) ?></th>
                <td><?= e($r['nom']) ?></td>
                <td><?= e($r['prenom']) ?></td>
                <td><?= e($r['genre']) ?></td>
                <td><?= e((string)$r['age']) ?></td>
                <td><?= e($r['cni']) ?></td>
                <td class="text-muted small"><?= e($r['approved_at']) ?></td>
                <td class="text-end">
                  <a class="btn btn-sm btn-dark" href="<?= e(base_url($config, 'card.php?dossier=' . urlencode($r['dossier_code']))) ?>">PDF</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../_layout_bottom.php'; ?>
