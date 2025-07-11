<?php
require_once 'config.php';
session_start();

// Controllo accesso trainer
if (!isset($_SESSION['userID'], $_SESSION['role']) || $_SESSION['role'] !== 'trainer') {
    header('Location: login.php');
    exit();
}

$trainerID = $_SESSION['userID'];

// Gestione POST
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        // Aggiunta disponibilità
        if (!empty($_POST['dayOfWeek']) && !empty($_POST['startTime']) && !empty($_POST['finishTime'])) {
            // Validazione formato orario
            if (!preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/', $_POST['startTime'])) {
                $error_message = 'Formato orario inizio non valido. Usa HH:MM (es: 14:30)';
            } elseif (!preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/', $_POST['finishTime'])) {
                $error_message = 'Formato orario fine non valido. Usa HH:MM (es: 18:45)';
            } elseif ($_POST['startTime'] >= $_POST['finishTime']) {
                $error_message = 'L\'orario di inizio deve essere precedente a quello di fine.';
            } else {
                // Verifica se esiste già una disponibilità sovrapposta per quel giorno
                $stmt = $conn->prepare("
                    SELECT availabilityDayID FROM AVAILABILITY_DAYS 
                    WHERE trainerID = ? AND dayOfWeek = ? AND 
                    ((startTime <= ? AND finishTime > ?) OR 
                     (startTime < ? AND finishTime >= ?) OR
                     (startTime >= ? AND finishTime <= ?))
                ");
                $stmt->bind_param('isssssss', 
                    $trainerID, 
                    $_POST['dayOfWeek'], 
                    $_POST['startTime'], $_POST['startTime'],
                    $_POST['finishTime'], $_POST['finishTime'],
                    $_POST['startTime'], $_POST['finishTime']
                );
                $stmt->execute();
                
                if ($stmt->get_result()->num_rows > 0) {
                    $error_message = 'Esiste già una disponibilità sovrapposta per questo giorno e orario.';
                } else {
                    $stmt = $conn->prepare("INSERT INTO AVAILABILITY_DAYS (trainerID, dayOfWeek, startTime, finishTime) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param('isss', $trainerID, $_POST['dayOfWeek'], $_POST['startTime'], $_POST['finishTime']);
                    
                    if ($stmt->execute()) {
                        $success_message = 'Disponibilità aggiunta con successo!';
                    } else {
                        $error_message = 'Errore durante l\'inserimento: ' . $stmt->error;
                    }
                }
            }
        } else {
            $error_message = 'Tutti i campi sono obbligatori.';
        }
    } elseif (isset($_POST['update'])) {
        // Modifica disponibilità
        if (!empty($_POST['dayOfWeek']) && !empty($_POST['startTime']) && !empty($_POST['finishTime'])) {
            // Validazione formato orario
            if (!preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/', $_POST['startTime'])) {
                $error_message = 'Formato orario inizio non valido. Usa HH:MM (es: 14:30)';
            } elseif (!preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/', $_POST['finishTime'])) {
                $error_message = 'Formato orario fine non valido. Usa HH:MM (es: 18:45)';
            } elseif ($_POST['startTime'] >= $_POST['finishTime']) {
                $error_message = 'L\'orario di inizio deve essere precedente a quello di fine.';
            } else {
                // Verifica sovrapposizioni escludendo la disponibilità corrente
                $stmt = $conn->prepare("
                    SELECT availabilityDayID FROM AVAILABILITY_DAYS 
                    WHERE trainerID = ? AND dayOfWeek = ? AND availabilityDayID != ? AND
                    ((startTime <= ? AND finishTime > ?) OR 
                     (startTime < ? AND finishTime >= ?) OR
                     (startTime >= ? AND finishTime <= ?))
                ");
                $stmt->bind_param('isissssss', 
                    $trainerID, 
                    $_POST['dayOfWeek'], 
                    $_POST['availabilityDayID'],
                    $_POST['startTime'], $_POST['startTime'],
                    $_POST['finishTime'], $_POST['finishTime'],
                    $_POST['startTime'], $_POST['finishTime']
                );
                $stmt->execute();
                
                if ($stmt->get_result()->num_rows > 0) {
                    $error_message = 'Esiste già una disponibilità sovrapposta per questo giorno e orario.';
                } else {
                    $stmt = $conn->prepare("UPDATE AVAILABILITY_DAYS SET dayOfWeek = ?, startTime = ?, finishTime = ? WHERE availabilityDayID = ? AND trainerID = ?");
                    $stmt->bind_param('sssii', $_POST['dayOfWeek'], $_POST['startTime'], $_POST['finishTime'], $_POST['availabilityDayID'], $trainerID);
                    
                    if ($stmt->execute()) {
                        $success_message = 'Disponibilità modificata con successo!';
                    } else {
                        $error_message = 'Errore durante la modifica della disponibilità: ' . $conn->error;
                    }
                }
            }
        } else {
            $error_message = 'Tutti i campi sono obbligatori.';
        }
    } elseif (isset($_POST['delete'])) {
        // Eliminazione disponibilità
        $stmt = $conn->prepare("DELETE FROM AVAILABILITY_DAYS WHERE availabilityDayID = ? AND trainerID = ?");
        $stmt->bind_param('ii', $_POST['delete_id'], $trainerID);
        
        if ($stmt->execute()) {
            $success_message = 'Disponibilità eliminata con successo!';
        } else {
            $error_message = 'Errore durante l\'eliminazione della disponibilità: ' . $conn->error;
        }
    }
}

// Recupera le disponibilità del trainer
$stmt = $conn->prepare("SELECT * FROM AVAILABILITY_DAYS WHERE trainerID = ? ORDER BY 
    CASE dayOfWeek 
        WHEN 'Monday' THEN 1
        WHEN 'Tuesday' THEN 2
        WHEN 'Wednesday' THEN 3
        WHEN 'Thursday' THEN 4
        WHEN 'Friday' THEN 5
        WHEN 'Saturday' THEN 6
        WHEN 'Sunday' THEN 7
    END, startTime");
$stmt->bind_param('i', $trainerID);
$stmt->execute();
$availabilities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Se è una modifica, recupera i dati
$editAvailability = null;
if (isset($_GET['edit'])) {
    $availabilityID = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM AVAILABILITY_DAYS WHERE availabilityDayID = ? AND trainerID = ?");
    $stmt->bind_param('ii', $availabilityID, $trainerID);
    $stmt->execute();
    $editAvailability = $stmt->get_result()->fetch_assoc();
}

// Informazioni trainer
$stmt = $conn->prepare("SELECT firstName, lastName FROM USERS WHERE userID = ?");
$stmt->bind_param('i', $trainerID);
$stmt->execute();
$trainerInfo = $stmt->get_result()->fetch_assoc();

// Statistiche
$totalSlots = count($availabilities);
$uniqueDays = count(array_unique(array_column($availabilities, 'dayOfWeek')));

// Calcola ore totali disponibili a settimana
$totalHours = 0;
foreach ($availabilities as $availability) {
    $start = new DateTime($availability['startTime']);
    $end = new DateTime($availability['finishTime']);
    $diff = $start->diff($end);
    $totalHours += $diff->h + ($diff->i / 60);
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
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Gestione Disponibilità</title>
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
                    <h2>Gestione Disponibilità</h2>
                </div>
                <a href="dashboard.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i>Torna alla Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Statistiche -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <h4>Statistiche Disponibilità</h4>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="card text-white h-100" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                        <div class="card-body text-center d-flex flex-column justify-content-center">
                            <h3><?= $totalSlots ?></h3>
                            <p class="mb-0">Slot Totali</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white h-100" style="background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);">
                        <div class="card-body text-center d-flex flex-column justify-content-center">
                            <h3><?= $uniqueDays ?></h3>
                            <p class="mb-0">Giorni Coperti</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white h-100" style="background: linear-gradient(135deg, #fd7e14 0%, #e83e8c 100%);">
                        <div class="card-body text-center d-flex flex-column justify-content-center">
                            <h3><?= number_format($totalHours, 1) ?>h</h3>
                            <p class="mb-0">Ore Settimanali</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Form aggiungi/modifica disponibilità -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <h4><?= $editAvailability ? 'Modifica Disponibilità' : 'Aggiungi Disponibilità' ?></h4>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>
            
            <form method="POST" class="row g-3">
                <?php if ($editAvailability): ?>
                    <input type="hidden" name="availabilityDayID" value="<?= $editAvailability['availabilityDayID'] ?>">
                <?php endif; ?>
                
                <div class="col-md-4">
                    <label class="form-label">Giorno della Settimana</label>
                    <select name="dayOfWeek" class="form-select" required>
                        <option value="">Seleziona giorno</option>
                        <?php foreach ($daysOfWeek as $value => $label): ?>
                            <option value="<?= $value ?>" <?= ($editAvailability && $editAvailability['dayOfWeek'] === $value) ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Orario Inizio</label>
                    <input name="startTime" required class="form-control" type="text" 
                           placeholder="HH:MM (es: 14:30)" pattern="^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$"
                           value="<?= $editAvailability ? substr($editAvailability['startTime'], 0, 5) : '' ?>" />
                    <div class="form-text">Formato 24 ore: HH:MM (es: 23:45)</div>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Orario Fine</label>
                    <input name="finishTime" required class="form-control" type="text" 
                           placeholder="HH:MM (es: 18:45)" pattern="^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$"
                           value="<?= $editAvailability ? substr($editAvailability['finishTime'], 0, 5) : '' ?>" />
                    <div class="form-text">Formato 24 ore: HH:MM (es: 23:45)</div>
                </div>
                
                <div class="col-12">
                    <button name="<?= $editAvailability ? 'update' : 'add' ?>" class="btn <?= $editAvailability ? 'btn-warning' : 'btn-success' ?>">
                        <?= $editAvailability ? 'Modifica Disponibilità' : 'Aggiungi Disponibilità' ?>
                    </button>
                    <?php if ($editAvailability): ?>
                        <a href="?" class="btn btn-secondary">Annulla</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista disponibilità -->
    <div class="card shadow-sm">
        <div class="card-body">
            <h4>Le Tue Disponibilità</h4>
            <?php if (!empty($availabilities)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Giorno</th>
                                <th>Orario Inizio</th>
                                <th>Orario Fine</th>
                                <th>Durata</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($availabilities as $availability): ?>
                                <?php
                                $start = new DateTime($availability['startTime']);
                                $end = new DateTime($availability['finishTime']);
                                $diff = $start->diff($end);
                                $duration = $diff->h . 'h ' . $diff->i . 'm';
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= $daysOfWeek[$availability['dayOfWeek']] ?></strong>
                                    </td>
                                    <td><?= substr($availability['startTime'], 0, 5) ?></td>
                                    <td><?= substr($availability['finishTime'], 0, 5) ?></td>
                                    <td><?= $duration ?></td>
                                    <td>
                                        <a href="?edit=<?= $availability['availabilityDayID'] ?>" class="btn btn-sm btn-warning">
                                            Modifica
                                        </a>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('Sei sicuro di eliminare questa disponibilità?');">
                                            <input type="hidden" name="delete_id" value="<?= $availability['availabilityDayID'] ?>">
                                            <button name="delete" class="btn btn-sm btn-danger">
                                                Elimina
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Riepilogo settimanale -->
                <div class="mt-4">
                    <h5>Riepilogo Settimanale</h5>
                    <div class="row g-2">
                        <?php foreach ($daysOfWeek as $value => $label): ?>
                            <?php
                            $dayAvailabilities = array_filter($availabilities, function($a) use ($value) {
                                return $a['dayOfWeek'] === $value;
                            });
                            ?>
                            <div class="col-md-3 col-sm-6 mb-2">
                                <div class="card <?= !empty($dayAvailabilities) ? 'border-success' : 'border-light' ?> h-100">
                                    <div class="card-body p-2 text-center">
                                        <h6 class="card-title mb-1"><?= $label ?></h6>
                                        <?php if (!empty($dayAvailabilities)): ?>
                                            <?php foreach ($dayAvailabilities as $da): ?>
                                                <small class="text-success d-block">
                                                    <?= substr($da['startTime'], 0, 5) ?> - <?= substr($da['finishTime'], 0, 5) ?>
                                                </small>
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
            <?php else: ?>
                <div class="text-center py-5">
                    <h5 class="text-muted">Nessuna disponibilità impostata</h5>
                    <p class="text-muted">Aggiungi i tuoi orari di disponibilità per permettere ai clienti di prenotare sessioni.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>