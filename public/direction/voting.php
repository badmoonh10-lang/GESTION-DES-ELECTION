<?php
require_once __DIR__ . '/../../app/bootstrap.php';

$user = require_role($pdo, $config, 'DIRECTION');
$title = 'Paramètres du vote';

$votingOpen = setting($pdo, 'voting_open', '0') === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'open') {
        setting_set($pdo, 'voting_open', '1');
        trigger_backup($pdo, $config, 'open_voting');
        flash_set('main', 'Le vote est maintenant OUVERT.', 'success');
    } elseif ($action === 'close') {
        setting_set($pdo, 'voting_open', '0');
        trigger_backup($pdo, $config, 'close_voting');
        flash_set('main', 'Le vote est maintenant CLÔTURÉ.', 'success');
    }
    redirect(base_url($config, 'direction/voting.php'));
}

require __DIR__ . '/../_layout_top.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Paramètres du vote</h3>
  <a class="btn btn-outline-secondary" href="<?= e(base_url($config, 'direction/index.php')) ?>">Retour</a>
</div>

<div class="card card-soft">
  <div class="card-body p-4">
    <p>
      Statut actuel du vote :
      <?php if ($votingOpen): ?>
        <span class="badge text-bg-success">OUVERT</span>
      <?php else: ?>
        <span class="badge text-bg-danger">FERMÉ</span>
      <?php endif; ?>
    </p>

    <div class="d-flex flex-wrap gap-2 mt-3">
      <form method="post">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="open">
        <button class="btn btn-success" <?= $votingOpen ? 'disabled' : '' ?>>Ouvrir le vote</button>
      </form>

      <form method="post">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="close">
        <button class="btn btn-danger" <?= !$votingOpen ? 'disabled' : '' ?>>Clôturer le vote</button>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../_layout_bottom.php'; ?>


