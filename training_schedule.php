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

// Gestione POST
$error_message = '';
$success_message = '';
$validation_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_schedule'])) {
        // Validazione
        if (empty(trim($_POST['name'] ?? ''))) {
            $validation_errors[] = 'Il nome del programma è obbligatorio.';
        }
        if (empty($_POST['customerID'])) {
            $validation_errors[] = 'Devi selezionare un cliente.';
        }
        if (empty(trim($_POST['description'] ?? ''))) {
            $validation_errors[] = 'La descrizione è obbligatoria.';
        }
        
        // Se non ci sono errori, inserisci il programma
        if (empty($validation_errors)) {
            $stmt = $conn->prepare("INSERT INTO TRAINING_SCHEDULE (name, description, creationDate, customerID, trainerID) VALUES (?, ?, CURDATE(), ?, ?)");
            $stmt->bind_param(
                'ssii',
                $_POST['name'],
                $_POST['description'],
                $_POST['customerID'],
                $trainerID
            );
            if ($stmt->execute()) {
                $success_message = 'Programma di allenamento creato con successo!';
                unset($_POST);
            } else {
                $error_message = 'Errore durante la creazione del programma.';
            }
        }
    } elseif (isset($_POST['update_schedule'])) {
        // Validazione per modifica
        if (empty(trim($_POST['name'] ?? ''))) {
            $validation_errors[] = 'Il nome del programma è obbligatorio.';
        }
        if (empty($_POST['customerID'])) {
            $validation_errors[] = 'Devi selezionare un cliente.';
        }
        if (empty(trim($_POST['description'] ?? ''))) {
            $validation_errors[] = 'La descrizione è obbligatoria.';
        }
        
        if (empty($validation_errors)) {
            $scheduleID = (int)$_POST['trainingScheduleID'];
            $stmt = $conn->prepare("UPDATE TRAINING_SCHEDULE SET name = ?, description = ?, customerID = ? WHERE trainingScheduleID = ? AND trainerID = ?");
            $stmt->bind_param(
                'ssiii',
                $_POST['name'],
                $_POST['description'],
                $_POST['customerID'],
                $scheduleID,
                $trainerID
            );
            if ($stmt->execute()) {
                $success_message = 'Programma modificato con successo!';
            } else {
                $error_message = 'Errore durante la modifica del programma.';
            }
        }
    } elseif (isset($_POST['delete_schedule'])) {
        $deleteID = (int)$_POST['delete_id'];
        if ($deleteID > 0) {
            $stmt = $conn->prepare("DELETE FROM TRAINING_SCHEDULE WHERE trainingScheduleID = ? AND trainerID = ?");
            $stmt->bind_param('ii', $deleteID, $trainerID);
            if ($stmt->execute()) {
                $success_message = 'Programma eliminato con successo!';
            } else {
                $error_message = 'Errore durante l\'eliminazione del programma.';
            }
        }
    } elseif (isset($_POST['add_training_day'])) {
        // Aggiunta giorno di allenamento
        if (!empty($_POST['day_name']) && !empty($_POST['schedule_id'])) {
            $stmt = $conn->prepare("INSERT INTO TRAINING_DAY (name, description, trainingScheduleID, dayOrder) VALUES (?, ?, ?, ?)");
            $stmt->bind_param(
                'ssii',
                $_POST['day_name'],
                $_POST['day_description'],
                $_POST['schedule_id'],
                $_POST['day_order']
            );
            if ($stmt->execute()) {
                $success_message = 'Giorno di allenamento aggiunto con successo!';
                unset($_POST);
            } else {
                $error_message = 'Errore durante l\'aggiunta del giorno di allenamento.';
            }
        } else {
            $error_message = 'Nome del giorno e programma sono obbligatori.';
        }
    } elseif (isset($_POST['add_exercise'])) {
        // Aggiunta esercizio
        if (!empty($_POST['exerciseID']) && !empty($_POST['training_day_id'])) {
            $stmt = $conn->prepare("INSERT INTO EXERCISE_DETAIL (sets, reps, weight, restTime, trainingDayID, exerciseID, orderInWorkout) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param(
                'iiiiiii',
                $_POST['sets'],
                $_POST['reps'],
                $_POST['weight'],
                $_POST['restTime'],
                $_POST['training_day_id'],
                $_POST['exerciseID'],
                $_POST['order_in_workout']
            );
            if ($stmt->execute()) {
                $success_message = 'Esercizio aggiunto con successo!';
                unset($_POST);
            } else {
                $error_message = 'Errore durante l\'aggiunta dell\'esercizio.';
            }
        } else {
            $error_message = 'Esercizio e giorno di allenamento sono obbligatori.';
        }
    }
}

// Recupera tutti i programmi del trainer
$stmt = $conn->prepare("
    SELECT ts.*, u.firstName, u.lastName, 
           COUNT(td.trainingDayID) as day_count
    FROM TRAINING_SCHEDULE ts
    JOIN USER u ON ts.customerID = u.userID
    LEFT JOIN TRAINING_DAY td ON ts.trainingScheduleID = td.trainingScheduleID
    WHERE ts.trainerID = ?
    GROUP BY ts.trainingScheduleID
    ORDER BY ts.creationDate DESC
");
$stmt->bind_param('i', $trainerID);
$stmt->execute();
$schedules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Recupera tutti i clienti del trainer (dai corsi e dagli allenamenti esistenti)
$stmt = $conn->prepare("
    SELECT DISTINCT u.userID, u.firstName, u.lastName 
    FROM USER u 
    WHERE u.role = 'customer'
    ORDER BY u.firstName, u.lastName
");
$stmt->execute();
$customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Recupera tutti gli esercizi disponibili
$stmt = $conn->prepare("SELECT * FROM EXERCISE ORDER BY name");
$stmt->execute();
$exercises = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Se è una modifica, recupera i dati del programma
$editSchedule = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $scheduleID = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM TRAINING_SCHEDULE WHERE trainingScheduleID = ? AND trainerID = ?");
    $stmt->bind_param('ii', $scheduleID, $trainerID);
    $stmt->execute();
    $editSchedule = $stmt->get_result()->fetch_assoc();
}

// Se stiamo visualizzando i dettagli di un programma
$viewSchedule = null;
$trainingDays = [];
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $scheduleID = (int)$_GET['view'];
    
    // Recupera il programma
    $stmt = $conn->prepare("
        SELECT ts.*, u.firstName, u.lastName 
        FROM TRAINING_SCHEDULE ts
        JOIN USER u ON ts.customerID = u.userID
        WHERE ts.trainingScheduleID = ? AND ts.trainerID = ?
    ");
    $stmt->bind_param('ii', $scheduleID, $trainerID);
    $stmt->execute();
    $viewSchedule = $stmt->get_result()->fetch_assoc();
    
    if ($viewSchedule) {
        // Recupera i giorni di allenamento
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

// Se stiamo visualizzando gli esercizi di un giorno
$viewDay = null;
$dayExercises = [];
if (isset($_GET['view_day']) && is_numeric($_GET['view_day'])) {
    $dayID = (int)$_GET['view_day'];
    
    // Recupera il giorno di allenamento
    $stmt = $conn->prepare("
        SELECT td.*, ts.name as schedule_name, u.firstName, u.lastName
        FROM TRAINING_DAY td
        JOIN TRAINING_SCHEDULE ts ON td.trainingScheduleID = ts.trainingScheduleID
        JOIN USER u ON ts.customerID = u.userID
        WHERE td.trainingDayID = ? AND ts.trainerID = ?
    ");
    $stmt->bind_param('ii', $dayID, $trainerID);
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

// Statistiche
function getTrainerStats($conn, $trainerID) {
    // Totale programmi creati
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM TRAINING_SCHEDULE WHERE trainerID = ?");
    $stmt->bind_param('i', $trainerID);
    $stmt->execute();
    $totalSchedules = $stmt->get_result()->fetch_assoc()['total'];
    
    // Clienti seguiti
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT customerID) as count FROM TRAINING_SCHEDULE WHERE trainerID = ?");
    $stmt->bind_param('i', $trainerID);
    $stmt->execute();
    $clientsFollowed = $stmt->get_result()->fetch_assoc()['count'];
    
    // Giorni di allenamento totali creati
    $stmt = $conn->prepare("
        SELECT COUNT(td.trainingDayID) as total
        FROM TRAINING_DAY td
        JOIN TRAINING_SCHEDULE ts ON td.trainingScheduleID = ts.trainingScheduleID
        WHERE ts.trainerID = ?
    ");
    $stmt->bind_param('i', $trainerID);
    $stmt->execute();
    $totalDays = $stmt->get_result()->fetch_assoc()['total'];
    
    return [
        'totalSchedules' => $totalSchedules,
        'clientsFollowed' => $clientsFollowed,
        'totalDays' => $totalDays
    ];
}

$stats = getTrainerStats($conn, $trainerID);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Programmi di Allenamento</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <h2>Programmi di Allenamento</h2>
                <div>
                    <?php if (isset($_GET['view'])): ?>
                        <a href="?" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-arrow-left me-2"></i>Torna ai Programmi
                        </a>
                    <?php elseif (isset($_GET['view_day'])): ?>
                        <a href="?view=<?= $viewDay['trainingScheduleID'] ?>" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-arrow-left me-2"></i>Torna al Programma
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
        <!-- Area Statistiche -->
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <h4>Le Tue Statistiche</h4>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="card text-white h-100" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                            <div class="card-body text-center d-flex flex-column justify-content-center">
                                <h3><?= $stats['totalSchedules'] ?></h3>
                                <p class="mb-0">Programmi Creati</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white h-100" style="background: linear-gradient(135deg, #17a2b8 0%, #28a745 100%);">
                            <div class="card-body text-center d-flex flex-column justify-content-center">
                                <h3><?= $stats['clientsFollowed'] ?></h3>
                                <p class="mb-0">Clienti Seguiti</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white h-100" style="background: linear-gradient(135deg, #6f42c1 0%, #17a2b8 100%);">
                            <div class="card-body text-center d-flex flex-column justify-content-center">
                                <h3><?= $stats['totalDays'] ?></h3>
                                <p class="mb-0">Giorni di Allenamento</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form aggiunta/modifica programma -->
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <h4><?= $editSchedule ? 'Modifica Programma' : 'Crea Nuovo Programma' ?></h4>
                
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
                
                <form method="POST" class="row g-3">
                    <?php if ($editSchedule): ?>
                        <input type="hidden" name="trainingScheduleID" value="<?= $editSchedule['trainingScheduleID'] ?>">
                    <?php endif; ?>
                    <div class="col-md-6">
                        <label class="form-label">Nome Programma</label>
                        <input name="name" required class="form-control" type="text" 
                               value="<?= $editSchedule ? htmlspecialchars($editSchedule['name']) : (isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '') ?>" />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Cliente</label>
                        <select name="customerID" class="form-select" required>
                            <option value="">Seleziona cliente</option>
                            <?php foreach($customers as $customer): ?>
                                <option value="<?= $customer['userID'] ?>" 
                                    <?= ($editSchedule && $editSchedule['customerID'] == $customer['userID']) || (isset($_POST['customerID']) && $_POST['customerID'] == $customer['userID']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($customer['firstName'] . ' ' . $customer['lastName']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Descrizione</label>
                        <textarea name="description" required class="form-control" rows="3"><?= $editSchedule ? htmlspecialchars($editSchedule['description']) : (isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '') ?></textarea>
                    </div>
                    <div class="col-12">
                        <button name="<?= $editSchedule ? 'update_schedule' : 'add_schedule' ?>" class="btn <?= $editSchedule ? 'btn-warning' : 'btn-success' ?>">
                            <?= $editSchedule ? 'Modifica Programma' : 'Crea Programma' ?>
                        </button>
                        <?php if ($editSchedule): ?>
                            <a href="?" class="btn btn-secondary">Annulla</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabella programmi -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h4>I Tuoi Programmi</h4>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Nome Programma</th><th>Cliente</th><th>Data Creazione</th><th>Giorni</th><th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($schedules as $schedule): ?>
                                <tr>
                                    <td><?= htmlspecialchars($schedule['name']) ?></td>
                                    <td><?= htmlspecialchars($schedule['firstName'] . ' ' . $schedule['lastName']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($schedule['creationDate'])) ?></td>
                                    <td><span class="badge bg-info"><?= $schedule['day_count'] ?> giorni</span></td>
                                    <td>
                                        <a href="?view=<?= $schedule['trainingScheduleID'] ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i> Visualizza
                                        </a>
                                        <a href="?edit=<?= $schedule['trainingScheduleID'] ?>" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i> Modifica
                                        </a>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('Sei sicuro di eliminare questo programma?');">
                                            <input type="hidden" name="delete_id" value="<?= $schedule['trainingScheduleID'] ?>">
                                            <button name="delete_schedule" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i> Elimina
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($schedules)): ?>
                                <tr><td colspan="5" class="text-center">Nessun programma creato.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php elseif (isset($_GET['view']) && $viewSchedule): ?>
        <!-- Visualizzazione dettagli programma -->
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <h3><?= htmlspecialchars($viewSchedule['name']) ?></h3>
                        <p><strong>Cliente:</strong> <?= htmlspecialchars($viewSchedule['firstName'] . ' ' . $viewSchedule['lastName']) ?></p>
                        <p><strong>Descrizione:</strong> <?= htmlspecialchars($viewSchedule['description']) ?></p>
                        <p><strong>Data Creazione:</strong> <?= date('d/m/Y', strtotime($viewSchedule['creationDate'])) ?></p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h4><?= count($trainingDays) ?></h4>
                                <p class="mb-0">Giorni di Allenamento</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form aggiunta giorno di allenamento -->
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <h4>Aggiungi Giorno di Allenamento</h4>
                <form method="POST" class="row g-3">
                    <input type="hidden" name="schedule_id" value="<?= $viewSchedule['trainingScheduleID'] ?>">
                    <div class="col-md-4">
                        <label class="form-label">Nome Giorno</label>
                        <input name="day_name" required class="form-control" type="text" placeholder="es. Giorno 1 - Petto e Tricipiti" />
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Ordine</label>
                        <input name="day_order" required class="form-control" type="number" min="1" value="<?= count($trainingDays) + 1 ?>" />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Descrizione</label>
                        <input name="day_description" class="form-control" type="text" placeholder="Descrizione del giorno di allenamento" />
                    </div>
                    <div class="col-12">
                        <button name="add_training_day" class="btn btn-success">
                            <i class="fas fa-plus"></i> Aggiungi Giorno
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Lista giorni di allenamento -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h4>Giorni di Allenamento</h4>
                <?php if (!empty($trainingDays)): ?>
                    <div class="row">
                        <?php foreach($trainingDays as $day): ?>
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <h6 class="card-title"><?= htmlspecialchars($day['name']) ?></h6>
                                    <p class="card-text text-muted small"><?= htmlspecialchars($day['description']) ?></p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge bg-secondary"><?= $day['exercise_count'] ?> esercizi</span>
                                        <a href="?view_day=<?= $day['trainingDayID'] ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-dumbbell"></i> Gestisci
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-calendar-plus fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Nessun giorno di allenamento</h5>
                        <p class="text-muted">Aggiungi il primo giorno di allenamento per questo programma.</p>
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
                <p><strong>Cliente:</strong> <?= htmlspecialchars($viewDay['firstName'] . ' ' . $viewDay['lastName']) ?></p>
                <p><strong>Descrizione:</strong> <?= htmlspecialchars($viewDay['description']) ?></p>
            </div>
        </div>

        <!-- Form aggiunta esercizio -->
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <h4>Aggiungi Esercizio</h4>
                <form method="POST" class="row g-3">
                    <input type="hidden" name="training_day_id" value="<?= $viewDay['trainingDayID'] ?>">
                    <div class="col-md-6">
                        <label class="form-label">Esercizio</label>
                        <select name="exerciseID" class="form-select" required>
                            <option value="">Seleziona esercizio</option>
                            <?php foreach($exercises as $exercise): ?>
                                <option value="<?= $exercise['exerciseID'] ?>">
                                    <?= htmlspecialchars($exercise['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Serie</label>
                        <input name="sets" required class="form-control" type="number" min="1" value="3" />
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Ripetizioni</label>
                        <input name="reps" required class="form-control" type="number" min="1" value="12" />
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Peso (kg)</label>
                        <input name="weight" class="form-control" type="number" step="0.5" min="0" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Recupero (sec)</label>
                        <input name="restTime" class="form-control" type="number" min="0" value="60" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Ordine</label>
                        <input name="order_in_workout" required class="form-control" type="number" min="1" value="<?= count($dayExercises) + 1 ?>" />
                    </div>
                    <div class="col-12">
                        <button name="add_exercise" class="btn btn-success">
                            <i class="fas fa-plus"></i> Aggiungi Esercizio
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Lista esercizi -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h4>Esercizi del Giorno</h4>
                <?php if (!empty($dayExercises)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Ordine</th><th>Esercizio</th><th>Serie</th><th>Ripetizioni</th><th>Peso</th><th>Recupero</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($dayExercises as $exercise): ?>
                                <tr>
                                    <td><span class="badge bg-primary"><?= $exercise['orderInWorkout'] ?></span></td>
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
                        <i class="fas fa-dumbbell fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Nessun esercizio</h5>
                        <p class="text-muted">Aggiungi il primo esercizio per questo giorno di allenamento.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php endif; ?>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>