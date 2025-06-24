<?php
// Inizio output buffering per evitare errori "headers already sent"
ob_start();

// Avvio sessione
session_start();

// === CONFIGURAZIONE DATABASE (locale con MySQL / XAMPP) ===
$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = '12341234'; // Di default XAMPP non imposta password a root
$db_name = 'gymdb';

// Connessione MySQLi
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Verifica connessione
if ($conn->connect_error) {
    die("Connessione al database fallita: " . $conn->connect_error);
}

// === FUNZIONI DI AUTENTICAZIONE ===
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}
?>