<?php
require_once __DIR__ . '/../../app/bootstrap.php';

require_login($config);
$user = current_user($pdo);
$title = 'Espace Candidat';

// Récupère l'électeur lié à cet utilisateur
$st = $pdo->prepare(
    "SELECT e.id AS elector_id, e.nom, e.prenom, e.age, e.genre, e.cni
     FROM electors e
     WHERE e.user_id = ?"
);
$st->execute([(int)$user['id']]);
$elector = $st->fetch();

// Vérifie les conditions d'éligibilité
$eligibilityError = null;
$approvedEnrollment = null;
if (!$elector) {
    $eligibilityError = "Vous devez d'abord être inscrit comme électeur.";
} else {
    // Vérifier l'âge
    if ((int)$elector['age'] <= 35) {
        $eligibilityError = "Âge insuffisant : il faut avoir plus de 35 ans pour être candidat.";
    } else {
        // Vérifier qu'il est dans la liste électorale (dossier approuvé)
        $st = $pdo->prepare(
            "SELECT en.id, en.dossier_code, en.status
             FROM enrollments en
             WHERE en.elector_id = ? AND en.status = 'APPROVED'
             ORDER BY en.id DESC
             LIMIT 1"
        );
        $st->execute([(int)$elector['elector_id']]);
        $approvedEnrollment = $st->fetch();
        if (!$approvedEnrollment) {
            $eligibilityError = "Vous devez avoir un dossier électoral approuvé pour être candidat.";
        }
    }
}

// Cherche un enregistrement candidat existant
$candidate = null;
if ($elector) {
    $st = $pdo->prepare(
        "SELECT c.*, e.nom, e.prenom
         FROM candidates c
         JOIN electors e ON e.id = c.elector_id
         WHERE c.elector_id = ?"
    );
    $st->execute([(int)$elector['elector_id']]);
    $candidate = $st->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();

    if ($eligibilityError) {
        flash_set('main', $eligibilityError, 'danger');
        redirect(base_url($config, 'candidate/index.php'));
    }

    if ($candidate) {
        flash_set('main', 'Vous êtes déjà enregistré comme candidat.', 'warning');
        redirect(base_url($config, 'candidate/index.php'));
    }

    $party = trim((string)($_POST['party_name'] ?? ''));
    $program = trim((string)($_POST['program_text'] ?? ''));
    $file = $_FILES['caution_file'] ?? null;

    $errors = [];
    if ($party === '') {
        $errors[] = 'Le nom du parti politique est obligatoire.';
    }
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'La preuve du versement de la caution (30 millions) est obligatoire.';
    } else {
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        if (!in_array($ext, $allowed, true)) {
            $errors[] = 'Preuve de caution: formats autorisés JPG, PNG ou PDF.';
        } elseif (!is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'Upload de la preuve de caution invalide.';
        }
    }

    if ($errors) {
        flash_set('main', implode(' ', $errors), 'danger');
        redirect(base_url($config, 'candidate/index.php'));
    }

    // Upload du fichier de caution
    $safeName = 'CAND_' . $approvedEnrollment['dossier_code'] . '_CAUTION_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $targetPath = rtrim($config['app']['upload_dir'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $safeName;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        flash_set('main', 'Échec de l\'upload de la preuve de caution.', 'danger');
        redirect(base_url($config, 'candidate/index.php'));
    }
    $relPath = 'storage/uploads/' . $safeName;

    $st = $pdo->prepare(
        "INSERT INTO candidates(elector_id, party_name, program_text, caution_file)
         VALUES(?,?,?,?)"
    );
    $st->execute([(int)$elector['elector_id'], $party, $program, $relPath]);

    flash_set('main', 'Votre candidature a été enregistrée.', 'success');
    redirect(base_url($config, 'candidate/index.php'));
}

require __DIR__ . '/../_layout_top.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Espace Candidat</h3>
  <a class="btn btn-outline-secondary" href="<?= e(base_url($config, 'dashboard.php')) ?>">Retour au dashboard</a>
</div>

<?php if ($eligibilityError): ?>
  <div class="alert alert-warning"><?= e($eligibilityError) ?></div>
<?php elseif ($candidate): ?>
  <div class="card card-soft mb-4">
    <div class="card-body p-4">
      <h5 class="mb-3">Votre candidature</h5>
      <p class="mb-1"><strong>Nom:</strong> <?= e($candidate['nom'] . ' ' . $candidate['prenom']) ?></p>
      <p class="mb-1"><strong>Parti politique:</strong> <?= e($candidate['party_name']) ?></p>
      <p class="mb-1"><strong>Programme:</strong><br><?= nl2br(e($candidate['program_text'] ?? '')) ?></p>
      <p class="mb-1"><strong>Preuve de caution:</strong> <span class="text-muted small"><?= e($candidate['caution_file']) ?></span></p>
    </div>
  </div>
<?php else: ?>
  <div class="card card-soft">
    <div class="card-body p-4">
      <h5 class="mb-3">Demande de candidature</h5>
      <p class="text-muted">
        Conditions : avoir plus de 35 ans, être inscrit dans la liste électorale (dossier approuvé),
        appartenir à un parti politique et fournir une preuve de versement de la caution de 30 millions (PDF ou image).
      </p>
      <form method="post" enctype="multipart/form-data" class="row g-3">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <div class="col-md-6">
          <label class="form-label">Parti politique</label>
          <input class="form-control" name="party_name" required>
        </div>
        <div class="col-md-12">
          <label class="form-label">Programme (résumé)</label>
          <textarea class="form-control" name="program_text" rows="4" placeholder="Présentez brièvement votre programme."></textarea>
        </div>
        <div class="col-md-12">
          <label class="form-label">Preuve de caution (PDF ou image)</label>
          <input class="form-control" type="file" name="caution_file" required>
        </div>
        <div class="col-12">
          <button class="btn btn-primary">Soumettre ma candidature</button>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../_layout_bottom.php'; ?>


