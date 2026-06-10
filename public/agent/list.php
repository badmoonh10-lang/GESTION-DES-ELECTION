<?php
require_once __DIR__ . '/../../app/bootstrap.php';

$user = require_role($pdo, $config, 'AGENT');
$title = 'Mes dossiers';

$st = $pdo->prepare(
    'SELECT en.dossier_code, en.status, en.created_at, en.submitted_at,
            el.nom, el.prenom, el.cni, el.profile_photo
     FROM enrollments en
     JOIN electors el ON el.id = en.elector_id
     WHERE en.created_by_user_id = ?
     ORDER BY en.id DESC'
);
$st->execute([(int)$user['id']]);
$rows = $st->fetchAll();

require __DIR__ . '/../_layout_top.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Mes dossiers</h3>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="<?= e(base_url($config, 'agent/index.php')) ?>">Retour</a>
    <a class="btn btn-primary" href="<?= e(base_url($config, 'agent/new.php')) ?>">Nouveau</a>
  </div>
</div>

<div class="card card-soft">
  <div class="card-body p-4">
    <?php if (!$rows): ?>
      <div class="text-muted">Aucun dossier.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead>
            <tr>
              <th>Photo</th>
              <th>Dossier</th>
              <th>Électeur</th>
              <th>Statut</th>
              <th>Date</th>
              <th class="text-end">Actions</th>
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
                <td><span class="badge text-bg-primary"><?= e($r['dossier_code']) ?></span></td>
                <td><?= e($r['nom'] . ' ' . $r['prenom']) ?><div class="text-muted small"><?= e($r['cni']) ?></div></td>
                <td><span class="badge text-bg-secondary"><?= e($r['status']) ?></span></td>
                <td class="text-muted small"><?= e($r['created_at']) ?></td>
                <td class="text-end">
                  <?php if (in_array($r['status'], ['PRE_ENROLLED'], true)): ?>
                    <a class="btn btn-sm btn-success" href="<?= e(base_url($config, 'agent/submit.php?dossier=' . urlencode($r['dossier_code']))) ?>">Soumettre</a>
                  <?php endif; ?>
                  <?php if ($r['status'] === 'APPROVED'): ?>
                    <a class="btn btn-sm btn-dark" href="<?= e(base_url($config, 'card.php?dossier=' . urlencode($r['dossier_code']))) ?>">Carte PDF</a>
                  <?php endif; ?>
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


