<?php
require_once __DIR__ . '/../../app/bootstrap.php';

$user = require_role($pdo, $config, 'DIRECTION');
$title = 'Agents de terrain';

$editId = (int)($_GET['edit'] ?? 0);
$agent = null;
if ($editId > 0) {
    $st = $pdo->prepare(
        'SELECT fa.*, u.username FROM field_agents fa LEFT JOIN users u ON u.id = fa.user_id WHERE fa.id = ?'
    );
    $st->execute([$editId]);
    $agent = $st->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('DELETE FROM field_agents WHERE id = ?')->execute([$id]);
            trigger_backup($pdo, $config, 'delete_field_agent');
            flash_set('main', 'Agent supprimé.', 'warning');
        }
        redirect(base_url($config, 'direction/agents.php'));
    }

    $nom = trim((string)($_POST['nom'] ?? ''));
    $prenom = trim((string)($_POST['prenom'] ?? ''));
    $genre = (string)($_POST['genre'] ?? 'M');
    $age = (int)($_POST['age'] ?? 0);
    $cni = trim((string)($_POST['cni'] ?? ''));
    $bureau = trim((string)($_POST['bureau_vote'] ?? 'Bureau principal'));
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $id = (int)($_POST['id'] ?? 0);

    if ($nom === '' || $prenom === '' || $cni === '' || $age <= 0) {
        flash_set('main', 'Veuillez remplir correctement tous les champs obligatoires.', 'warning');
        redirect(base_url($config, 'direction/agents.php' . ($id ? '?edit=' . $id : '')));
    }

    try {
        $pdo->beginTransaction();
        $profileRel = null;
        if (!empty($_FILES['profile_photo']['name'])) {
            $uploaded = save_uploaded_image($config, 'AGT_' . ($id ?: 'NEW'), $_FILES['profile_photo']);
            $profileRel = $uploaded['rel'] ?? null;
        }

        if ($id > 0) {
            if ($profileRel) {
                $st = $pdo->prepare(
                    'UPDATE field_agents SET nom=?, prenom=?, genre=?, age=?, cni=?, bureau_vote=?, profile_photo=?, updated_at=NOW() WHERE id=?'
                );
                $st->execute([$nom, $prenom, $genre, $age, $cni, $bureau, $profileRel, $id]);
            } else {
                $st = $pdo->prepare(
                    'UPDATE field_agents SET nom=?, prenom=?, genre=?, age=?, cni=?, bureau_vote=?, updated_at=NOW() WHERE id=?'
                );
                $st->execute([$nom, $prenom, $genre, $age, $cni, $bureau, $id]);
            }

            if ($username !== '' && $password !== '') {
                $st = $pdo->prepare('SELECT user_id FROM field_agents WHERE id = ?');
                $st->execute([$id]);
                $uid = (int)($st->fetch()['user_id'] ?? 0);
                if ($uid > 0) {
                    $pdo->prepare('UPDATE users SET username=?, password_hash=? WHERE id=?')
                        ->execute([$username, password_hash($password, PASSWORD_BCRYPT), $uid]);
                } else {
                    $pdo->prepare('INSERT INTO users(username, password_hash, role) VALUES(?,?,?)')
                        ->execute([$username, password_hash($password, PASSWORD_BCRYPT), 'AGENT']);
                    $uid = (int)$pdo->lastInsertId();
                    $pdo->prepare('UPDATE field_agents SET user_id=? WHERE id=?')->execute([$uid, $id]);
                }
            }

            $sig = setting($pdo, 'card_signature_path', 'assets/img/signature.png') ?? 'assets/img/signature.png';
            $st = $pdo->prepare('SELECT matricule FROM field_agents WHERE id = ?');
            $st->execute([$id]);
            $mat = (string)($st->fetch()['matricule'] ?? '');
            $pdo->prepare(
                'INSERT INTO agent_cards(field_agent_id, qr_code, lieu, signature_path) VALUES(?,?,?,?)
                 ON DUPLICATE KEY UPDATE qr_code=VALUES(qr_code), lieu=VALUES(lieu)'
            )->execute([$id, $mat, 'IUT-Fv Bandjoun', $sig]);

            $pdo->commit();
            trigger_backup($pdo, $config, 'update_field_agent');
            flash_set('main', 'Agent modifié.', 'success');
        } else {
            if ($username === '' || $password === '') {
                throw new RuntimeException('Identifiants de connexion requis pour un nouvel agent.');
            }
            $pdo->prepare('INSERT INTO users(username, password_hash, role) VALUES(?,?,?)')
                ->execute([$username, password_hash($password, PASSWORD_BCRYPT), 'AGENT']);
            $userId = (int)$pdo->lastInsertId();
            $matricule = gen_agent_matricule($pdo);

            $pdo->prepare(
                'INSERT INTO field_agents(user_id, matricule, nom, prenom, genre, age, cni, profile_photo, bureau_vote)
                 VALUES(?,?,?,?,?,?,?,?,?)'
            )->execute([$userId, $matricule, $nom, $prenom, $genre, $age, $cni, $profileRel, $bureau]);
            $newId = (int)$pdo->lastInsertId();

            $sig = setting($pdo, 'card_signature_path', 'assets/img/signature.png') ?? 'assets/img/signature.png';
            $pdo->prepare(
                'INSERT INTO agent_cards(field_agent_id, qr_code, lieu, signature_path) VALUES(?,?,?,?)'
            )->execute([$newId, $matricule, 'IUT-Fv Bandjoun', $sig]);

            $pdo->commit();
            trigger_backup($pdo, $config, 'create_field_agent');
            flash_set('main', 'Agent créé. Matricule : ' . $matricule, 'success');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash_set('main', 'Erreur : ' . $e->getMessage(), 'danger');
    }
    redirect(base_url($config, 'direction/agents.php'));
}

$st = $pdo->query(
    'SELECT fa.*, u.username FROM field_agents fa LEFT JOIN users u ON u.id = fa.user_id ORDER BY fa.id DESC'
);
$agents = $st->fetchAll();

require __DIR__ . '/../_layout_top.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Gestion des agents de terrain</h3>
  <a class="btn btn-outline-secondary" href="<?= e(base_url($config, 'direction/index.php')) ?>">Retour</a>
</div>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="card card-soft">
      <div class="card-body p-4">
        <h5 class="mb-3"><?= $agent ? 'Modifier l\'agent' : 'Nouvel agent' ?></h5>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="save">
          <?php if ($agent): ?>
            <input type="hidden" name="id" value="<?= (int)$agent['id'] ?>">
          <?php endif; ?>
          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label">Nom</label>
              <input class="form-control" name="nom" value="<?= e($agent['nom'] ?? '') ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Prénom</label>
              <input class="form-control" name="prenom" value="<?= e($agent['prenom'] ?? '') ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Genre</label>
              <select class="form-select" name="genre">
                <?php foreach (['M', 'F', 'AUTRE'] as $g): ?>
                  <option value="<?= $g ?>" <?= ($agent['genre'] ?? 'M') === $g ? 'selected' : '' ?>><?= $g ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Âge</label>
              <input class="form-control" type="number" min="1" name="age" value="<?= e((string)($agent['age'] ?? '')) ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">N° CNI</label>
              <input class="form-control" name="cni" value="<?= e($agent['cni'] ?? '') ?>" required>
            </div>
            <div class="col-12">
              <label class="form-label">Bureau de vote</label>
              <input class="form-control" name="bureau_vote" value="<?= e($agent['bureau_vote'] ?? 'Bureau principal') ?>">
            </div>
            <div class="col-12">
              <label class="form-label">Photo de profil</label>
              <input class="form-control" type="file" name="profile_photo" accept=".jpg,.jpeg,.png">
            </div>
            <div class="col-md-6">
              <label class="form-label">Login (pseudo)</label>
              <input class="form-control" name="username" value="<?= e($agent['username'] ?? '') ?>" <?= $agent ? '' : 'required' ?>>
            </div>
            <div class="col-md-6">
              <label class="form-label">Mot de passe<?= $agent ? ' (laisser vide pour conserver)' : '' ?></label>
              <input class="form-control" type="password" name="password" <?= $agent ? '' : 'required' ?>>
            </div>
          </div>
          <div class="mt-3 d-flex gap-2">
            <button class="btn btn-primary"><?= $agent ? 'Enregistrer' : 'Créer' ?></button>
            <?php if ($agent): ?>
              <a class="btn btn-outline-secondary" href="<?= e(base_url($config, 'direction/agents.php')) ?>">Annuler</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card card-soft">
      <div class="card-body p-4">
        <h5 class="mb-3">Liste des agents</h5>
        <?php if (!$agents): ?>
          <div class="text-muted">Aucun agent enregistré.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead>
                <tr>
                  <th>Matricule</th>
                  <th>Photo</th>
                  <th>Nom</th>
                  <th>Bureau</th>
                  <th>Login</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($agents as $a): ?>
                  <tr>
                    <th><?= e($a['matricule']) ?></th>
                    <td>
                      <?php if (!empty($a['profile_photo'])): ?>
                        <img src="<?= e(media_url($config, $a['profile_photo']) ?? '') ?>" alt="" class="rounded" width="36" height="36" style="object-fit:cover">
                      <?php else: ?>
                        <span class="text-muted small">—</span>
                      <?php endif; ?>
                    </td>
                    <td><?= e($a['nom'] . ' ' . $a['prenom']) ?></td>
                    <td class="small"><?= e($a['bureau_vote'] ?? '') ?></td>
                    <td><?= e($a['username'] ?? '—') ?></td>
                    <td class="text-end">
                      <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url($config, 'direction/agents.php?edit=' . (int)$a['id'])) ?>">Modifier</a>
                      <?php if (list_is_closed($pdo)): ?>
                        <a class="btn btn-sm btn-dark" href="<?= e(base_url($config, 'agent_card.php?matricule=' . urlencode($a['matricule']))) ?>">Carte</a>
                      <?php endif; ?>
                      <form method="post" class="d-inline" onsubmit="return confirm('Supprimer cet agent ?');">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger">Supprimer</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../_layout_bottom.php'; ?>
