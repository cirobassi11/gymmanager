<?php
require_once 'config.php';
session_start();

// Controllo accesso customer
if (!isset($_SESSION['userID'], $_SESSION['role']) || $_SESSION['role'] !== 'customer') {
    header('Location: login.php');
    exit();
}

$customerID = $_SESSION['userID'];

// Gestione POST
$error_message = '';
$success_message = '';
$validation_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['enroll_course'])) {
        $courseID = (int)$_POST['courseID'];
        
        if ($courseID <= 0) {
            $validation_errors[] = 'Corso non valido.';
        }
        
        // Verifica che il corso esista
        $stmt = $conn->prepare("SELECT * FROM COURSE WHERE courseID = ?");
        $stmt->bind_param('i', $courseID);
        $stmt->execute();
        $course = $stmt->get_result()->fetch_assoc();
        
        if (!$course) {
            $validation_errors[] = 'Corso non trovato.';
        } else {
            // Verifica che il corso non sia già iniziato
            if ($course['startDate'] < date('Y-m-d')) {
                $validation_errors[] = 'Non puoi iscriverti a un corso già iniziato.';
            }
            
            // Verifica che il cliente non sia già iscritto
            $stmt = $conn->prepare("SELECT enrollmentDate FROM enrollment WHERE customerID = ? AND courseID = ?");
            $stmt->bind_param('ii', $customerID, $courseID);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $validation_errors[] = 'Sei già iscritto a questo corso.';
            }
            
            // Verifica posti disponibili
            $stmt = $conn->prepare("SELECT COUNT(*) as enrolled FROM enrollment WHERE courseID = ?");
            $stmt->bind_param('i', $courseID);
            $stmt->execute();
            $enrolledCount = $stmt->get_result()->fetch_assoc()['enrolled'];
            
            if ($enrolledCount >= $course['maxParticipants']) {
                $validation_errors[] = 'Il corso ha raggiunto il numero massimo di partecipanti.';
            }
            
            // Verifica che il cliente abbia un abbonamento attivo
            $stmt = $conn->prepare("
                SELECT subscriptionID 
                FROM SUBSCRIPTION 
                WHERE customerID = ? AND startDate <= CURDATE() AND expirationDate >= CURDATE()
            ");
            $stmt->bind_param('i', $customerID);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) {
                $validation_errors[] = 'Devi avere un abbonamento attivo per iscriverti ai corsi.';
            }
        }
        
        if (empty($validation_errors)) {
            // Iscrivi il cliente al corso
            $stmt = $conn->prepare("INSERT INTO enrollment (customerID, courseID, enrollmentDate) VALUES (?, ?, CURDATE())");
            $stmt->bind_param('ii', $customerID, $courseID);
            
            if ($stmt->execute()) {
                $success_message = 'Iscrizione al corso "' . htmlspecialchars($course['name']) . '" completata con successo!';
            } else {
                $error_message = 'Errore durante l\'iscrizione al corso.';
            }
        }
    } elseif (isset($_POST['unenroll_course'])) {
        $courseID = (int)$_POST['courseID'];
        
        // Verifica che il cliente sia effettivamente iscritto
        $stmt = $conn->prepare("SELECT enrollmentDate FROM enrollment WHERE customerID = ? AND courseID = ?");
        $stmt->bind_param('ii', $customerID, $courseID);
        $stmt->execute();
        $enrollment = $stmt->get_result()->fetch_assoc();
        
        if (!$enrollment) {
            $error_message = 'Non sei iscritto a questo corso.';
        } else {
            // Verifica che il corso non sia già iniziato
            $stmt = $conn->prepare("SELECT startDate FROM COURSE WHERE courseID = ?");
            $stmt->bind_param('i', $courseID);
            $stmt->execute();
            $course = $stmt->get_result()->fetch_assoc();
            
            if ($course['startDate'] < date('Y-m-d')) {
                $error_message = 'Non puoi annullare l\'iscrizione a un corso già iniziato.';
            } else {
                // Rimuovi l'iscrizione
                $stmt = $conn->prepare("DELETE FROM enrollment WHERE customerID = ? AND courseID = ?");
                $stmt->bind_param('ii', $customerID, $courseID);
                
                if ($stmt->execute()) {
                    $success_message = 'Iscrizione al corso annullata con successo.';
                } else {
                    $error_message = 'Errore durante l\'annullamento dell\'iscrizione.';
                }
            }
        }
    }
}

// Verifica abbonamento attivo
$stmt = $conn->prepare("
    SELECT s.*, m.name as membership_name
    FROM SUBSCRIPTION s
    JOIN MEMBERSHIP m ON s.membershipID = m.membershipID
    WHERE s.customerID = ? AND s.startDate <= CURDATE() AND s.expirationDate >= CURDATE()
");
$stmt->bind_param('i', $customerID);
$stmt->execute();
$activeSubscription = $stmt->get_result()->fetch_assoc();

// Recupera i corsi a cui il cliente è iscritto
$stmt = $conn->prepare("
    SELECT c.*, e.enrollmentDate,
           CASE 
               WHEN c.startDate > CURDATE() THEN 'In attesa'
               WHEN c.finishDate >= CURDATE() THEN 'In corso'
               ELSE 'Completato'
           END as course_status,
           GROUP_CONCAT(CONCAT(u.firstName, ' ', u.lastName) SEPARATOR ', ') as trainers
    FROM COURSE c
    JOIN enrollment e ON c.courseID = e.courseID
    LEFT JOIN teaching t ON c.courseID = t.courseID
    LEFT JOIN USER u ON t.trainerID = u.userID
    WHERE e.customerID = ?
    GROUP BY c.courseID, e.enrollmentDate
    ORDER BY c.startDate DESC
");
$stmt->bind_param('i', $customerID);
$stmt->execute();
$enrolledCourses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Recupera tutti i corsi disponibili
$stmt = $conn->prepare("
    SELECT c.*, 
           COUNT(e.customerID) as enrolled_count,
           (COUNT(e.customerID) >= c.maxParticipants) as is_full,
           CASE 
               WHEN c.startDate > CURDATE() THEN 'In programma'
               WHEN c.finishDate >= CURDATE() THEN 'In corso'
               ELSE 'Completato'
           END as course_status,
           GROUP_CONCAT(CONCAT(u.firstName, ' ', u.lastName) SEPARATOR ', ') as trainers,
           EXISTS(SELECT 1 FROM enrollment WHERE customerID = ? AND courseID = c.courseID) as is_enrolled
    FROM COURSE c
    LEFT JOIN enrollment e ON c.courseID = e.courseID
    LEFT JOIN teaching t ON c.courseID = t.courseID
    LEFT JOIN USER u ON t.trainerID = u.userID
    GROUP BY c.courseID
    ORDER BY c.startDate ASC
");
$stmt->bind_param('i', $customerID);
$stmt->execute();
$availableCourses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Informazioni cliente
$stmt = $conn->prepare("SELECT firstName, lastName, email FROM USER WHERE userID = ?");
$stmt->bind_param('i', $customerID);
$stmt->execute();
$customerInfo = $stmt->get_result()->fetch_assoc();

// Statistiche
$totalEnrolled = count($enrolledCourses);
$activeCourses = count(array_filter($enrolledCourses, function($course) {
    return $course['course_status'] === 'In corso';
}));
$upcomingCourses = count(array_filter($enrolledCourses, function($course) {
    return $course['course_status'] === 'In attesa';
}));
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Iscrizione ai Corsi</title>
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
                    <h2>Iscrizione ai Corsi</h2>
                </div>
                <a href="dashboard.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i>Torna alla Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Verifica abbonamento -->
    <?php if (!$activeSubscription): ?>
        <div class="alert alert-warning">
            <h5>Abbonamento Richiesto</h5>
            <p class="mb-2">Per iscriverti ai corsi devi avere un abbonamento attivo.</p>
            <a href="customer_subscription.php" class="btn btn-warning">Acquista Abbonamento</a>
        </div>
    <?php endif; ?>

    <!-- Messaggi -->
    <?php if (!empty($validation_errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach($validation_errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>
    
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>

    <!-- Statistiche -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <h4>Le Tue Statistiche</h4>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="card text-white h-100" style="background: linear-gradient(135deg, #6a85b6 0%, #bac8e0 100%);">
                        <div class="card-body text-center d-flex flex-column justify-content-center">
                            <h3><?= $totalEnrolled ?></h3>
                            <p class="mb-0">Corsi Totali</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white h-100" style="background: linear-gradient(135deg, #a8c8ec 0%, #7fcdcd 100%);">
                        <div class="card-body text-center d-flex flex-column justify-content-center">
                            <h3><?= $activeCourses ?></h3>
                            <p class="mb-0">Corsi Attivi</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white h-100" style="background: linear-gradient(135deg, #7fcdcd 0%, #c2e9fb 100%);">
                        <div class="card-body text-center d-flex flex-column justify-content-center">
                            <h3><?= $upcomingCourses ?></h3>
                            <p class="mb-0">Corsi in Programma</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- I Tuoi Corsi -->
    <?php if (!empty($enrolledCourses)): ?>
        <div class="card mb-4 shadow-sm">
            <div class="card-header">
                <h4 class="mb-0">I Tuoi Corsi</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Nome Corso</th>
                                <th>Prezzo</th>
                                <th>Trainer</th>
                                <th>Data Inizio</th>
                                <th>Data Fine</th>
                                <th>Iscrizione</th>
                                <th>Stato</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($enrolledCourses as $course): ?>
                                <?php
                                $statusClass = $course['course_status'] === 'In corso' ? 'success' : 
                                              ($course['course_status'] === 'In attesa' ? 'warning' : 'primary');
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($course['name']) ?></strong>
                                        <br><small class="text-muted"><?= htmlspecialchars($course['description']) ?></small>
                                    </td>
                                    <td>€<?= number_format($course['price'], 2) ?></td>
                                    <td><?= htmlspecialchars($course['trainers'] ?: 'Non assegnato') ?></td>
                                    <td><?= date('d/m/Y', strtotime($course['startDate'])) ?></td>
                                    <td><?= date('d/m/Y', strtotime($course['finishDate'])) ?></td>
                                    <td><?= date('d/m/Y', strtotime($course['enrollmentDate'])) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $statusClass ?>"><?= $course['course_status'] ?></span>
                                    </td>
                                    <td>
                                        <?php if ($course['course_status'] === 'In attesa'): ?>
                                            <form method="POST" style="display:inline" onsubmit="return confirm('Sei sicuro di voler annullare l\'iscrizione?');">
                                                <input type="hidden" name="courseID" value="<?= $course['courseID'] ?>">
                                                <button name="unenroll_course" class="btn btn-sm btn-outline-danger">
                                                    Annulla Iscrizione
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted">Iscrizione confermata</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Corsi Disponibili -->
    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <h4 class="mb-0">Corsi Disponibili</h4>
        </div>
        <div class="card-body">
            <?php if (!empty($availableCourses)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Nome Corso</th>
                                <th>Prezzo</th>
                                <th>Trainer</th>
                                <th>Partecipanti</th>
                                <th>Data Inizio</th>
                                <th>Data Fine</th>
                                <th>Stato</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($availableCourses as $course): ?>
                                <?php
                                $statusClass = $course['course_status'] === 'In corso' ? 'success' : 
                                              ($course['course_status'] === 'In programma' ? 'warning' : 'secondary');
                                $canEnroll = $activeSubscription && 
                                           !$course['is_enrolled'] && 
                                           !$course['is_full'] && 
                                           $course['course_status'] === 'In programma';
                                ?>
                                <tr class="<?= $course['is_enrolled'] ? 'table-success' : '' ?>">
                                    <td>
                                        <strong><?= htmlspecialchars($course['name']) ?></strong>
                                        <br><small class="text-muted"><?= htmlspecialchars($course['description']) ?></small>
                                    </td>
                                    <td>€<?= number_format($course['price'], 2) ?></td>
                                    <td><?= htmlspecialchars($course['trainers'] ?: 'Non assegnato') ?></td>
                                    <td>
                                        <span class="<?= $course['is_full'] ? 'text-danger fw-bold' : 'text-success' ?>">
                                            <?= $course['enrolled_count'] ?>/<?= $course['maxParticipants'] ?>
                                        </span>
                                        <?php if ($course['is_full']): ?>
                                            <br><small class="text-danger">Completo</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($course['startDate'])) ?></td>
                                    <td><?= date('d/m/Y', strtotime($course['finishDate'])) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $statusClass ?>"><?= $course['course_status'] ?></span>
                                    </td>
                                    <td>
                                        <?php if ($course['is_enrolled']): ?>
                                            <span class="badge bg-success">Iscritto</span>
                                        <?php elseif (!$activeSubscription): ?>
                                            <small class="text-muted">Abbonamento richiesto</small>
                                        <?php elseif ($course['is_full']): ?>
                                            <span class="badge bg-danger">Completo</span>
                                        <?php elseif ($course['course_status'] !== 'In programma'): ?>
                                            <span class="badge bg-secondary">Non disponibile</span>
                                        <?php else: ?>
                                            <form method="POST" style="display:inline" onsubmit="return confirm('Sei sicuro di voler iscriverti a questo corso?');">
                                                <input type="hidden" name="courseID" value="<?= $course['courseID'] ?>">
                                                <button name="enroll_course" class="btn btn-sm btn-success">
                                                    Iscriviti
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <h5 class="text-muted">Nessun corso disponibile</h5>
                    <p class="text-muted">Al momento non ci sono corsi disponibili. Torna più tardi per nuove opportunità!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Info Abbonamento -->
    <?php if ($activeSubscription): ?>
        <div class="card shadow-sm mb-4 border-success">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h6 class="text-success mb-2">Abbonamento Attivo</h6>
                        <p class="mb-0">
                            <strong><?= htmlspecialchars($activeSubscription['membership_name']) ?></strong> - 
                            Valido fino al <?= date('d/m/Y', strtotime($activeSubscription['expirationDate'])) ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <a href="customer_subscription.php" class="btn btn-outline-success btn-sm">
                            Gestisci Abbonamento
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>