<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('APP_BASE', '../');
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in() || (($_SESSION['panel'] ?? '') !== 'federation')) {
    redirect_to('../login.php');
}

$currentUser = current_user();
