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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="mb-0">Benvenuto, <?= htmlspecialchars($user['firstName'] . ' ' . $user['lastName']) ?>!</h2>
                    <a href="logout.php" class="btn btn-outline-secondary">Logout</a>
                </div>

                <?php if ($role === 'admin'): ?>
                    <div class="alert alert-primary rounded-3">
                        Sei un <strong>Amministratore</strong>. Puoi gestire utenti, corsi, abbonamenti, attrezzature e visualizzare statistiche e feedback.
                    </div>
                    <div class="row row-cols-1 row-cols-md-2 g-3">
                        <div class="col"><a href="user_management.php" class="btn btn-primary w-100">Gestione Utenti</a></div>
                        <div class="col"><a href="course_management.php" class="btn btn-primary w-100">Gestione Corsi</a></div>
                        <div class="col"><a href="subscription_management.php" class="btn btn-primary w-100">Gestione Abbonamenti e Promozioni</a></div>
                        <div class="col"><a href="equipment_management.php" class="btn btn-primary w-100">Gestione Attrezzature</a></div>
                        <div class="col"><a href="feedback_view.php" class="btn btn-primary w-100">Visualizza Feedback</a></div>
                    </div>

                <?php elseif ($role === 'trainer'): ?>
                    <div class="alert alert-success rounded-3">
                        Sei un <strong>Trainer</strong>. Specializzazione: <?= htmlspecialchars($user['specialization']) ?><br>
                        Disponibilità: <?= htmlspecialchars($user['availability']) ?>
                    </div>
                    <div class="row row-cols-1 row-cols-md-2 g-3">
                        <div class="col"><a href="trainer_courses.php" class="btn btn-success w-100">I tuoi corsi</a></div>
                        <div class="col"><a href="trainer_customers.php" class="btn btn-success w-100">Clienti seguiti</a></div>
                        <div class="col"><a href="training_schedule.php" class="btn btn-success w-100">Programmi di Allenamento</a></div>
                        <div class="col"><a href="trainer_availability.php" class="btn btn-success w-100">Gestione Disponibilità</a></div>
                    </div>

                <?php elseif ($role === 'customer'): ?>
                    <div class="alert alert-info rounded-3">
                        Sei un <strong>Cliente</strong>. Puoi gestire il tuo abbonamento, iscriverti ai corsi e seguire i tuoi progressi.
                    </div>
                    <div class="row row-cols-1 row-cols-md-2 g-3">
                        <div class="col"><a href="customer_subscription.php" class="btn btn-info w-100">Gestione Abbonamenti</a></div>
                        <div class="col"><a href="customer_course_registration.php" class="btn btn-info w-100">Iscrizione a Corsi</a></div>
                        <div class="col"><a href="customer_training_schedule.php" class="btn btn-info w-100">Programma Allenamento</a></div>
                        <div class="col"><a href="customer_progress_report.php" class="btn btn-info w-100">I Miei Progressi</a></div>
                        <div class="col"><a href="customer_feedback.php" class="btn btn-info w-100">Lascia un Feedback</a></div>
                    </div>

                <?php else: ?>
                    <div class="alert alert-warning">
                        Ruolo non riconosciuto.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>