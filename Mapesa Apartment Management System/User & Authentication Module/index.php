<?php
require_once __DIR__ . '/config.php';

if (isLoggedIn()) {
    require_once __DIR__ . '/includes/auth.php';
    redirectToRoleHome();
}

header('Location: login.php');
exit;
