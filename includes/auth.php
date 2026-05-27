<?php
require_once __DIR__ . '/functions.php';

if (!is_logged_in()) {
    redirect_to('login.php');
}

$currentUser = current_user();

