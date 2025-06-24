<?php
require_once 'config.php';

// Redirect in base allo stato di autenticazione
if (isLoggedIn()) {
    // Utente autenticato → vai alla dashboard
    header('Location: dashboard.php');
    exit();
} else {
    // Utente non autenticato → vai al login
    header('Location: login.php');
    exit();
}
?>