<?php
require_once 'config.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Verifica che l'utente sia loggato
if (!isset($_SESSION['userID']) || !isset($_SESSION['role'])) {
    header('Location: login.php');
    exit();
}

// Recupera i dati dell'utente dal DB
$user_id = $_SESSION['userID'];
$role = $_SESSION['role'];

$sql = "SELECT firstName, lastName, role, specialization, availability FROM USER WHERE userID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo "Utente non trovato.";
    exit();
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <h1 class="mb-4">Benvenuto, <?= htmlspecialchars($user['firstName'] . ' ' . $user['lastName']) ?>!</h1>

        <?php if ($role === 'admin'): ?>
            <div class="alert alert-primary">
                Sei un <strong>Amministratore</strong>. Qui puoi gestire utenti, corsi, e visualizzare report.
            </div>
            <ul>
                <li><a href="#">Gestione utenti</a></li>
                <li><a href="#">Visualizza statistiche</a></li>
                <li><a href="#">Impostazioni di sistema</a></li>
            </ul>

        <?php elseif ($role === 'trainer'): ?>
            <div class="alert alert-success">
                Sei un <strong>Trainer</strong>. Specializzazioni: <?= htmlspecialchars($user['specialization']) ?>.<br>
                Disponibilità: <?= htmlspecialchars($user['availability']) ?>
            </div>
            <ul>
                <li><a href="#">Visualizza lezioni</a></li>
                <li><a href="#">Gestisci clienti</a></li>
            </ul>

        <?php elseif ($role === 'customer'): ?>
            <div class="alert alert-info">
                Sei un <strong>Cliente</strong>. Puoi vedere i tuoi allenamenti e prenotazioni.
            </div>
            <ul>
                <li><a href="#">Prenota una lezione</a></li>
                <li><a href="#">Storico allenamenti</a></li>
            </ul>

        <?php else: ?>
            <div class="alert alert-warning">
                Ruolo non riconosciuto.
            </div>
        <?php endif; ?>

        <a href="logout.php" class="btn btn-secondary mt-4">Logout</a>
    </div>
</body>
</html>