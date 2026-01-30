<?php
/**
 * Ghost Kitchen Order Board - Admin Logout
 */

require_once __DIR__ . '/../includes/auth.php';

logout();
header('Location: /admin/login.php');
exit;
