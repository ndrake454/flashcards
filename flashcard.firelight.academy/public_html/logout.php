<?php
// Start session
session_start();

// Include config
require_once 'config/config.php';
require_once 'includes/functions.php';

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
redirect(APP_URL . '/login.php');
?>