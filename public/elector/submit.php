<?php
require_once __DIR__ . '/../../app/bootstrap.php';

$user = require_role($pdo, $config, 'ELECTOR');

$listClosed = setting($pdo, 'list_closed', '0') === '1';
if ($listClosed) {
    flash_set('main', 'La liste électorale est arrêtée. Soumissions fermées.', 'danger');
    redirect(base_url($config, 'elector/index.php'));
}

$st = $pdo->prepare(
    'SELECT en.id, en.dossier_code, en.status
     FROM enrollments en
     JOIN electors e ON e.id = en.elector_id
     WHERE e.user_id = ?
     ORDER BY en.id DESC
     LIMIT 1'
);
$st->execute([(int)$user['id']]);
$en = $st->fetch();
if (!$en) {
    flash_set('main', 'Aucun dossier trouvé.', 'warning');
    redirect(base_url($config, 'elector/index.php'));
}

if (in_array($en['status'], ['SUBMITTED','APPROVED','REJECTED'], true)) {
    flash_set('main', 'Votre demande a déjà été soumise / traitée.', 'info');
    redirect(base_url($config, 'elector/index.php'));
}

// Vérifie pièces minimales: CNI + ACTE_NAISSANCE
$st = $pdo->prepare('SELECT file_type, COUNT(*) c FROM attachments WHERE enrollment_id = ? GROUP BY file_type');
$st->execute([(int)$en['id']]);
$counts = [];
foreach ($st->fetchAll() as $r) {
    $counts[$r['file_type']] = (int)$r['c'];
}

if (($counts['CNI'] ?? 0) < 1 || ($counts['ACTE_NAISSANCE'] ?? 0) < 1) {
    flash_set('main', 'Veuillez fournir au moins: CNI + Acte de naissance avant soumission.', 'warning');
    redirect(base_url($config, 'elector/upload.php'));
}

$st = $pdo->prepare("UPDATE enrollments SET status='SUBMITTED', submitted_at=NOW() WHERE id=?");
$st->execute([(int)$en['id']]);
trigger_backup($pdo, $config, 'elector_submit');

flash_set('main', 'Demande soumise à la Direction.', 'success');
redirect(base_url($config, 'elector/index.php'));


