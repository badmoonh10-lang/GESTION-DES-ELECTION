<?php
require_once __DIR__ . '/../../app/bootstrap.php';

$user = require_role($pdo, $config, 'DIRECTION');
$title = 'Liste officielle des agents';

if (!list_is_closed($pdo)) {
    flash_set('main', 'Disponible uniquement lorsque les inscriptions sur les listes électorales sont arrêtées.', 'warning');
    redirect(base_url($config, 'direction/index.php'));
}

$st = $pdo->query(
    'SELECT fa.*, u.username FROM field_agents fa LEFT JOIN users u ON u.id = fa.user_id ORDER BY fa.matricule ASC'
);
$agents = $st->fetchAll();

require __DIR__ . '/../_layout_top.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Liste officielle des agents de terrain</h3>
  <div class="d-flex flex-wrap gap-2">
    <a class="btn btn-outline-secondary" href="<?= e(base_url($config, 'direction/index.php')) ?>">Retour</a>
    <?php if ($agents): ?>
      <a class="btn btn-dark" href="<?= e(base_url($config, 'direction/agents_official_pdf.php')) ?>" target="_blank">Exporter PDF</a>
    <?php endif; ?>
  </div>
</div>

<div class="card card-soft">
  <div class="card-body p-4">
    <?php if (!$agents): ?>
      <div class="text-muted">Aucun agent enregistré.</div>
    <?php else: ?>
      <div class="table-responsive mb-4">
        <table class="table table-sm align-middle">
          <thead>
            <tr>
              <th>Matricule</th>
              <th>Photo</th>
              <th>Nom</th>
              <th>Prénom</th>
              <th>Genre</th>
              <th>Âge</th>
              <th>CNI</th>
              <th>Bureau</th>
              <th class="text-end">Carte</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($agents as $a): ?>
              <tr>
                <th><?= e($a['matricule']) ?></th>
                <td>
                  <?php if (!empty($a['profile_photo'])): ?>
                    <img src="<?= e(media_url($config, $a['profile_photo']) ?? '') ?>" alt="" class="rounded" width="40" height="40" style="object-fit:cover">
                  <?php else: ?>
                    <span class="text-muted small">N/A</span>
                  <?php endif; ?>
                </td>
                <td><?= e($a['nom']) ?></td>
                <td><?= e($a['prenom']) ?></td>
                <td><?= e($a['genre']) ?></td>
                <td><?= e((string)$a['age']) ?></td>
                <td><?= e($a['cni']) ?></td>
                <td><?= e($a['bureau_vote'] ?? '') ?></td>
                <td class="text-end">
                  <a class="btn btn-sm btn-dark" href="<?= e(base_url($config, 'agent_card.php?matricule=' . urlencode($a['matricule']))) ?>">PDF</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <h5 class="mb-3">Cartes des agents</h5>
      <div class="row g-3">
        <?php foreach ($agents as $a): ?>
          <div class="col-md-6 col-lg-4">
            <div class="card card-soft h-100">
              <div class="card-body p-3">
                <div class="d-flex gap-3 align-items-center">
                  <?php if (!empty($a['profile_photo'])): ?>
                    <img src="<?= e(media_url($config, $a['profile_photo']) ?? '') ?>" alt="" class="rounded" width="56" height="56" style="object-fit:cover">
                  <?php else: ?>
                    <div class="bg-secondary rounded d-flex align-items-center justify-content-center text-white" style="width:56px;height:56px;font-size:12px;">N/A</div>
                  <?php endif; ?>
                  <div>
                    <div class="fw-bold"><?= e($a['nom'] . ' ' . $a['prenom']) ?></div>
                    <span class="badge text-bg-primary"><?= e($a['matricule']) ?></span>
                    <div class="small text-muted mt-1"><?= e($a['bureau_vote'] ?? '') ?></div>
                  </div>
                </div>
                <div class="mt-2">
                  <a class="btn btn-sm btn-outline-dark w-100" href="<?= e(base_url($config, 'agent_card.php?matricule=' . urlencode($a['matricule']))) ?>">Télécharger la carte</a>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../_layout_bottom.php'; ?>
