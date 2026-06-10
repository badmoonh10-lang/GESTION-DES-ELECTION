<?php
require_once __DIR__ . '/../../app/bootstrap.php';

$user = require_role($pdo, $config, 'AGENT');
$title = 'Synchronisation vote physique';

if (!elections_en_cours($pdo)) {
    flash_set('main', 'Disponible uniquement lorsque les élections sont en cours et les inscriptions arrêtées.', 'warning');
    redirect(base_url($config, 'agent/index.php'));
}

$searchResult = null;
$myVote = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'search') {
        $query = trim((string)($_POST['query'] ?? ''));
        if ($query !== '') {
            $st = $pdo->prepare(
                "SELECT e.id AS elector_id, e.nom, e.prenom, e.cni, en.dossier_code, en.status
                 FROM electors e
                 JOIN enrollments en ON en.elector_id = e.id
                 WHERE en.status = 'APPROVED' AND (en.dossier_code = ? OR e.cni = ?)
                 ORDER BY en.id DESC LIMIT 1"
            );
            $st->execute([$query, $query]);
            $searchResult = $st->fetch() ?: null;
            if ($searchResult) {
                $st = $pdo->prepare('SELECT v.id, v.vote_type, c.party_name, ee.nom, ee.prenom
                                     FROM votes v
                                     JOIN candidates c ON c.id = v.candidate_id
                                     JOIN electors ee ON ee.id = c.elector_id
                                     WHERE v.elector_id = ?');
                $st->execute([(int)$searchResult['elector_id']]);
                $myVote = $st->fetch() ?: null;
            }
        }
    }

    if ($action === 'record_vote') {
        $electorId = (int)($_POST['elector_id'] ?? 0);
        $candidateId = (int)($_POST['candidate_id'] ?? 0);

        $st = $pdo->prepare(
            "SELECT e.id FROM electors e
             JOIN enrollments en ON en.elector_id = e.id
             WHERE e.id = ? AND en.status = 'APPROVED' LIMIT 1"
        );
        $st->execute([$electorId]);
        if (!$st->fetch()) {
            flash_set('main', 'Électeur non trouvé ou non approuvé.', 'danger');
            redirect(base_url($config, 'agent/vote_sync.php'));
        }

        $st = $pdo->prepare('SELECT id FROM votes WHERE elector_id = ?');
        $st->execute([$electorId]);
        if ($st->fetch()) {
            flash_set('main', 'Cet électeur a déjà voté.', 'warning');
            redirect(base_url($config, 'agent/vote_sync.php'));
        }

        $st = $pdo->prepare('SELECT id FROM candidates WHERE id = ?');
        $st->execute([$candidateId]);
        if (!$st->fetch()) {
            flash_set('main', 'Candidat invalide.', 'danger');
            redirect(base_url($config, 'agent/vote_sync.php'));
        }

        try {
            $st = $pdo->prepare(
                'INSERT INTO votes(elector_id, candidate_id, vote_type, recorded_by_user_id) VALUES(?,?,?,?)'
            );
            $st->execute([$electorId, $candidateId, 'PHYSICAL', (int)$user['id']]);
            trigger_backup($pdo, $config, 'physical_vote');
            flash_set('main', 'Vote physique enregistré et synchronisé.', 'success');
        } catch (Throwable $e) {
            flash_set('main', 'Impossible d\'enregistrer le vote.', 'danger');
        }
        redirect(base_url($config, 'agent/vote_sync.php'));
    }
}

$st = $pdo->query(
    "SELECT c.id, c.party_name, e.nom, e.prenom
     FROM candidates c JOIN electors e ON e.id = c.elector_id ORDER BY c.id"
);
$candidates = $st->fetchAll();

require __DIR__ . '/../_layout_top.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Vote physique — synchronisation</h3>
  <a class="btn btn-outline-secondary" href="<?= e(base_url($config, 'agent/index.php')) ?>">Retour</a>
</div>

<div class="alert alert-info">
  Recherchez un électeur par numéro de dossier ou CNI, puis cochez son vote au bureau de vote pour synchroniser avec le système.
</div>

<div class="card card-soft mb-4">
  <div class="card-body p-4">
    <h5 class="mb-3">Rechercher un électeur</h5>
    <form method="post" class="row g-2">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="search">
      <div class="col-md-8">
        <input class="form-control" name="query" placeholder="Dossier (DOS-000001) ou N° CNI" required
               value="<?= e((string)($_POST['query'] ?? '')) ?>">
      </div>
      <div class="col-md-4">
        <button class="btn btn-primary w-100">Rechercher</button>
      </div>
    </form>
  </div>
</div>

<?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'search'): ?>
  <?php if (!$searchResult): ?>
    <div class="alert alert-warning">Aucun électeur approuvé trouvé.</div>
  <?php else: ?>
    <div class="card card-soft">
      <div class="card-body p-4">
        <h5 class="mb-3"><?= e($searchResult['nom'] . ' ' . $searchResult['prenom']) ?></h5>
        <p class="mb-1"><strong>Dossier :</strong> <?= e($searchResult['dossier_code']) ?></p>
        <p class="mb-3"><strong>CNI :</strong> <?= e($searchResult['cni']) ?></p>

        <?php if ($myVote): ?>
          <div class="alert alert-success mb-0">
            <strong>Déjà voté</strong> (<?= e($myVote['vote_type']) ?>) —
            <?= e($myVote['nom'] . ' ' . $myVote['prenom'] . ' — ' . $myVote['party_name']) ?>
          </div>
        <?php else: ?>
          <form method="post">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="record_vote">
            <input type="hidden" name="elector_id" value="<?= (int)$searchResult['elector_id'] ?>">
            <h6 class="mb-2">Enregistrer le vote physique</h6>
            <?php foreach ($candidates as $c): ?>
              <div class="form-check mb-2">
                <input class="form-check-input" type="radio" name="candidate_id" value="<?= (int)$c['id'] ?>" id="c<?= (int)$c['id'] ?>" required>
                <label class="form-check-label" for="c<?= (int)$c['id'] ?>">
                  <?= e($c['nom'] . ' ' . $c['prenom'] . ' — ' . $c['party_name']) ?>
                </label>
              </div>
            <?php endforeach; ?>
            <button class="btn btn-success mt-2">Cocher / synchroniser le vote</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/../_layout_bottom.php'; ?>
