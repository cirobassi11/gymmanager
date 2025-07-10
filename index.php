<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

if (isLoggedIn()) {
    // Se l'utente è già loggato, reindirizza alla dashboard
    header('Location: dashboard.php');
    exit();
} else {
    // Se l'utente non è loggato, reindirizza alla pagina di login
    header('Location: login.php');
    exit();
}
?>