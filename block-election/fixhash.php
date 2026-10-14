<?php
// Fixes the wrong password hashes in the users table.
// Run once: http://localhost/E_VOTING/block-election/fixhash.php
// DELETE THIS FILE immediately after.

require_once __DIR__ . '/config/database.php';

$adminHash   = '$2y$10$soyaKwbGqlzy/Of2lMHeX.xfe.3gKKtuhnQhIsf4rhmGRLpa4WtCC';
$studentHash = '$2y$10$OE95fmMWlFM8lCWk12E36.Epry2yCAjN767KWNY.o8iWOVhH3w9kq';

db()->prepare("UPDATE users SET password_hash = ? WHERE student_id IN ('ADMIN001','ADV001')")
   ->execute([$adminHash]);

db()->prepare("UPDATE users SET password_hash = ? WHERE student_id IN ('STU001','STU002','STU003')")
   ->execute([$studentHash]);

echo "Done. <a href='/E_VOTING/block-election/login.php'>Go to login</a>. Delete this file now.";
