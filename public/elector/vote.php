<?php
require_once __DIR__ . '/../../app/bootstrap.php';

$user = require_role($pdo, $config, 'ELECTOR');
$title = 'Vote';

$votingOpen = setting($pdo, 'voting_open', '0') === '1';

// Récupère l'électeur courant + dossier approuvé
$st = $pdo->prepare(
    "SELECT e.id AS elector_id, e.nom, e.prenom, e.age, e.genre, e.cni,
            en.dossier_code, en.status
     FROM electors e
     JOIN enrollments en ON en.elector_id = e.id
     WHERE e.user_id = ?
     ORDER BY en.id DESC
     LIMIT 1"
);
$st->execute([(int)$user['id']]);
$row = $st->fetch();

if (!$row || $row['status'] !== 'APPROVED') {
    flash_set('main', 'Vous devez avoir un dossier approuvé pour voter.', 'danger');
    redirect(base_url($config, 'elector/index.php'));
}

// Vérifie si l'électeur a déjà voté
$st = $pdo->prepare("SELECT v.id, v.candidate_id, c.party_name, ee.nom, ee.prenom
                     FROM votes v
                     JOIN candidates c ON c.id = v.candidate_id
                     JOIN electors ee ON ee.id = c.elector_id
                     WHERE v.elector_id = ?");
$st->execute([(int)$row['elector_id']]);
$myVote = $st->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();

    if (!$votingOpen) {
        flash_set('main', 'Le vote n\'est pas ouvert actuellement.', 'danger');
        redirect(base_url($config, 'elector/vote.php'));
    }

    if ($myVote) {
        flash_set('main', 'Vous avez déjà voté. Un seul vote est autorisé.', 'warning');
        redirect(base_url($config, 'elector/vote.php'));
    }

    $candidateId = (int)($_POST['candidate_id'] ?? 0);
    if ($candidateId <= 0) {
        flash_set('main', 'Candidat invalide.', 'danger');
        redirect(base_url($config, 'elector/vote.php'));
    }

    // Vérifie que le candidat existe
    $st = $pdo->prepare("SELECT c.id FROM candidates c WHERE c.id = ?");
    $st->execute([$candidateId]);
    $cand = $st->fetch();
    if (!$cand) {
        flash_set('main', 'Candidat introuvable.', 'danger');
        redirect(base_url($config, 'elector/vote.php'));
    }

    try {
        $st = $pdo->prepare("INSERT INTO votes(elector_id, candidate_id, vote_type, recorded_by_user_id) VALUES(?, ?, 'ONLINE', ?)");
        $st->execute([(int)$row['elector_id'], $candidateId, (int)$user['id']]);
        trigger_backup($pdo, $config, 'online_vote');
        flash_set('main', 'Votre vote a été enregistré.', 'success');
    } catch (Throwable $e) {
        flash_set('main', 'Impossible d\'enregistrer le vote (avez-vous déjà voté ?).', 'danger');
    }

    redirect(base_url($config, 'elector/vote.php'));
}

// Liste des candidats
$st = $pdo->query(
    "SELECT c.id, c.party_name, c.program_text,
            e.nom, e.prenom
     FROM candidates c
     JOIN electors e ON e.id = c.elector_id
     ORDER BY c.id ASC"
);
$candidates = $st->fetchAll();

require __DIR__ . '/../_layout_top.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Vote</h3>
  <a class="btn btn-outline-secondary" href="<?= e(base_url($config, 'elector/index.php')) ?>">Retour</a>
</div>

<?php if (!$votingOpen): ?>
  <div class="alert alert-warning">
    Le vote n'est pas encore ouvert. Revenez le jour du vote.
  </div>
<?php endif; ?>

<?php if ($myVote): ?>
  <div class="card card-soft mb-4">
    <div class="card-body p-4">
      <h5 class="mb-3">Votre vote</h5>
      <p class="mb-1"><strong>Candidat:</strong> <?= e($myVote['nom'] . ' ' . $myVote['prenom']) ?></p>
      <p class="mb-1"><strong>Parti:</strong> <?= e($myVote['party_name']) ?></p>
      <p class="text-muted mb-0">Vous ne pouvez voter qu'une seule fois.</p>
    </div>
  </div>
<?php endif; ?>

<div class="card card-soft">
  <div class="card-body p-4">
    <h5 class="mb-3">Liste des candidats</h5>
    <?php if (!$candidates): ?>
      <div class="text-muted">Aucun candidat enregistré.</div>
    <?php else: ?>
      <?php if ($votingOpen && !$myVote): ?>
        <form method="post">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <div class="table-responsive mb-3">
            <table class="table table-sm align-middle">
              <thead>
                <tr>
                  <th>Candidat</th>
                  <th>Parti</th>
                  <th>Programme</th>
                  <th class="text-end">Choix</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($candidates as $c): ?>
                  <tr>
                    <td><?= e($c['nom'] . ' ' . $c['prenom']) ?></td>
                    <td><?= e($c['party_name']) ?></td>
                    <td class="small text-muted"><?= nl2br(e($c['program_text'] ?? '')) ?></td>
                    <td class="text-end">
                      <div class="form-check d-inline-block">
                        <input class="form-check-input" type="radio" name="candidate_id" value="<?= (int)$c['id'] ?>" required>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <button class="btn btn-success">Voter</button>
        </form>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr>
                <th>Candidat</th>
                <th>Parti</th>
                <th>Programme</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($candidates as $c): ?>
                <tr>
                  <td><?= e($c['nom'] . ' ' . $c['prenom']) ?></td>
                  <td><?= e($c['party_name']) ?></td>
                  <td class="small text-muted"><?= nl2br(e($c['program_text'] ?? '')) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../_layout_bottom.php'; ?>


