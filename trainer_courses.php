<?php
require_once 'config.php';
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Controllo accesso trainer
if (!isset($_SESSION['userID'], $_SESSION['role']) || $_SESSION['role'] !== 'trainer') {
    header('Location: login.php');
    exit();
}

$trainerID = $_SESSION['userID'];

// Recupera i corsi assegnati al trainer
$stmt = $conn->prepare("
    SELECT c.courseID, c.name, c.description, c.price, c.maxParticipants, c.startDate, c.finishDate,
           COUNT(e.customerID) as enrolled_count
    FROM COURSE c
    JOIN teaching t ON c.courseID = t.courseID
    LEFT JOIN enrollment e ON c.courseID = e.courseID
    WHERE t.trainerID = ?
    GROUP BY c.courseID, c.name, c.description, c.price, c.maxParticipants, c.startDate, c.finishDate
    ORDER BY c.startDate DESC
");
$stmt->bind_param('i', $trainerID);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Statistiche trainer
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM teaching WHERE trainerID = ?");
$stmt->bind_param('i', $trainerID);
$stmt->execute();
$totalCourses = $stmt->get_result()->fetch_assoc()['total'];

$stmt = $conn->prepare("
    SELECT COUNT(*) as active 
    FROM COURSE c
    JOIN teaching t ON c.courseID = t.courseID
    WHERE t.trainerID = ? AND c.startDate <= date('now') AND c.finishDate >= date('now')
");
$stmt->bind_param('i', $trainerID);
$stmt->execute();
$activeCourses = $stmt->get_result()->fetch_assoc()['active'];

$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT e.customerID) as total_students
    FROM enrollment e
    JOIN teaching t ON e.courseID = t.courseID
    WHERE t.trainerID = ?
");
$stmt->bind_param('i', $trainerID);
$stmt->execute();
$totalStudents = $stmt->get_result()->fetch_assoc()['total_students'];

// Informazioni trainer
$stmt = $conn->prepare("SELECT firstName, lastName, specialization FROM USER WHERE userID = ?");
$stmt->bind_param('i', $trainerID);
$stmt->execute();
$trainerInfo = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>I Tuoi Corsi</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
</head>
<body class="bg-light">
<div class="container py-5">
    <!-- Header -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2>I Tuoi Corsi</h2>
                </div>
                <a href="dashboard.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i> Torna alla Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Statistiche -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <h4>Statistiche Corsi</h4>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="card text-white h-100" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                        <div class="card-body text-center d-flex flex-column justify-content-center">
                            <h3><?= $totalCourses ?></h3>
                            <p class="mb-0">Corsi Assegnati</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white h-100" style="background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);">
                        <div class="card-body text-center d-flex flex-column justify-content-center">
                            <h3><?= $activeCourses ?></h3>
                            <p class="mb-0">Corsi Attivi</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white h-100" style="background: linear-gradient(135deg, #fd7e14 0%, #e83e8c 100%);">
                        <div class="card-body text-center d-flex flex-column justify-content-center">
                            <h3><?= $totalStudents ?></h3>
                            <p class="mb-0">Clienti Totali</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista corsi -->
    <div class="card shadow-sm">
        <div class="card-body">
            <h4>Corsi</h4>
            <?php if (!empty($courses)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Prezzo</th>
                                <th>Partecipanti</th>
                                <th>Data Inizio</th>
                                <th>Data Fine</th>
                                <th>Stato</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($courses as $course): ?>
                                <?php
                                $today = new DateTime();
                                $startDate = new DateTime($course['startDate']);
                                $finishDate = new DateTime($course['finishDate']);
                                
                                if ($startDate > $today) {
                                    $status = 'In attesa';
                                    $statusClass = 'badge bg-warning';
                                } elseif ($finishDate >= $today) {
                                    $status = 'In corso';
                                    $statusClass = 'badge bg-success';
                                } else {
                                    $status = 'Completato';
                                    $statusClass = 'badge bg-primary';
                                }
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($course['name']) ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <?= htmlspecialchars(substr($course['description'], 0, 60)) ?>
                                            <?= strlen($course['description']) > 60 ? '...' : '' ?>
                                        </small>
                                    </td>
                                    <td>€<?= number_format($course['price'], 2) ?></td>
                                    <td>
                                        <span class="<?= $course['enrolled_count'] >= $course['maxParticipants'] ? 'text-danger fw-bold' : 'text-success' ?>">
                                            <?= $course['enrolled_count'] ?>/<?= $course['maxParticipants'] ?>
                                        </span>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($course['startDate'])) ?></td>
                                    <td><?= date('d/m/Y', strtotime($course['finishDate'])) ?></td>
                                    <td><span class="<?= $statusClass ?>"><?= $status ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <h5 class="text-muted">Nessun corso assegnato</h5>
                    <p class="text-muted">Al momento non hai corsi assegnati. Contatta l'amministratore per richiedere l'assegnazione di corsi.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>