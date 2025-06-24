<?php
require_once 'config.php';

if (!isset($_SESSION['userID']) || !isset($_SESSION['role'])) {
    header('Location: login.php');
    exit();
}

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
    <link rel="stylesheet" href="dashboard.css">
</head>
<body>
    <div class="header">
        <h1>Benvenuto, <?= htmlspecialchars($user['firstName'] . ' ' . $user['lastName']) ?>!</h1>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <div class="main-content">
        <?php if ($role === 'admin'): ?>
            <div class="alert alert-primary">
                Sei un <strong>Amministratore</strong>. Puoi gestire utenti, corsi, abbonamenti, attrezzature e visualizzare statistiche.
            </div>
            <div class="d-grid gap-2">
                <a href="gestione_utenti.php" class="btn btn-primary">Gestione Utenti</a>
                <a href="gestione_corsi.php" class="btn btn-primary">Gestione Corsi</a>
                <a href="gestione_abbonamenti.php" class="btn btn-primary">Gestione Abbonamenti</a>
                <a href="gestione_attrezzature.php" class="btn btn-primary">Gestione Attrezzature</a>
                <a href="statistiche_corsi.php" class="btn btn-primary">Statistiche Corsi</a>
                <a href="statistiche_clienti.php" class="btn btn-primary">Statistiche Clienti</a>
                <a href="statistiche_attrezzature.php" class="btn btn-primary">Statistiche Attrezzature</a>
                <a href="pagamenti.php" class="btn btn-primary">Storico Pagamenti</a>
            </div>

        <?php elseif ($role === 'trainer'): ?>
            <div class="alert alert-success">
                Sei un <strong>Trainer</strong>. Specializzazione: <?= htmlspecialchars($user['specialization']) ?> <br>
                Disponibilità: <?= htmlspecialchars($user['availability']) ?>
            </div>
            <div class="d-grid gap-2">
                <a href="lezioni_trainer.php" class="btn btn-success">Le mie Lezioni</a>
                <a href="clienti_trainer.php" class="btn btn-success">Clienti Seguiti</a>
                <a href="programmi_allenamento.php" class="btn btn-success">Programmi di Allenamento</a>
                <a href="gestione_disponibilita.php" class="btn btn-success">Gestione Disponibilità</a>
            </div>

        <?php elseif ($role === 'customer'): ?>
            <div class="alert alert-info">
                Sei un <strong>Cliente</strong>. Puoi gestire il tuo abbonamento e iscriverti ai corsi.
            </div>
            <div class="d-grid gap-2">
                <a href="acquista_abbonamento.php" class="btn btn-info">Acquista Abbonamento</a>
                <a href="stato_abbonamento.php" class="btn btn-info">Stato Abbonamento</a>
                <a href="storico_abbonamenti.php" class="btn btn-info">Storico Abbonamenti</a>
                <a href="iscrizione_corsi.php" class="btn btn-info">Iscrizione a Corsi</a>
                <a href="allenamento_personalizzato.php" class="btn btn-info">Programma Allenamento</a>
                <a href="progressi.php" class="btn btn-info">I Miei Progressi</a>
                <a href="feedback.php" class="btn btn-info">Lascia un Feedback</a>
                <a href="promozioni.php" class="btn btn-info">Promozioni Attive</a>
            </div>

        <?php else: ?>
            <div class="alert alert-warning">
                Ruolo non riconosciuto.
            </div>
        <?php endif; ?>
    </div>
</body>
</html>