<?php
// Run this once in browser: http://localhost/E_VOTING/block-election/genhash.php
// Then delete this file immediately after

$passwords = [
    'admin123'   => password_hash('admin123',   PASSWORD_BCRYPT),
    'student123' => password_hash('student123', PASSWORD_BCRYPT),
    'password'   => password_hash('password',   PASSWORD_BCRYPT),
];

foreach ($passwords as $plain => $hash) {
    echo "<b>{$plain}</b>: <code>{$hash}</code><br>";
    echo "verify: " . (password_verify($plain, $hash) ? 'OK' : 'FAIL') . "<br><br>";
}

// Also test the existing seed hash
$seedHash = '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm';
echo "<hr><b>Seed hash test:</b><br>";
echo "'password' vs seed: "   . (password_verify('password',   $seedHash) ? 'MATCH' : 'NO MATCH') . "<br>";
echo "'admin123' vs seed: "   . (password_verify('admin123',   $seedHash) ? 'MATCH' : 'NO MATCH') . "<br>";
echo "'student123' vs seed: " . (password_verify('student123', $seedHash) ? 'MATCH' : 'NO MATCH') . "<br>";
