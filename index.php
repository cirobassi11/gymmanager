<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

// Redirect in base allo stato di autenticazione
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
} else {
    header('Location: login.php');
    exit();
}
?>