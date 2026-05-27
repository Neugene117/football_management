<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('APP_BASE', '../');
require_once __DIR__ . '/../includes/functions.php';
if (!empty($_SESSION['user'])) {
    log_action('team_logout', 'auth', 'users', $_SESSION['user']['id']);
}
$_SESSION = [];
session_destroy();
redirect_to('../login.php');
