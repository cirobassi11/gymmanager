<?php
// Configurazione database per Railway
// Railway fornisce automaticamente le variabili d'ambiente

// Configurazione database
$db_config = [
    'host' => $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?? 'localhost',
    'port' => $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?? '3306',
    'database' => $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?? 'gymdb',
    'username' => $_ENV['DB_USER'] ?? getenv('DB_USER') ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?? '12341234',
    'charset' => 'utf8mb4'
];

// Configurazioni generali dell'applicazione
define('APP_NAME', 'Gestionale Palestra');
define('APP_VERSION', '1.0.0');
define('BASE_URL', $_ENV['RAILWAY_STATIC_URL'] ?? 'http://localhost');

// Configurazioni di sicurezza
define('SESSION_TIMEOUT', 3600);
define('PASSWORD_MIN_LENGTH', 8);

// Fuso orario
date_default_timezone_set('Europe/Rome');

// Connessione al database
function getDbConnection() {
    global $db_config;
    
    try {
        $dsn = "mysql:host={$db_config['host']};port={$db_config['port']};dbname={$db_config['database']};charset={$db_config['charset']}";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ];
        $pdo = new PDO($dsn, $db_config['username'], $db_config['password'], $options);
        if (DEBUG_MODE) {
            echo "<!-- Connessione database riuscita -->\n";
        }
        return $pdo;
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            die("Errore connessione database: " . $e->getMessage());
        } else {
            die("Errore di connessione al database. Riprova più tardi.");
        }
    }
}

// Avvio sessione
session_start();

// Controllo sessione scaduta
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
    session_unset();
    session_destroy();
    session_start();
}
$_SESSION['last_activity'] = time();

// Funzioni di utilità per sicurezza
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

// Configurazione upload file (per foto profilo membri, etc.)
define('UPLOAD_PATH', 'uploads/');
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);

// Headers di sicurezza
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
?>