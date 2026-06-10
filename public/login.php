<?php
require_once __DIR__ . '/../app/bootstrap.php';

$title = 'Connexion';
$user = current_user($pdo);
if ($user) {
    redirect(base_url($config, 'dashboard.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        flash_set('main', 'Veuillez remplir tous les champs.', 'warning');
    } elseif (login($pdo, $username, $password)) {
        flash_set('main', 'Connexion réussie.', 'success');
        redirect(base_url($config, 'dashboard.php'));
    } else {
        flash_set('main', 'Identifiants invalides.', 'danger');
    }
}

require __DIR__ . '/_layout_top.php';
?>

<div class="row justify-content-center">
  <div class="col-md-6 col-lg-5">
    <div class="card card-soft">
      <div class="card-body p-4">
        <h4 class="mb-3">Connexion</h4>
        <form method="post">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <div class="mb-3">
            <label class="form-label">Nom d’utilisateur</label>
            <input class="form-control" name="username" autocomplete="username" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Mot de passe</label>
            <input class="form-control" type="password" name="password" autocomplete="current-password" required>
          </div>
          <button class="btn btn-primary w-100">Se connecter</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>


