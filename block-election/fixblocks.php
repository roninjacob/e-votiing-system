<?php
// Run once: http://localhost/E_VOTING/block-election/fixblocks.php
// Removes BSCS, ensures all blocks are BSIT. DELETE after running.
require_once __DIR__ . '/config/database.php';

db()->exec("UPDATE blocks SET block_name = 'BSIT' WHERE block_name = 'BSCS'");
echo "Done — all blocks are now BSIT. <a href='/E_VOTING/block-election/login.php'>Login</a>. Delete this file.";
