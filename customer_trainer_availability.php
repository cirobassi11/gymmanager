<?php
require_once 'config.php';
session_start();

// Controllo accesso customer
if (!isset($_SESSION['userID'], $_SESSION['role']) || $_SESSION['role'] !== 'customer') {
    header('Location: login.php');
    exit();
}

$customerID = $_SESSION['userID'];

// Recupera i trainer che seguono questo cliente
$stmt = $conn->prepare("
    SELECT DISTINCT u.userID, u.firstName, u.lastName
    FROM USERS u
    LEFT JOIN TEACHINGS t ON u.userID = t.trainerID
    LEFT JOIN ENROLLMENTS e ON t.courseID = e.courseID AND e.customerID = ?
    LEFT JOIN TRAINING_SCHEDULES ts ON u.userID = ts.trainerID AND ts.customerID = ?
    WHERE u.role = 'trainer' 
    AND (e.customerID IS NOT NULL OR ts.customerID IS NOT NULL)
    ORDER BY u.firstName, u.lastName
");
$stmt->bind_param('ii', $customerID, $customerID);
$stmt->execute();
$myTrainers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Visualizzazione di un trainer specifico
$selectedTrainer = null;
$trainerAvailability = [];
if (isset($_GET['trainer']) && is_numeric($_GET['trainer'])) {
    $trainerID = (int)$_GET['trainer'];
    
    // Verifica che il trainer selezionato sia tra quelli che seguono il cliente
    foreach ($myTrainers as $trainer) {
        if ($trainer['userID'] == $trainerID) {
            $selectedTrainer = $trainer;
            break;
        }
    }
    
    if ($selectedTrainer) {
        // Recupera le disponibilità del trainer
        $stmt = $conn->prepare("
            SELECT * FROM AVAILABILITY_DAYS 
            WHERE trainerID = ? 
            ORDER BY 
                CASE dayOfWeek 
                    WHEN 'Monday' THEN 1
                    WHEN 'Tuesday' THEN 2
                    WHEN 'Wednesday' THEN 3
                    WHEN 'Thursday' THEN 4
                    WHEN 'Friday' THEN 5
                    WHEN 'Saturday' THEN 6
                    WHEN 'Sunday' THEN 7
                END, startTime
        ");
        $stmt->bind_param('i', $trainerID);
        $stmt->execute();
        $trainerAvailability = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

// Array giorni della settimana
$daysOfWeek = [
    'Monday' => 'Lunedì',
    'Tuesday' => 'Martedì', 
    'Wednesday' => 'Mercoledì',
    'Thursday' => 'Giovedì',
    'Friday' => 'Venerdì',
    'Saturday' => 'Sabato',
    'Sunday' => 'Domenica'
];

// Informazioni cliente
$stmt = $conn->prepare("SELECT firstName, lastName FROM USERS WHERE userID = ?");
$stmt->bind_param('i', $customerID);
$stmt->execute();
$customerInfo = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Disponibilità Trainer</title>
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
                    <h2>Disponibilità dei Tuoi Trainer</h2>
                </div>
                <div>
                    <?php if ($selectedTrainer): ?>
                        <a href="?" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-arrow-left me-2"></i>Torna alla Lista
                        </a>
                    <?php endif; ?>
                    <a href="dashboard.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i>Torna alla Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$selectedTrainer): ?>
        <!-- Lista Trainer -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h4>I Tuoi Trainer</h4>
                <?php if (!empty($myTrainers)): ?>
                    <div class="row">
                        <?php foreach($myTrainers as $trainer): ?>
                            <?php
                            // Recupera le disponibilità per questo trainer
                            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM AVAILABILITY_DAYS WHERE trainerID = ?");
                            $stmt->bind_param('i', $trainer['userID']);
                            $stmt->execute();
                            $availabilityCount = $stmt->get_result()->fetch_assoc()['count'];                            
                        ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body">
                                    <?= htmlspecialchars($trainer['firstName'] . ' ' . $trainer['lastName']) ?>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <?php if ($availabilityCount > 0): ?>
                                            <a href="?trainer=<?= $trainer['userID'] ?>" class="btn btn-primary btn-sm">
                                                Vedi Disponibilità
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-secondary btn-sm" disabled>
                                                Nessuna Disponibilità
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <h5 class="text-muted">Nessun trainer assegnato</h5>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($selectedTrainer): ?>
        <!-- Visualizzazione disponibilità trainer specifico -->
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <h3><?= htmlspecialchars($selectedTrainer['firstName'] . ' ' . $selectedTrainer['lastName']) ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Disponibilità settimanale -->
        <?php if (!empty($trainerAvailability)): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h4>Disponibilità Settimanale</h4>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Giorno</th>
                                    <th>Orario Inizio</th>
                                    <th>Orario Fine</th>
                                    <th>Durata</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($trainerAvailability as $availability): ?>
                                    <?php
                                    $start = new DateTime($availability['startTime']);
                                    $end = new DateTime($availability['finishTime']);
                                    $diff = $start->diff($end);
                                    $duration = $diff->h . 'h ' . $diff->i . 'm';
                                    ?>
                                    <tr>
                                        <td>
                                            <?= $daysOfWeek[$availability['dayOfWeek']] ?>
                                        </td>
                                        <td><?= substr($availability['startTime'], 0, 5) ?></td>
                                        <td><?= substr($availability['finishTime'], 0, 5) ?></td>
                                        <td><?= $duration ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Riepilogo settimanale visuale -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h4>Vista Settimanale</h4>
                    <div class="row g-2">
                        <?php foreach ($daysOfWeek as $value => $label): ?>
                            <?php
                            $dayAvailabilities = array_filter($trainerAvailability, function($a) use ($value) {
                                return $a['dayOfWeek'] === $value;
                            });
                            ?>
                            <div class="col-md-3 col-sm-6 mb-2">
                                <div class="card <?= !empty($dayAvailabilities) ? 'border-success bg-light' : 'border-light' ?> h-100">
                                    <div class="card-body p-3 text-center">
                                        <h6 class="card-title mb-2 fw-bold"><?= $label ?></h6>
                                        <?php if (!empty($dayAvailabilities)): ?>
                                            <?php foreach ($dayAvailabilities as $da): ?>
                                                <div class="badge bg-success mb-1 d-block">
                                                    <?= substr($da['startTime'], 0, 5) ?> - <?= substr($da['finishTime'], 0, 5) ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <small class="text-muted">Non disponibile</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="card shadow-sm">
                <div class="card-body text-center py-5">
                    <h5 class="text-muted">Nessuna disponibilità impostata</h5>
                </div>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- Trainer non trovato -->
        <div class="alert alert-warning">
            <h5>Trainer non trovato</h5>
            <p class="mb-0">Il trainer selezionato non è tra quelli che ti seguono.</p>
        </div>
    <?php endif; ?>
</div>
</body>
</html>