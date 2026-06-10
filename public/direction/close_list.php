<?php
require_once __DIR__ . '/../../app/bootstrap.php';

$user = require_role($pdo, $config, 'DIRECTION');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    setting_set($pdo, 'list_closed', '1');
    trigger_backup($pdo, $config, 'close_list');
    flash_set('main', 'Liste électorale arrêtée.', 'success');
    redirect(base_url($config, 'direction/index.php'));
}

$title = 'Arrêter la liste';
require __DIR__ . '/../_layout_top.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Arrêter la liste électorale</h3>
  <a class="btn btn-outline-secondary" href="<?= e(base_url($config, 'direction/index.php')) ?>">Retour</a>
</div>

<div class="card card-soft">
  <div class="card-body p-4">
    <div class="alert alert-warning">
      Cette action ferme les inscriptions et soumissions definitivement jusqu'aux prochaines elections. ( irréversible sans modifier la DB)
    </div>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <button class="btn btn-danger">Confirmer l’arrêt</button>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../_layout_bottom.php'; ?>


