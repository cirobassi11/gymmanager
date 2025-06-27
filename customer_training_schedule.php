<?php
require_once 'config.php';
session_start();

// Controllo accesso customer
if (!isset($_SESSION['userID'], $_SESSION['role']) || $_SESSION['role'] !== 'customer') {
    header('Location: login.php');
    exit();
}

$customerID = $_SESSION['userID'];

// Recupera tutti i programmi di allenamento assegnati al cliente
$stmt = $conn->prepare("
    SELECT ts.*, 
           u.firstName as trainer_firstName, u.lastName as trainer_lastName,
           COUNT(td.trainingDayID) as total_days
    FROM TRAINING_SCHEDULE ts
    JOIN USER u ON ts.trainerID = u.userID
    LEFT JOIN TRAINING_DAY td ON ts.trainingScheduleID = td.trainingScheduleID
    WHERE ts.customerID = ?
    GROUP BY ts.trainingScheduleID
    ORDER BY ts.creationDate DESC
");
$stmt->bind_param('i', $customerID);
$stmt->execute();
$trainingSchedules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Se stiamo visualizzando i dettagli di un programma
$viewSchedule = null;
$trainingDays = [];
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $scheduleID = (int)$_GET['view'];
    
    // Verifica che il programma appartenga al cliente
    $stmt = $conn->prepare("
        SELECT ts.*, 
               u.firstName as trainer_firstName, u.lastName as trainer_lastName,
               u.specialization as trainer_specialization
        FROM TRAINING_SCHEDULE ts
        JOIN USER u ON ts.trainerID = u.userID
        WHERE ts.trainingScheduleID = ? AND ts.customerID = ?
    ");
    $stmt->bind_param('ii', $scheduleID, $customerID);
    $stmt->execute();
    $viewSchedule = $stmt->get_result()->fetch_assoc();
    
    if ($viewSchedule) {
        // Recupera i giorni di allenamento del programma
        $stmt = $conn->prepare("
            SELECT td.*,
                   COUNT(ed.exerciseDetailID) as exercise_count
            FROM TRAINING_DAY td
            LEFT JOIN EXERCISE_DETAIL ed ON td.trainingDayID = ed.trainingDayID
            WHERE td.trainingScheduleID = ?
            GROUP BY td.trainingDayID
            ORDER BY td.dayOrder
        ");
        $stmt->bind_param('i', $scheduleID);
        $stmt->execute();
        $trainingDays = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

// Se stiamo visualizzando gli esercizi di un giorno specifico
$viewDay = null;
$dayExercises = [];
if (isset($_GET['view_day']) && is_numeric($_GET['view_day'])) {
    $dayID = (int)$_GET['view_day'];
    
    // Verifica che il giorno appartenga a un programma del cliente
    $stmt = $conn->prepare("
        SELECT td.*, ts.name as schedule_name,
               u.firstName as trainer_firstName, u.lastName as trainer_lastName
        FROM TRAINING_DAY td
        JOIN TRAINING_SCHEDULE ts ON td.trainingScheduleID = ts.trainingScheduleID
        JOIN USER u ON ts.trainerID = u.userID
        WHERE td.trainingDayID = ? AND ts.customerID = ?
    ");
    $stmt->bind_param('ii', $dayID, $customerID);
    $stmt->execute();
    $viewDay = $stmt->get_result()->fetch_assoc();
    
    if ($viewDay) {
        // Recupera gli esercizi del giorno
        $stmt = $conn->prepare("
            SELECT ed.*, e.name as exercise_name, e.description as exercise_description
            FROM EXERCISE_DETAIL ed
            JOIN EXERCISE e ON ed.exerciseID = e.exerciseID
            WHERE ed.trainingDayID = ?
            ORDER BY ed.orderInWorkout
        ");
        $stmt->bind_param('i', $dayID);
        $stmt->execute();
        $dayExercises = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

// Informazioni cliente
$stmt = $conn->prepare("SELECT firstName, lastName FROM USER WHERE userID = ?");
$stmt->bind_param('i', $customerID);
$stmt->execute();
$customerInfo = $stmt->get_result()->fetch_assoc();

// Statistiche
$totalSchedules = count($trainingSchedules);
$totalTrainers = count(array_unique(array_column($trainingSchedules, 'trainerID')));
$totalDays = array_sum(array_column($trainingSchedules, 'total_days'));

// Calcola il numero totale di esercizi
$totalExercises = 0;
if (!empty($trainingSchedules)) {
    $scheduleIDs = array_column($trainingSchedules, 'trainingScheduleID');
    $placeholders = str_repeat('?,', count($scheduleIDs) - 1) . '?';
    $stmt = $conn->prepare("
        SELECT COUNT(ed.exerciseDetailID) as total
        FROM EXERCISE_DETAIL ed
        JOIN TRAINING_DAY td ON ed.trainingDayID = td.trainingDayID
        WHERE td.trainingScheduleID IN ($placeholders)
    ");
    $stmt->bind_param(str_repeat('i', count($scheduleIDs)), ...$scheduleIDs);
    $stmt->execute();
    $totalExercises = $stmt->get_result()->fetch_assoc()['total'];
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>I Tuoi Programmi di Allenamento</title>
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
                    <h2>I Tuoi Programmi di Allenamento</h2>
                </div>
                <div>
                    <?php if (isset($_GET['view'])): ?>
                        <a href="?" class="btn btn-outline-secondary me-2">
                            Torna ai Programmi
                        </a>
                    <?php elseif (isset($_GET['view_day'])): ?>
                        <a href="?view=<?= $viewDay['trainingScheduleID'] ?>" class="btn btn-outline-secondary me-2">
                            Torna al Programma
                        </a>
                    <?php endif; ?>
                    <a href="dashboard.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i>Torna alla Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if (!isset($_GET['view']) && !isset($_GET['view_day'])): ?>
        <!-- Statistiche -->
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <h4>Le Tue Statistiche</h4>
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="card text-white h-100" style="background: linear-gradient(135deg, #6a85b6 0%, #bac8e0 100%);">
                            <div class="card-body text-center d-flex flex-column justify-content-center">
                                <h3><?= $totalSchedules ?></h3>
                                <p class="mb-0">Programmi</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white h-100" style="background: linear-gradient(135deg, #a8c8ec 0%, #7fcdcd 100%);">
                            <div class="card-body text-center d-flex flex-column justify-content-center">
                                <h3><?= $totalTrainers ?></h3>
                                <p class="mb-0">Trainer</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white h-100" style="background: linear-gradient(135deg, #7fcdcd 0%, #c2e9fb 100%);">
                            <div class="card-body text-center d-flex flex-column justify-content-center">
                                <h3><?= $totalDays ?></h3>
                                <p class="mb-0">Giorni</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white h-100" style="background: linear-gradient(135deg, #c2e9fb 0%, #a8c8ec 100%);">
                            <div class="card-body text-center d-flex flex-column justify-content-center">
                                <h3><?= $totalExercises ?></h3>
                                <p class="mb-0">Esercizi</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista Programmi -->
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h4 class="mb-0">I Tuoi Programmi di Allenamento</h4>
            </div>
            <div class="card-body">
                <?php if (!empty($trainingSchedules)): ?>
                    <div class="row">
                        <?php foreach($trainingSchedules as $schedule): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body">
                                    <h5 class="card-title text-primary"><?= htmlspecialchars($schedule['name']) ?></h5>
                                    <p class="card-text text-muted"><?= htmlspecialchars($schedule['description']) ?></p>
                                    
                                    <div class="mb-3">
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <small class="text-muted">Trainer:</small><br>
                                                <strong><?= htmlspecialchars($schedule['trainer_firstName'] . ' ' . $schedule['trainer_lastName']) ?></strong>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">Creato:</small><br>
                                                <strong><?= date('d/m/Y', strtotime($schedule['creationDate'])) ?></strong>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge bg-secondary"><?= $schedule['total_days'] ?> giorni</span>
                                        <a href="?view=<?= $schedule['trainingScheduleID'] ?>" class="btn btn-primary btn-sm">
                                            Visualizza
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <h5 class="text-muted">Nessun programma di allenamento</h5>
                        <p class="text-muted">I tuoi trainer non ti hanno ancora assegnato programmi di allenamento personalizzati.</p>
                        <p class="text-muted">Contatta il tuo trainer per richiedere un programma su misura per te!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif (isset($_GET['view']) && $viewSchedule): ?>
        <!-- Visualizzazione dettagli programma -->
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <h3><?= htmlspecialchars($viewSchedule['name']) ?></h3>
                        <p class="text-muted"><?= htmlspecialchars($viewSchedule['description']) ?></p>
                        
                        <div class="row g-3 mt-2">
                            <div class="col-md-6">
                                <strong>Data Creazione:</strong><br>
                                <span><?= date('d/m/Y', strtotime($viewSchedule['creationDate'])) ?></span>
                            </div>
                            <div class="col-md-6">
                                <strong>Giorni di Allenamento:</strong><br>
                                <span class="badge bg-info"><?= count($trainingDays) ?> giorni</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-light h-100">
                            <div class="card-body">
                                <h6 class="card-title">Il Tuo Trainer</h6>
                                <p class="card-text">
                                    <strong><?= htmlspecialchars($viewSchedule['trainer_firstName'] . ' ' . $viewSchedule['trainer_lastName']) ?></strong>
                                </p>
                                <?php if ($viewSchedule['trainer_specialization']): ?>
                                    <p class="card-text">
                                        <small class="text-muted">Specializzazione:</small><br>
                                        <span><?= htmlspecialchars($viewSchedule['trainer_specialization']) ?></span>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Giorni di allenamento -->
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h4 class="mb-0">Giorni di Allenamento</h4>
            </div>
            <div class="card-body">
                <?php if (!empty($trainingDays)): ?>
                    <div class="row">
                        <?php foreach($trainingDays as $day): ?>
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <h6 class="card-title"><?= htmlspecialchars($day['name']) ?></h6>
                                    <?php if ($day['description']): ?>
                                        <p class="card-text text-muted small"><?= htmlspecialchars($day['description']) ?></p>
                                    <?php endif; ?>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge bg-secondary"><?= $day['exercise_count'] ?> esercizi</span>
                                        <a href="?view_day=<?= $day['trainingDayID'] ?>" class="btn btn-sm btn-primary">
                                            Vedi Esercizi
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <h5 class="text-muted">Nessun giorno di allenamento</h5>
                        <p class="text-muted">Il trainer non ha ancora strutturato i giorni di allenamento per questo programma.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif (isset($_GET['view_day']) && $viewDay): ?>
        <!-- Visualizzazione esercizi di un giorno -->
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <h3><?= htmlspecialchars($viewDay['name']) ?></h3>
                <p><strong>Programma:</strong> <?= htmlspecialchars($viewDay['schedule_name']) ?></p>
                <p><strong>Trainer:</strong> <?= htmlspecialchars($viewDay['trainer_firstName'] . ' ' . $viewDay['trainer_lastName']) ?></p>
                <?php if ($viewDay['description']): ?>
                    <p><strong>Descrizione:</strong> <?= htmlspecialchars($viewDay['description']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Lista esercizi -->
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h4 class="mb-0">Esercizi del Giorno</h4>
            </div>
            <div class="card-body">
                <?php if (!empty($dayExercises)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Ordine</th>
                                    <th>Esercizio</th>
                                    <th>Serie</th>
                                    <th>Ripetizioni</th>
                                    <th>Peso</th>
                                    <th>Recupero</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($dayExercises as $exercise): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-primary"><?= $exercise['orderInWorkout'] ?></span>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($exercise['exercise_name']) ?></strong>
                                        <?php if ($exercise['exercise_description']): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($exercise['exercise_description']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $exercise['sets'] ?></td>
                                    <td><?= $exercise['reps'] ?></td>
                                    <td><?= $exercise['weight'] ? $exercise['weight'] . ' kg' : '-' ?></td>
                                    <td><?= $exercise['restTime'] ? $exercise['restTime'] . ' sec' : '-' ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <h5 class="text-muted">Nessun esercizio programmato</h5>
                        <p class="text-muted">Il trainer non ha ancora aggiunto esercizi per questo giorno di allenamento.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php else: ?>
        <!-- Messaggio di errore se il programma/giorno non è trovato -->
        <div class="alert alert-danger">
            <h5>Programma non trovato</h5>
            <p class="mb-0">Il programma o giorno di allenamento richiesto non esiste o non ti appartiene.</p>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>