<?php
require_once 'config.php';
session_start();

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
                $stmt = $conn->prepare("INSERT INTO EXERCISES (name, description, trainerID) VALUES (?, ?, ?)");
                $stmt->bind_param('ssi', $name, $description, $trainerID);
                if ($stmt->execute()) {
                    $success_message = "Esercizio aggiunto con successo!";
                    unset($_POST);
                } else {
                    $error_message = "Errore durante l'inserimento dell'esercizio.";
                }
            } elseif (isset($_POST['update_exercise'])) {
                $exerciseID = (int)$_POST['exerciseID'];
                $stmt = $conn->prepare("SELECT exerciseID FROM EXERCISES WHERE exerciseID = ? AND trainerID = ?");
                $stmt->bind_param('ii', $exerciseID, $trainerID);
                $stmt->execute();

                if ($stmt->get_result()->num_rows === 0) {
                    $error_message = "Puoi modificare solo i tuoi esercizi.";
                } else {
                    $stmt = $conn->prepare("UPDATE EXERCISES SET name = ?, description = ? WHERE exerciseID = ? AND trainerID = ?");
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
            $stmt = $conn->prepare("SELECT exerciseID FROM EXERCISES WHERE exerciseID = ? AND trainerID = ?");
            $stmt->bind_param('ii', $deleteID, $trainerID);
            $stmt->execute();

            if ($stmt->get_result()->num_rows === 0) {
                $error_message = "Puoi eliminare solo i tuoi esercizi.";
            } else {
                $stmt = $conn->prepare("DELETE FROM EXERCISES WHERE exerciseID = ? AND trainerID = ?");
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

// Recupera esercizi
$stmt = $conn->prepare("
    SELECT e.exerciseID, e.name, e.description, COUNT(ed.exerciseDetailID) as usage_count
    FROM EXERCISES e
    LEFT JOIN EXERCISE_DETAILS ed ON e.exerciseID = ed.exerciseID
    LEFT JOIN TRAINING_DAYS td ON ed.trainingDayID = td.trainingDayID
    LEFT JOIN TRAINING_SCHEDULES ts ON td.trainingScheduleID = ts.trainingScheduleID AND ts.trainerID = ?
    WHERE e.trainerID = ? OR e.trainerID IS NULL
    GROUP BY e.exerciseID, e.name, e.description
    ORDER BY e.name ASC
");
$stmt->bind_param('ii', $trainerID, $trainerID);
$stmt->execute();
$exercises = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Statistiche
function getExerciseStats($conn, $trainerID) {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM EXERCISES WHERE trainerID = ?");
    $stmt->bind_param('i', $trainerID);
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];

    // Contare gli esercizi utilizzati
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT e.exerciseID) as used
        FROM EXERCISES e
        JOIN EXERCISE_DETAILS ed ON e.exerciseID = ed.exerciseID
        JOIN TRAINING_DAYS td ON ed.trainingDayID = td.trainingDayID
        JOIN TRAINING_SCHEDULES ts ON td.trainingScheduleID = ts.trainingScheduleID
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
    $stmt = $conn->prepare("SELECT * FROM EXERCISES WHERE exerciseID = ? AND trainerID = ?");
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
                           value="<?= $editExercise ? htmlspecialchars($editExercise['name']) : (isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '') ?>" />
                </div>
                <div class="col-12">
                    <label class="form-label">Descrizione</label>
                    <textarea name="description" required class="form-control" rows="4"><?= $editExercise ? htmlspecialchars($editExercise['description']) : (isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '') ?></textarea>
                </div>
                <div class="col-12">
                    <button name="<?= $editExercise ? 'update_exercise' : 'add_exercise' ?>" class="btn <?= $editExercise ? 'btn-warning' : 'btn-success' ?>">
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
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($exercise['name']) ?>
                                    </td>
                                    <td>
                                        <span class="text-muted">
                                            <?= htmlspecialchars(substr($exercise['description'], 0, 80)) ?>
                                            <?= strlen($exercise['description']) > 80 ? '...' : '' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= $exercise['usage_count'] ?>
                                    </td>
                                    <td>
                                        <?php 
                                        // Verifica se l'esercizio è del trainer
                                        $stmt = $conn->prepare("SELECT trainerID FROM EXERCISES WHERE exerciseID = ?");
                                        $stmt->bind_param('i', $exercise['exerciseID']);
                                        $stmt->execute();
                                        $creator = $stmt->get_result()->fetch_assoc()['trainerID'];
                                        $isOwner = ($creator == $trainerID);
                                        ?>
                                        
                                        <?php if ($isOwner): ?>
                                            <a href="?edit=<?= $exercise['exerciseID'] ?>" class="btn btn-sm btn-warning">
                                                Modifica
                                            </a>
                                            <form method="POST" style="display:inline" onsubmit="return confirm('Sei sicuro di eliminare questo esercizio?');">
                                                <input type="hidden" name="delete_id" value="<?= $exercise['exerciseID'] ?>">
                                                <button name="delete_exercise" class="btn btn-sm btn-danger">
                                                    Elimina
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark">
                                                Condiviso
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
                    <h5 class="text-muted">Nessun esercizio disponibile</h5>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>