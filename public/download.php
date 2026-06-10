<?php
require_once __DIR__ . '/../app/bootstrap.php';

$user = current_user($pdo);
$title = 'Télécharger carte';

$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $dossier = trim((string)($_POST['dossier_code'] ?? ''));
    $cni = trim((string)($_POST['cni'] ?? ''));
    if ($dossier !== '' && $cni !== '') {
        // on redirige vers card.php en passant le cni (mode public)
        redirect(base_url($config, 'card.php?dossier=' . urlencode($dossier) . '&cni=' . urlencode($cni)));
    } else {
        $result = ['error' => 'Champs requis'];
    }
}

require __DIR__ . '/_layout_top.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="card card-soft">
      <div class="card-body p-4">
        <h4 class="mb-3">Télécharger sa carte</h4>
        <p class="text-muted">Saisis le numéro de dossier + le numéro de CNI. (Le PDF n’est disponible qu’après approbation.)</p>

        <form method="post" class="row g-2 align-items-end">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <div class="col-md-6">
            <label class="form-label">Numéro de dossier</label>
            <input class="form-control" name="dossier_code" placeholder="DOS-000001" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Numéro CNI</label>
            <input class="form-control" name="cni" required>
          </div>
          <div class="col-12">
            <button class="btn btn-dark w-100">Ouvrir le PDF</button>
          </div>
        </form>

        <?php if (is_array($result) && !empty($result['error'])): ?>
          <div class="alert alert-warning mt-3 mb-0"><?= e($result['error']) ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>


