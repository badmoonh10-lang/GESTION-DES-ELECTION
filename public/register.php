<?php
require_once __DIR__ . '/../app/bootstrap.php';

$title = 'Inscription Électeur';
$user = current_user($pdo);
if ($user) {
    redirect(base_url($config, 'dashboard.php'));
}

$listClosed = setting($pdo, 'list_closed', '0') === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    if ($listClosed) {
        flash_set('main', 'La liste électorale est arrêtée. Inscriptions fermées.', 'danger');
        redirect(base_url($config, 'index.php'));
    }

    $nom = trim((string)($_POST['nom'] ?? ''));
    $prenom = trim((string)($_POST['prenom'] ?? ''));
    $genre = (string)($_POST['genre'] ?? 'M');
    $age = (int)($_POST['age'] ?? 0);
    $cni = trim((string)($_POST['cni'] ?? ''));
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $profileFile = $_FILES['profile_photo'] ?? null;
    $profileExt = null;

    $errors = [];
    if ($nom === '' || $prenom === '' || $cni === '' || $username === '' || $password === '' || $age <= 0) {
        $errors[] = 'Veuillez remplir correctement tous les champs.';
    }
    if (!$profileFile || ($profileFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Photo de profil requise.';
    } else {
        $profileExt = strtolower(pathinfo($profileFile['name'] ?? '', PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png'];
        if (!in_array($profileExt, $allowed, true)) {
            $errors[] = 'Photo de profil: formats autorisés JPG/PNG.';
        } elseif (!is_uploaded_file($profileFile['tmp_name'])) {
            $errors[] = 'Upload de la photo invalide.';
        }
    }

    if ($errors) {
        flash_set('main', implode(' ', $errors), 'warning');
    } else {
        try {
            $pdo->beginTransaction();
            $savedFilePath = null;

            $st = $pdo->prepare('INSERT INTO users(username, password_hash, role) VALUES(?, ?, ?)');
            $st->execute([$username, password_hash($password, PASSWORD_BCRYPT), 'ELECTOR']);
            $userId = (int)$pdo->lastInsertId();

            $dossier = gen_dossier_code($pdo);

            // Sauvegarde de la photo de profil
            $profileSafeName = $dossier . '_PROFILE_' . bin2hex(random_bytes(6)) . '.' . $profileExt;
            $targetPath = rtrim($config['app']['upload_dir'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $profileSafeName;
            if (!move_uploaded_file($profileFile['tmp_name'], $targetPath)) {
                throw new RuntimeException('Upload de la photo impossible.');
            }
            $savedFilePath = $targetPath;
            $relProfilePath = 'storage/uploads/' . $profileSafeName;

            $st = $pdo->prepare('INSERT INTO electors(user_id, nom, prenom, genre, age, cni, profile_photo) VALUES(?,?,?,?,?,?,?)');
            $st->execute([$userId, $nom, $prenom, $genre, $age, $cni, $relProfilePath]);
            $electorId = (int)$pdo->lastInsertId();

            $st = $pdo->prepare('INSERT INTO enrollments(dossier_code, elector_id, created_by_role, created_by_user_id, status) VALUES(?,?,?,?,?)');
            $st->execute([$dossier, $electorId, 'ONLINE', $userId, 'PRE_ENROLLED']);

            $pdo->commit();
            trigger_backup($pdo, $config, 'register_elector');

            flash_set('main', 'Compte créé. Votre numéro de dossier est: ' . $dossier, 'success');
            login($pdo, $username, $password);
            redirect(base_url($config, 'dashboard.php'));
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (isset($savedFilePath) && is_string($savedFilePath) && is_file($savedFilePath)) {
                @unlink($savedFilePath);
            }
            flash_set('main', 'Erreur: username ou CNI existe déjà, ou upload photo impossible.', 'danger');
        }
    }
}

require __DIR__ . '/_layout_top.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="card card-soft">
      <div class="card-body p-4">
        <h4 class="mb-1">Inscription Électeur</h4>
        <p class="text-muted mb-4">Créer un compte puis compléter la demande (pièces jointes) et soumettre.</p>

        <?php if ($listClosed): ?>
          <div class="alert alert-danger">La liste électorale est arrêtée. Inscriptions fermées.</div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Nom</label>
              <input class="form-control" name="nom" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Prénom</label>
              <input class="form-control" name="prenom" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Genre</label>
              <select class="form-select" name="genre">
                <option value="M">M</option>
                <option value="F">F</option>
                <option value="AUTRE">AUTRE</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Âge</label>
              <input class="form-control" type="number" min="1" name="age" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">N° CNI</label>
              <input class="form-control" name="cni" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Nom d’utilisateur</label>
              <input class="form-control" name="username" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Mot de passe</label>
              <input class="form-control" type="password" name="password" required>
            </div>
              <div class="col-md-12">
                  <label class="form-label">Profile</label>
                  <input class="form-control" type="file" name="profile_photo" required>
              </div>
          </div>

          <div class="mt-4">
            <button class="btn btn-success" <?= $listClosed ? 'disabled' : '' ?>>Créer le compte</button>
            <a class="btn btn-link" href="<?= e(base_url($config, 'login.php')) ?>">J’ai déjà un compte</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>


