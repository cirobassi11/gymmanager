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

$error_message = '';
$success_message = '';
$validation_errors = [];

// Gestione form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_exercise']) || isset($_POST['update_exercise'])) {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($name === '') $validation_errors[] = "Il nome dell'esercizio è obbligatorio.";
        if ($description === '') $validation_errors[] = "La descrizione è obbligatoria.";

        if (empty($validation_errors)) {
            if (isset($_POST['add_exercise'])) {
                $stmt = $conn->prepare("INSERT INTO EXERCISE (name, description, trainerID) VALUES (?, ?, ?)");
                $stmt->bind_param('ssi', $name, $description, $trainerID);
                if ($stmt->execute()) {
                    $success_message = "Esercizio aggiunto con successo!";
                    unset($_POST);
                } else {
                    $error_message = "Errore durante l'inserimento dell'esercizio.";
                }
            } elseif (isset($_POST['update_exercise'])) {
                $exerciseID = (int)$_POST['exerciseID'];
                $stmt = $conn->prepare("SELECT exerciseID FROM EXERCISE WHERE exerciseID = ? AND trainerID = ?");
                $stmt->bind_param('ii', $exerciseID, $trainerID);
                $stmt->execute();

                if ($stmt->get_result()->num_rows === 0) {
                    $error_message = "Puoi modificare solo i tuoi esercizi.";
                } else {
                    $stmt = $conn->prepare("UPDATE EXERCISE SET name = ?, description = ? WHERE exerciseID = ? AND trainerID = ?");
                    $stmt->bind_param('ssii', $name, $description, $exerciseID, $trainerID);
                    if ($stmt->execute()) {
                        $success_message = "Esercizio modificato con successo!";
                    } else {
                        $error_message = "Errore durante la modifica dell'esercizio.";
                    }
                }
            }
        }
    } elseif (isset($_POST['delete_exercise'])) {
        $deleteID = (int)$_POST['delete_id'];
        if ($deleteID > 0) {
            $stmt = $conn->prepare("SELECT exerciseID FROM EXERCISE WHERE exerciseID = ? AND trainerID = ?");
            $stmt->bind_param('ii', $deleteID, $trainerID);
            $stmt->execute();

            if ($stmt->get_result()->num_rows === 0) {
                $error_message = "Puoi eliminare solo i tuoi esercizi.";
            } else {
                $stmt = $conn->prepare("
                    SELECT COUNT(*) as count 
                    FROM EXERCISE_DETAIL ed
                    JOIN TRAINING_DAY td ON ed.trainingDayID = td.trainingDayID
                    JOIN TRAINING_SCHEDULE ts ON td.trainingScheduleID = ts.trainingScheduleID
                    WHERE ed.exerciseID = ? AND ts.trainerID = ?
                ");
                $stmt->bind_param('ii', $deleteID, $trainerID);
                $stmt->execute();
                $usageCount = $stmt->get_result()->fetch_assoc()['count'];

                if ($usageCount > 0) {
                    $error_message = "Impossibile eliminare l'esercizio: è utilizzato in $usageCount tuoi programmi.";
                } else {
                    $stmt = $conn->prepare("DELETE FROM EXERCISE WHERE exerciseID = ? AND trainerID = ?");
                    $stmt->bind_param('ii', $deleteID, $trainerID);
                    if ($stmt->execute()) {
                        $success_message = "Esercizio eliminato con successo!";
                    } else {
                        $error_message = "Errore durante l'eliminazione dell'esercizio.";
                    }
                }
            }
        }
    }
}

// Recupera esercizi
$stmt = $conn->prepare("
    SELECT e.exerciseID, e.name, e.description,
           COUNT(ed.exerciseDetailID) as usage_count
    FROM EXERCISE e
    LEFT JOIN EXERCISE_DETAIL ed ON e.exerciseID = ed.exerciseID
    LEFT JOIN TRAINING_DAY td ON ed.trainingDayID = td.trainingDayID
    LEFT JOIN TRAINING_SCHEDULE ts ON td.trainingScheduleID = ts.trainingScheduleID AND ts.trainerID = ?
    WHERE e.trainerID = ? OR e.trainerID IS NULL
    GROUP BY e.exerciseID, e.name, e.description
    ORDER BY e.name ASC
");
$stmt->bind_param('ii', $trainerID, $trainerID);
$stmt->execute();
$exercises = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Statistiche
function getExerciseStats($conn, $trainerID) {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM EXERCISE WHERE trainerID = ?");
    $stmt->bind_param('i', $trainerID);
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];

    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT e.exerciseID) as used
        FROM EXERCISE e
        JOIN EXERCISE_DETAIL ed ON e.exerciseID = ed.exerciseID
        JOIN TRAINING_DAY td ON ed.trainingDayID = td.trainingDayID
        JOIN TRAINING_SCHEDULE ts ON td.trainingScheduleID = ts.trainingScheduleID
        WHERE e.trainerID = ? AND ts.trainerID = ?
    ");
    $stmt->bind_param('ii', $trainerID, $trainerID);
    $stmt->execute();
    $used = $stmt->get_result()->fetch_assoc()['used'];

    return ['totalExercises' => $total, 'usedExercises' => $used];
}

$stats = getExerciseStats($conn, $trainerID);

// Modifica
$editExercise = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $exerciseID = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM EXERCISE WHERE exerciseID = ? AND trainerID = ?");
    $stmt->bind_param('ii', $exerciseID, $trainerID);
    $stmt->execute();
    $editExercise = $stmt->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Gestione Esercizi</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="card shadow-sm mb-4">
        <div class="card-body d-flex justify-content-between align-items-center">
            <h2>Gestione Esercizi</h2>
            <a href="dashboard.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-2"></i>Torna alla Dashboard
            </a>
        </div>
    </div>

    <!-- Statistiche -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <h4 class="mb-4">Statistiche Esercizi</h4>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="card text-white h-100" style="background: linear-gradient(135deg, #6a85b6 0%, #bac8e0 100%);">
                        <div class="card-body text-center d-flex flex-column justify-content-center">
                            <h3><?= $stats['totalExercises'] ?></h3>
                            <p class="mb-0">Esercizi Totali</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card text-white h-100" style="background: linear-gradient(135deg, #a8c8ec 0%, #7fcdcd 100%);">
                        <div class="card-body text-center d-flex flex-column justify-content-center">
                            <h3><?= $stats['usedExercises'] ?></h3>
                            <p class="mb-0">Esercizi Utilizzati</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Form aggiunta/modifica esercizio -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <h4><?= $editExercise ? 'Modifica Esercizio' : 'Aggiungi Esercizio' ?></h4>
            
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
                <?php if ($editExercise): ?>
                    <input type="hidden" name="exerciseID" value="<?= $editExercise['exerciseID'] ?>">
                <?php endif; ?>
                <div class="col-md-6">
                    <label class="form-label">Nome Esercizio</label>
                    <input name="name" required class="form-control" type="text" 
                           placeholder="es. Panca Piana, Squat, Deadlift"
                           value="<?= $editExercise ? htmlspecialchars($editExercise['name']) : (isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '') ?>" />
                </div>
                <div class="col-12">
                    <label class="form-label">Descrizione</label>
                    <textarea name="description" required class="form-control" rows="4" 
                              placeholder="Descrizione dettagliata dell'esercizio, tecnica di esecuzione, muscoli coinvolti..."><?= $editExercise ? htmlspecialchars($editExercise['description']) : (isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '') ?></textarea>
                </div>
                <div class="col-12">
                    <button name="<?= $editExercise ? 'update_exercise' : 'add_exercise' ?>" class="btn <?= $editExercise ? 'btn-warning' : 'btn-success' ?>">
                        <i class="fas <?= $editExercise ? 'fa-edit' : 'fa-plus' ?>"></i>
                        <?= $editExercise ? 'Modifica Esercizio' : 'Aggiungi Esercizio' ?>
                    </button>
                    <?php if ($editExercise): ?>
                        <a href="?" class="btn btn-secondary">Annulla</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabella esercizi -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h4>Libreria Esercizi</h4>
            <?php if (!empty($exercises)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Descrizione</th>
                                <th>Utilizzi</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($exercises as $exercise): ?>
                                <?php
                                $popularityClass = 'secondary';
                                $popularityText = 'Non utilizzato';
                                if ($exercise['usage_count'] > 0) {
                                    if ($exercise['usage_count'] >= 10) {
                                        $popularityClass = 'success';
                                        $popularityText = 'Molto popolare';
                                    } elseif ($exercise['usage_count'] >= 5) {
                                        $popularityClass = 'info';
                                        $popularityText = 'Popolare';
                                    } else {
                                        $popularityClass = 'warning';
                                        $popularityText = 'Poco utilizzato';
                                    }
                                }
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($exercise['name']) ?></strong>
                                    </td>
                                    <td>
                                        <span class="text-muted">
                                            <?= htmlspecialchars(substr($exercise['description'], 0, 80)) ?>
                                            <?= strlen($exercise['description']) > 80 ? '...' : '' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?= $exercise['usage_count'] ?></span>
                                    </td>
                                    <td>
                                        <?php 
                                        // Verifica se l'esercizio è del trainer (può essere modificato/eliminato)
                                        $stmt = $conn->prepare("SELECT trainerID FROM EXERCISE WHERE exerciseID = ?");
                                        $stmt->bind_param('i', $exercise['exerciseID']);
                                        $stmt->execute();
                                        $creator = $stmt->get_result()->fetch_assoc()['trainerID'];
                                        $isOwner = ($creator == $trainerID);
                                        ?>
                                        
                                        <?php if ($isOwner): ?>
                                            <a href="?edit=<?= $exercise['exerciseID'] ?>" class="btn btn-sm btn-warning">
                                                <i class="fas fa-edit"></i> Modifica
                                            </a>
                                            <?php if ($exercise['usage_count'] == 0): ?>
                                                <form method="POST" style="display:inline" onsubmit="return confirm('Sei sicuro di eliminare questo esercizio?');">
                                                    <input type="hidden" name="delete_id" value="<?= $exercise['exerciseID'] ?>">
                                                    <button name="delete_exercise" class="btn btn-sm btn-danger">
                                                        <i class="fas fa-trash"></i> Elimina
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-secondary" disabled title="Impossibile eliminare: esercizio in uso">
                                                    <i class="fas fa-lock"></i> In uso
                                                </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark">
                                                <i class="fas fa-globe"></i> Condiviso
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-dumbbell fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Nessun esercizio disponibile</h5>
                    <p class="text-muted">Aggiungi il primo esercizio alla libreria per iniziare a creare programmi di allenamento.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>