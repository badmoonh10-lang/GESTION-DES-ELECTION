<?php
require_once __DIR__ . '/../../app/bootstrap.php';

$user = require_role($pdo, $config, 'ELECTOR');
$title = 'Pièces jointes';

// Récupère le dossier courant
$st = $pdo->prepare(
    'SELECT en.id, en.dossier_code, en.status
     FROM enrollments en
     JOIN electors e ON e.id = en.elector_id
     WHERE e.user_id = ?
     ORDER BY en.id DESC
     LIMIT 1'
);
$st->execute([(int)$user['id']]);
$en = $st->fetch();
if (!$en) {
    flash_set('main', 'Aucun dossier trouvé.', 'warning');
    redirect(base_url($config, 'elector/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();

    $type = (string)($_POST['file_type'] ?? 'CNI');
    if (!in_array($type, ['CNI', 'ACTE_NAISSANCE', 'AUTRE'], true)) {
        $type = 'AUTRE';
    }

    if (empty($_FILES['file']['tmp_name'])) {
        flash_set('main', 'Veuillez choisir un fichier.', 'warning');
        redirect(base_url($config, 'elector/upload.php'));
    }

    $file = $_FILES['file'];
    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
    if (!in_array($ext, $allowed, true)) {
        flash_set('main', 'Formats autorisés: JPG, PNG, PDF.', 'danger');
        redirect(base_url($config, 'elector/upload.php'));
    }

    $safeName = $en['dossier_code'] . '_' . $type . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $targetPath = rtrim($config['app']['upload_dir'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        flash_set('main', 'Upload impossible.', 'danger');
        redirect(base_url($config, 'elector/upload.php'));
    }

    $relPath = 'storage/uploads/' . $safeName; // pour servir via Apache si besoin
    $st = $pdo->prepare('INSERT INTO attachments(enrollment_id, file_type, file_path) VALUES(?,?,?)');
    $st->execute([(int)$en['id'], $type, $relPath]);

    flash_set('main', 'Fichier envoyé.', 'success');
    redirect(base_url($config, 'elector/upload.php'));
}

$st = $pdo->prepare('SELECT id, file_type, file_path, uploaded_at FROM attachments WHERE enrollment_id = ? ORDER BY id DESC');
$st->execute([(int)$en['id']]);
$files = $st->fetchAll();

require __DIR__ . '/../_layout_top.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Pièces jointes</h3>
  <a class="btn btn-outline-secondary" href="<?= e(base_url($config, 'elector/index.php')) ?>">Retour</a>
</div>

<div class="card card-soft mb-4">
  <div class="card-body p-4">
    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
      <span class="badge text-bg-primary">Dossier: <?= e($en['dossier_code']) ?></span>
      <span class="badge text-bg-secondary">Statut: <?= e($en['status']) ?></span>
    </div>

    <form method="post" enctype="multipart/form-data" class="row g-2 align-items-end">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <div class="col-md-4">
        <label class="form-label">Type</label>
        <select class="form-select" name="file_type">
          <option value="CNI">CNI</option>
          <option value="ACTE_NAISSANCE">Acte de naissance</option>
          <option value="AUTRE">Autre</option>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">Fichier (JPG/PNG/PDF)</label>
        <input class="form-control" type="file" name="file" required>
      </div>
      <div class="col-md-2">
        <button class="btn btn-primary w-100">Envoyer</button>
      </div>
    </form>
  </div>
</div>

<div class="card card-soft">
  <div class="card-body p-4">
    <h5 class="mb-3">Fichiers envoyés</h5>
    <?php if (!$files): ?>
      <div class="text-muted">Aucun fichier.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead>
            <tr>
              <th>Type</th>
              <th>Chemin</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($files as $f): ?>
              <tr>
                <td><span class="badge text-bg-light border"><?= e($f['file_type']) ?></span></td>
                <td class="text-muted small"><?= e($f['file_path']) ?></td>
                <td class="text-muted small"><?= e($f['uploaded_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../_layout_bottom.php'; ?>


