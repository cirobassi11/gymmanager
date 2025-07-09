<?php
ob_start();

// Avvio sessione
session_start();

// Dati di connessione al database
$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = '12341234';
$db_name = 'gymdb';

// Connessione MySQLi
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Verifica connessione
if ($conn->connect_error) {
    die("Connessione al database fallita: " . $conn->connect_error);
}

// Funzione per criptare la password
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Funzione per verificare la password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Funzione per verificare se l'utente è loggato
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Funzione per ottenere l'ID dell'utente loggato
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}
?>