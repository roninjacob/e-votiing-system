<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

require_login();

$user = current_user();

// Route to appropriate landing page based on role
if (in_array($user['role'], ['admin', 'adviser', 'committee'], true)) {
    redirect('/admin/dashboard.php');
} else {
    redirect('/voter/elections.php');
}
