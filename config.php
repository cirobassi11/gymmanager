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

// === COSTANTI GENERALI ===
define('APP_NAME', 'Gestionale Palestra');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/gymmanager');
define('UPLOAD_PATH', __DIR__ . '/uploads/');
define('MAX_FILE_SIZE', 2 * 1024 * 1024);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);
define('SESSION_TIMEOUT', 3600);

// === TIMEZONE ===
date_default_timezone_set('Europe/Rome');

// === SICUREZZA: headers HTTP ===
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// === GESTIONE SESSIONE (timeout) ===
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
    session_unset();
    session_destroy();
    session_start();
}
$_SESSION['last_activity'] = time();

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