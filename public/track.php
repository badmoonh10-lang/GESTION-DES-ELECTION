<?php
require_once __DIR__ . '/../app/bootstrap.php';

$user = current_user($pdo);
$title = 'Suivi de demande';

$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $code = trim((string)($_POST['dossier_code'] ?? ''));
    if ($code !== '') {
        $st = $pdo->prepare(
            'SELECT en.dossier_code, en.status, en.direction_comment, en.created_at, en.submitted_at, en.reviewed_at,
                    el.nom, el.prenom
             FROM enrollments en
             JOIN electors el ON el.id = en.elector_id
             WHERE en.dossier_code = ?'
        );
        $st->execute([$code]);
        $result = $st->fetch() ?: ['_not_found' => true];
    }
}

require __DIR__ . '/_layout_top.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="card card-soft">
      <div class="card-body p-4">
        <h4 class="mb-3">Suivre une demande</h4>
        <form method="post" class="row g-2 align-items-end">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <div class="col-md-8">
            <label class="form-label">Numéro de dossier</label>
            <input class="form-control" name="dossier_code" placeholder="Ex: DOS-000001" required>
          </div>
          <div class="col-md-4">
            <button class="btn btn-primary w-100">Rechercher</button>
          </div>
        </form>

        <?php if (is_array($result)): ?>
          <hr>
          <?php if (!empty($result['_not_found'])): ?>
            <div class="alert alert-warning mb-0">Aucune demande trouvée.</div>
          <?php else: ?>
            <div class="row g-3">
              <div class="col-md-6">
                <div class="border rounded p-3 bg-white">
                  <div class="text-muted small">Électeur</div>
                  <div class="fw-semibold"><?= e($result['nom'] . ' ' . $result['prenom']) ?></div>
                  <div class="text-muted small mt-2">Dossier</div>
                  <div class="fw-semibold"><?= e($result['dossier_code']) ?></div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="border rounded p-3 bg-white">
                  <div class="text-muted small">Statut</div>
                  <div class="fw-semibold"><?= e($result['status']) ?></div>
                  <?php if (!empty($result['direction_comment'])): ?>
                    <div class="text-muted small mt-2">Commentaire Direction</div>
                    <div><?= e($result['direction_comment']) ?></div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>


