<?php
require_once __DIR__ . '/../../app/bootstrap.php';

$user = require_role($pdo, $config, 'DIRECTION');
$title = 'Dossier';

$dossier = trim((string)($_GET['dossier'] ?? ''));
if ($dossier === '') {
    redirect(base_url($config, 'direction/requests.php'));
}

$st = $pdo->prepare(
    'SELECT en.id AS enrollment_id, en.dossier_code, en.status, en.direction_comment,
            el.id AS elector_id, el.nom, el.prenom, el.genre, el.age, el.cni, el.profile_photo
     FROM enrollments en
     JOIN electors el ON el.id = en.elector_id
     WHERE en.dossier_code = ?'
);
$st->execute([$dossier]);
$row = $st->fetch();
if (!$row) {
    flash_set('main', 'Dossier introuvable.', 'warning');
    redirect(base_url($config, 'direction/requests.php'));
}

// Pieces
$st = $pdo->prepare('SELECT file_type, file_path, uploaded_at FROM attachments WHERE enrollment_id=? ORDER BY id DESC');
$st->execute([(int)$row['enrollment_id']]);
$files = $st->fetchAll();

// Actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $action = (string)($_POST['action'] ?? '');
    $comment = trim((string)($_POST['comment'] ?? ''));

    $listClosed = setting($pdo, 'list_closed', '0') === '1';
    if ($listClosed) {
        flash_set('main', 'Liste arrêtée: actions bloquées.', 'danger');
        redirect(base_url($config, 'direction/view.php?dossier=' . urlencode($dossier)));
    }

    if ($action === 'approve') {
        $pdo->beginTransaction();
        $st = $pdo->prepare("UPDATE enrollments SET status='APPROVED', direction_comment=?, reviewed_at=NOW(), approved_at=NOW() WHERE id=?");
        $st->execute([$comment, (int)$row['enrollment_id']]);

        // Crée carte si absente
        $sig = setting($pdo, 'card_signature_path', 'assets/img/signature.png') ?? 'assets/img/signature.png';
        $st = $pdo->prepare('INSERT INTO cards(enrollment_id, qr_code, lieu, signature_path) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE qr_code=VALUES(qr_code)');
        // qr_code sera ensuite mis à jour avec le chemin réel du fichier QR
        $st->execute([(int)$row['enrollment_id'], $row['dossier_code'], 'IUT-Fv Bandjoun', $sig]);

        // Génère automatiquement un QR code persistant pour la carte de cet électeur
        try {
            $rootDir = realpath(__DIR__ . '/../../');
            if ($rootDir !== false) {
                $qrDir = $rootDir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'qr';
                if (!is_dir($qrDir)) {
                    @mkdir($qrDir, 0775, true);
                }

                $safeDossier = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)$row['dossier_code']);
                $qrFileName = 'qr_' . $safeDossier . '.png';
                $outputPng = $qrDir . DIRECTORY_SEPARATOR . $qrFileName;

                $tmpDir = sys_get_temp_dir();
                $qrId = bin2hex(random_bytes(8));
                $inputJson = $tmpDir . DIRECTORY_SEPARATOR . 'qr_input_' . $qrId . '.json';

                $payload = [
                    'dossier' => $row['dossier_code'],
                    'nom' => $row['nom'],
                    'prenom' => $row['prenom'],
                    'genre' => $row['genre'],
                    'age' => $row['age'],
                    'cni' => $row['cni'],
                    'profile_photo' => $row['profile_photo'] ?? null,
                ];

                file_put_contents($inputJson, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

                $python = 'python';
                $scriptPath = realpath($rootDir . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'gen_qr.py');
                if ($scriptPath !== false) {
                    $cmd = escapeshellcmd($python) . ' ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($inputJson) . ' ' . escapeshellarg($outputPng);
                    @shell_exec($cmd);

                    if (is_file($outputPng)) {
                        $relativePath = 'storage/qr/' . $qrFileName;
                        $st = $pdo->prepare('UPDATE cards SET qr_code=? WHERE enrollment_id=?');
                        $st->execute([$relativePath, (int)$row['enrollment_id']]);
                    }
                }
            }
        } catch (Throwable $e) {
            // En cas d'échec, la génération du QR restera assurée à la volée dans card.php
        }

        $pdo->commit();
        trigger_backup($pdo, $config, 'approve_enrollment');
        flash_set('main', 'Dossier approuvé.', 'success');
        redirect(base_url($config, 'direction/requests.php'));
    }

    if ($action === 'reject') {
        $st = $pdo->prepare("UPDATE enrollments SET status='REJECTED', direction_comment=?, reviewed_at=NOW(), rejected_at=NOW() WHERE id=?");
        $st->execute([$comment, (int)$row['enrollment_id']]);
        trigger_backup($pdo, $config, 'reject_enrollment');
        flash_set('main', 'Dossier rejeté.', 'warning');
        redirect(base_url($config, 'direction/requests.php'));
    }

    if ($action === 'edit_elector') {
        $nom = trim((string)($_POST['nom'] ?? ''));
        $prenom = trim((string)($_POST['prenom'] ?? ''));
        $genre = (string)($_POST['genre'] ?? 'M');
        $age = (int)($_POST['age'] ?? 0);
        if ($nom !== '' && $prenom !== '' && $age > 0) {
            $st = $pdo->prepare('UPDATE electors SET nom=?, prenom=?, genre=?, age=?, updated_at=NOW() WHERE id=?');
            $st->execute([$nom, $prenom, $genre, $age, (int)$row['elector_id']]);
            trigger_backup($pdo, $config, 'edit_elector');
            flash_set('main', 'Électeur modifié.', 'success');
        } else {
            flash_set('main', 'Champs invalides.', 'danger');
        }
        redirect(base_url($config, 'direction/view.php?dossier=' . urlencode($dossier)));
    }
}

require __DIR__ . '/../_layout_top.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Dossier <?= e($row['dossier_code']) ?></h3>
  <a class="btn btn-outline-secondary" href="<?= e(base_url($config, 'direction/requests.php')) ?>">Retour</a>
</div>

<div class="row g-4">
  <div class="col-lg-6">
    <div class="card card-soft">
      <div class="card-body p-4">
        <h5 class="mb-3">Électeur</h5>
        <?php if (!empty($row['profile_photo'])): ?>
          <div class="mb-3 text-center">
            <img src="<?= e(media_url($config, $row['profile_photo']) ?? '') ?>"
                 alt="Photo de <?= e($row['prenom'] . ' ' . $row['nom']) ?>"
                 class="rounded"
                 width="100" height="100"
                 style="object-fit:cover; border: 2px solid var(--bs-border-color);">
          </div>
        <?php endif; ?>
        <div><strong>Nom:</strong> <?= e($row['nom']) ?></div>
        <div><strong>Prénom:</strong> <?= e($row['prenom']) ?></div>
        <div><strong>Genre:</strong> <?= e($row['genre']) ?></div>
        <div><strong>Âge:</strong> <?= e((string)$row['age']) ?></div>
        <div><strong>CNI:</strong> <?= e($row['cni']) ?></div>

        <hr>
        <h6 class="mb-2">Modifier</h6>
        <form method="post" class="row g-2">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="edit_elector">
          <div class="col-md-6">
            <input class="form-control" name="nom" value="<?= e($row['nom']) ?>" required>
          </div>
          <div class="col-md-6">
            <input class="form-control" name="prenom" value="<?= e($row['prenom']) ?>" required>
          </div>
          <div class="col-md-4">
            <select class="form-select" name="genre">
              <option value="M" <?= $row['genre']==='M'?'selected':'' ?>>M</option>
              <option value="F" <?= $row['genre']==='F'?'selected':'' ?>>F</option>
              <option value="AUTRE" <?= $row['genre']==='AUTRE'?'selected':'' ?>>AUTRE</option>
            </select>
          </div>
          <div class="col-md-4">
            <input class="form-control" type="number" min="1" name="age" value="<?= e((string)$row['age']) ?>" required>
          </div>
          <div class="col-md-4">
            <button class="btn btn-outline-primary w-100">Enregistrer</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card card-soft mb-4">
      <div class="card-body p-4">
        <h5 class="mb-2">Statut</h5>
        <div class="d-flex flex-wrap gap-2 align-items-center">
          <span class="badge text-bg-secondary"><?= e($row['status']) ?></span>
          <?php if ($row['status'] === 'APPROVED'): ?>
            <a class="btn btn-sm btn-dark" href="<?= e(base_url($config, 'card.php?dossier=' . urlencode($row['dossier_code']))) ?>">Carte PDF</a>
          <?php endif; ?>
        </div>
        <?php if (!empty($row['direction_comment'])): ?>
          <div class="alert alert-info mt-3 mb-0"><?= e($row['direction_comment']) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card card-soft mb-4">
      <div class="card-body p-4">
        <h5 class="mb-3">Pièces jointes</h5>
        <?php if (!$files): ?>
          <div class="text-muted">Aucun fichier.</div>
        <?php else: ?>
          <ul class="mb-0">
            <?php foreach ($files as $f): ?>
              <li>
                <strong><?= e($f['file_type']) ?></strong> — <span class="text-muted small"><?= e($f['file_path']) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>

    <?php if (in_array($row['status'], ['SUBMITTED','PRE_ENROLLED'], true)): ?>
      <div class="card card-soft">
        <div class="card-body p-4">
          <h5 class="mb-3">Décision</h5>
          <form method="post" class="mb-3">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="approve">
            <label class="form-label">Commentaire (optionnel)</label>
            <input class="form-control mb-2" name="comment" placeholder="Ex: Informations conformes.">
            <button class="btn btn-success w-100">Approuver</button>
          </form>

          <form method="post">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="reject">
            <label class="form-label">Motif de rejet</label>
            <input class="form-control mb-2" name="comment" placeholder="Ex: CNI illisible." required>
            <button class="btn btn-outline-danger w-100">Rejeter</button>
          </form>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../_layout_bottom.php'; ?>


