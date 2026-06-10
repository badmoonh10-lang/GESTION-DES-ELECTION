<?php
require_once __DIR__ . '/../../app/bootstrap.php';

$user = require_role($pdo, $config, 'AGENT');
$title = 'Nouveau pré‑enrôlement';

$listClosed = list_is_closed($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    if ($listClosed) {
        flash_set('main', 'La liste électorale est arrêtée. Inscriptions fermées.', 'danger');
        redirect(base_url($config, 'agent/index.php'));
    }

    $nom = trim((string)($_POST['nom'] ?? ''));
    $prenom = trim((string)($_POST['prenom'] ?? ''));
    $genre = (string)($_POST['genre'] ?? 'M');
    $age = (int)($_POST['age'] ?? 0);
    $cni = trim((string)($_POST['cni'] ?? ''));
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $createAccount = isset($_POST['create_account']);

    if ($nom === '' || $prenom === '' || $cni === '' || $age <= 0) {
        flash_set('main', 'Veuillez remplir correctement tous les champs obligatoires.', 'warning');
    } elseif ($createAccount && ($username === '' || $password === '')) {
        flash_set('main', 'Pseudo et mot de passe requis pour le compte électeur.', 'warning');
    } else {
        try {
            $pdo->beginTransaction();

            $dossier = gen_dossier_code($pdo);
            $profileRel = null;
            if (!empty($_FILES['profile_photo']['name'])) {
                $uploaded = save_uploaded_image($config, $dossier . '_PROFILE', $_FILES['profile_photo']);
                $profileRel = $uploaded['rel'];
            }

            $userId = null;
            if ($createAccount) {
                $pdo->prepare('INSERT INTO users(username, password_hash, role) VALUES(?, ?, ?)')
                    ->execute([$username, password_hash($password, PASSWORD_BCRYPT), 'ELECTOR']);
                $userId = (int)$pdo->lastInsertId();
            }

            $st = $pdo->prepare('INSERT INTO electors(user_id, nom, prenom, genre, age, cni, profile_photo) VALUES(?,?,?,?,?,?,?)');
            $st->execute([$userId, $nom, $prenom, $genre, $age, $cni, $profileRel]);
            $electorId = (int)$pdo->lastInsertId();

            $st = $pdo->prepare('INSERT INTO enrollments(dossier_code, elector_id, created_by_role, created_by_user_id, status) VALUES(?,?,?,?,?)');
            $st->execute([$dossier, $electorId, 'AGENT', (int)$user['id'], 'PRE_ENROLLED']);

            $pdo->commit();
            trigger_backup($pdo, $config, 'agent_pre_enrollment');
            $msg = 'Pré‑enrôlement créé. Dossier : ' . $dossier;
            if ($createAccount) {
                $msg .= ' — Compte en ligne créé (login : ' . $username . ')';
            }
            flash_set('main', $msg, 'success');
            redirect(base_url($config, 'agent/list.php'));
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash_set('main', 'Erreur : ' . $e->getMessage(), 'danger');
        }
    }
}

require __DIR__ . '/../_layout_top.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Nouveau pré‑enrôlement</h3>
  <a class="btn btn-outline-secondary" href="<?= e(base_url($config, 'agent/index.php')) ?>">Retour</a>
</div>

<?php if ($listClosed): ?>
  <div class="alert alert-danger">La liste électorale est arrêtée. Inscriptions fermées.</div>
<?php endif; ?>

<div class="card card-soft">
  <div class="card-body p-4">
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
        <div class="col-12">
          <label class="form-label">Photo de profil</label>
          <input class="form-control" type="file" name="profile_photo" accept=".jpg,.jpeg,.png">
          <div class="form-text">Optionnel mais recommandé pour la carte électorale.</div>
        </div>
        <div class="col-12">
          <hr>
          <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="create_account" id="create_account" value="1">
            <label class="form-check-label" for="create_account">
              Créer un compte pour le vote en ligne (pseudo et mot de passe)
            </label>
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Pseudo (login électeur)</label>
          <input class="form-control" name="username" placeholder="ex: jean.dupont">
        </div>
        <div class="col-md-6">
          <label class="form-label">Mot de passe</label>
          <input class="form-control" type="password" name="password">
        </div>
      </div>
      <div class="mt-4">
        <button class="btn btn-primary" <?= $listClosed ? 'disabled' : '' ?>>Créer le pré‑enrôlement</button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../_layout_bottom.php'; ?>
