<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/backup.php';

$user = require_role($pdo, $config, 'DIRECTION');
$title = 'Sauvegardes XML';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'manual_backup') {
        $file = backup_database_xml($pdo, $config, 'manual');
        flash_set('main', $file ? 'Sauvegarde créée : ' . $file : 'Échec de la sauvegarde.', $file ? 'success' : 'danger');
        redirect(base_url($config, 'direction/backup.php'));
    }

    if ($action === 'restore') {
        $file = basename((string)($_POST['file'] ?? ''));
        $filepath = backup_dir($config) . DIRECTORY_SEPARATOR . $file;
        try {
            restore_database_xml($pdo, $filepath);
            trigger_backup($pdo, $config, 'pre_restore_snapshot');
            flash_set('main', 'Base restaurée depuis ' . $file, 'success');
        } catch (Throwable $e) {
            flash_set('main', 'Restauration échouée : ' . $e->getMessage(), 'danger');
        }
        redirect(base_url($config, 'direction/backup.php'));
    }
}

$files = list_backup_files($config);

require __DIR__ . '/../_layout_top.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Sauvegardes XML de la base</h3>
  <div class="d-flex gap-2">
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="manual_backup">
      <button class="btn btn-primary">Créer une sauvegarde</button>
    </form>
    <a class="btn btn-outline-secondary" href="<?= e(base_url($config, 'direction/index.php')) ?>">Retour</a>
  </div>
</div>

<div class="alert alert-info">
  Une sauvegarde XML est générée automatiquement après chaque opération CRUD sur la base de données.
  Vous pouvez consulter et restaurer ces fichiers depuis cette page (accès Direction uniquement).
</div>

<div class="card card-soft">
  <div class="card-body p-4">
    <?php if (!$files): ?>
      <div class="text-muted">Aucune sauvegarde disponible.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead>
            <tr>
              <th>Fichier</th>
              <th>Date</th>
              <th>Taille</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($files as $f): ?>
              <?php
              $basename = basename($f);
              $mtime = filemtime($f);
              ?>
              <tr>
                <td><code><?= e($basename) ?></code></td>
                <td><?= $mtime ? date('d/m/Y H:i:s', $mtime) : '—' ?></td>
                <td><?= e(number_format(filesize($f) / 1024, 1)) ?> Ko</td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url($config, 'direction/backup_download.php?file=' . urlencode($basename))) ?>">Télécharger</a>
                  <form method="post" class="d-inline" onsubmit="return confirm('Restaurer cette sauvegarde ? Les données actuelles seront remplacées.');">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="restore">
                    <input type="hidden" name="file" value="<?= e($basename) ?>">
                    <button class="btn btn-sm btn-warning">Restaurer</button>
                  </form>
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
