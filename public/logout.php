<?php
#logout, POST only so a link cannot sign a player out
declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';

auth_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    auth_logout();
}
redirect('index.php');
