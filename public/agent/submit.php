<?php
require_once __DIR__ . '/../../app/bootstrap.php';

$user = require_role($pdo, $config, 'AGENT');

$listClosed = setting($pdo, 'list_closed', '0') === '1';
if ($listClosed) {
    flash_set('main', 'La liste électorale est arrêtée. Soumissions fermées.', 'danger');
    redirect(base_url($config, 'agent/list.php'));
}

$dossier = trim((string)($_GET['dossier'] ?? ''));
if ($dossier === '') {
    redirect(base_url($config, 'agent/list.php'));
}

$st = $pdo->prepare('SELECT id, status FROM enrollments WHERE dossier_code=? AND created_by_user_id=?');
$st->execute([$dossier, (int)$user['id']]);
$en = $st->fetch();
if (!$en) {
    flash_set('main', 'Dossier introuvable.', 'warning');
    redirect(base_url($config, 'agent/list.php'));
}

if ($en['status'] !== 'PRE_ENROLLED') {
    flash_set('main', 'Dossier déjà soumis / traité.', 'info');
    redirect(base_url($config, 'agent/list.php'));
}

$st = $pdo->prepare("UPDATE enrollments SET status='SUBMITTED', submitted_at=NOW() WHERE id=?");
$st->execute([(int)$en['id']]);
trigger_backup($pdo, $config, 'agent_submit');

flash_set('main', 'Dossier soumis à la Direction.', 'success');
redirect(base_url($config, 'agent/list.php'));


