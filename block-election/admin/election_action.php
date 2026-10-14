<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/services/BlockElectionService.php';

require_role(['admin', 'adviser', 'committee']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/elections.php');
}

csrf_check();

$svc    = new BlockElectionService();
$action = $_POST['action'] ?? '';
$user   = current_user();

try {
    switch ($action) {
        case 'create':
            $id = $svc->create([
                'block_id'    => (int) ($_POST['block_id'] ?? 0),
                'title'       => trim($_POST['title'] ?? ''),
                'description' => trim($_POST['description'] ?? ''),
                'start_at'    => $_POST['start_at'] ?? '',
                'end_at'      => $_POST['end_at'] ?? '',
            ], $user['id']);
            flash('success', 'Election created successfully.');
            redirect('/admin/positions.php?election_id=' . $id);

        case 'update':
            $svc->update((int) ($_POST['id'] ?? 0), [
                'block_id'    => (int) ($_POST['block_id'] ?? 0),
                'title'       => trim($_POST['title'] ?? ''),
                'description' => trim($_POST['description'] ?? ''),
                'start_at'    => $_POST['start_at'] ?? '',
                'end_at'      => $_POST['end_at'] ?? '',
            ]);
            flash('success', 'Election updated.');
            redirect('/admin/elections.php');

        case 'delete':
            $adminPassword = $_POST['admin_password'] ?? '';
            $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$user['id']]);
            $row = $stmt->fetch();
            if (!$row || !password_verify($adminPassword, $row['password_hash'])) {
                flash('danger', 'Incorrect password. Election was not deleted.');
                redirect('/admin/elections.php');
            }
            $svc->delete((int) ($_POST['id'] ?? 0));
            flash('success', 'Election deleted.');
            redirect('/admin/elections.php');

        case 'transition':
            $next = $svc->transition((int) ($_POST['id'] ?? 0), $user['id']);
            flash('success', 'Election status advanced to: ' . ucfirst($next));
            redirect('/admin/elections.php');

        default:
            flash('danger', 'Unknown action.');
            redirect('/admin/elections.php');
    }
} catch (RuntimeException $e) {
    flash('danger', $e->getMessage());
    redirect('/admin/elections.php');
}
