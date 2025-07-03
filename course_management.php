<?php
require_once 'config.php';
session_start();

// Controllo che l'utente sia loggato e abbia il ruolo di admin
if (!isset($_SESSION['userID'], $_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Validazione delle date
function validateDates($startDate, $finishDate) {
    $errors = [];
    // Le date sono obbligatorie
    if (empty($startDate)) {
        $errors[] = 'La data di inizio è obbligatoria.';
    }
    if (empty($finishDate)) {
        $errors[] = 'La data di fine è obbligatoria.';
    }
    // Controlla che la data di inizio sia precedente alla data di fine
    if (!empty($startDate) && !empty($finishDate)) {
        $start = new DateTime($startDate);
        $finish = new DateTime($finishDate);
        if ($start >= $finish) {
            $errors[] = 'La data di inizio deve essere precedente alla data di fine.';
        }
    }
    return $errors;
}

// Gestione POST
$error_message = '';
$success_message = '';
$validation_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validazione comune per add e update
    $dateErrors = validateDates($_POST['startDate'] ?? '', $_POST['finishDate'] ?? '');
    $validation_errors = array_merge($validation_errors, $dateErrors);
    
    // Controllo campi obbligatori
    if (empty(trim($_POST['name'] ?? ''))) {
        $validation_errors[] = 'Il nome del corso è obbligatorio.';
    }
    if (empty($_POST['description'] ?? '') || empty(trim($_POST['description']))) {
        $validation_errors[] = 'La descrizione è obbligatoria.';
    }
    if (empty($_POST['price']) || $_POST['price'] < 0) {
        $validation_errors[] = 'Il prezzo deve essere un valore positivo.';
    }
    if (empty($_POST['maxParticipants']) || $_POST['maxParticipants'] < 1) {
        $validation_errors[] = 'Il numero massimo di partecipanti deve essere almeno 1.';
    }
    if (empty($_POST['trainers'])) {
        $validation_errors[] = 'Devi assegnare almeno un trainer al corso.';
    }
    
    // Se non ci sono errori, si procede con l'operazione
    if (empty($validation_errors)) {
        if (isset($_POST['add'])) {
            // Inserisci il corso
            $stmt = $conn->prepare("INSERT INTO COURSE (name, description, price, maxParticipants, startDate, finishDate) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param(
                'ssdiss',
                $_POST['name'],
                $_POST['description'],
                $_POST['price'],
                $_POST['maxParticipants'],
                $_POST['startDate'],
                $_POST['finishDate']
            );
            if ($stmt->execute()) {
                $courseID = $conn->insert_id;
                // Inserisci le assegnazioni trainer se selezionati
                if (!empty($_POST['trainers'])) {
                    $stmt = $conn->prepare("INSERT INTO teaching (trainerID, courseID) VALUES (?, ?)");
                    foreach ($_POST['trainers'] as $trainerID) {
                        $stmt->bind_param('ii', $trainerID, $courseID);
                        $stmt->execute();
                    }
                }
                $success_message = 'Corso aggiunto con successo!';
                unset($_POST);
            } else {
                $error_message = 'Errore durante l\'inserimento del corso.';
            }
        } elseif (isset($_POST['update'])) {
            // Aggiorna il corso
            $stmt = $conn->prepare("UPDATE COURSE SET name = ?, description = ?, price = ?, maxParticipants = ?, startDate = ?, finishDate = ? WHERE courseID = ?");
            $stmt->bind_param(
                'ssdissi',
                $_POST['name'],
                $_POST['description'],
                $_POST['price'],
                $_POST['maxParticipants'],
                $_POST['startDate'],
                $_POST['finishDate'],
                $_POST['courseID']
            );
            if ($stmt->execute()) {
                // Rimuovi le vecchie assegnazioni
                $stmt = $conn->prepare("DELETE FROM teaching WHERE courseID = ?");
                $stmt->bind_param('i', $_POST['courseID']);
                $stmt->execute();
                // Inserisci le nuove assegnazioni trainer
                if (!empty($_POST['trainers'])) {
                    $stmt = $conn->prepare("INSERT INTO teaching (trainerID, courseID) VALUES (?, ?)");
                    foreach ($_POST['trainers'] as $trainerID) {
                        $stmt->bind_param('ii', $trainerID, $_POST['courseID']);
                        $stmt->execute();
                    }
                }
                $success_message = 'Corso modificato con successo!';
            } else {
                $error_message = 'Errore durante la modifica del corso.';
            }
        }
    }
    if (isset($_POST['delete'])) {
        // Elimina il corso
        $stmt = $conn->prepare("DELETE FROM COURSE WHERE courseID = ?");
        $stmt->bind_param('i', $_POST['delete_id']);
        if ($stmt->execute()) {
            $success_message = 'Corso eliminato con successo!';
        } else {
            $error_message = 'Errore durante l\'eliminazione del corso.';
        }
    }
}

// Recupera tutti i corsi
$stmt = $conn->prepare("SELECT courseID, name, description, price, maxParticipants, startDate, finishDate FROM COURSE ORDER BY startDate DESC");
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Recupera tutti i trainer per il form
$stmt = $conn->prepare("SELECT userID, firstName, lastName FROM USER WHERE role = 'trainer' ORDER BY firstName, lastName");
$stmt->execute();
$trainers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Se è una modifica, recupera i trainer assegnati al corso
$editCourse = null;
$assignedTrainers = [];
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM COURSE WHERE courseID = ?");
    $stmt->bind_param('i', $_GET['edit']);
    $stmt->execute();
    $editCourse = $stmt->get_result()->fetch_assoc();
    
    // Recupera i trainer assegnati
    $stmt = $conn->prepare("SELECT trainerID FROM teaching WHERE courseID = ?");
    $stmt->bind_param('i', $_GET['edit']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $assignedTrainers[] = $row['trainerID'];
    }
}

// Statistiche corsi
function getCourseStats($conn) {
    // Totale corsi
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM COURSE");
    $stmt->execute();
    $totalCourses = $stmt->get_result()->fetch_assoc()['total'];
    
    // Corsi attivi (già iniziati ma non ancora finiti)
    $stmt = $conn->prepare("SELECT COUNT(*) as active FROM COURSE WHERE startDate <= CURDATE() AND finishDate >= CURDATE()");
    $stmt->execute();
    $activeCourses = $stmt->get_result()->fetch_assoc()['active'];
    
    // Prezzo medio corsi
    $stmt = $conn->prepare("SELECT AVG(price) as avgPrice FROM COURSE");
    $stmt->execute();
    $avgPrice = round($stmt->get_result()->fetch_assoc()['avgPrice'], 2);
    
    return [
        'total' => $totalCourses,
        'active' => $activeCourses,
        'avgPrice' => $avgPrice ?: 0
    ];
}

$stats = getCourseStats($conn);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Gestione Corsi</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <h2>Gestione Corsi</h2>
                <a href="dashboard.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i>Torna alla Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Area Statistiche -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <h4>Statistiche Corsi</h4>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="card text-white h-100" style="background: linear-gradient(135deg, #6a85b6 0%, #bac8e0 100%);">
                        <div class="card-body text-center d-flex flex-column justify-content-center">
                            <h3><?= $stats['total'] ?></h3>
                            <p class="mb-0">Corsi Totali</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white h-100" style="background: linear-gradient(135deg, #a8c8ec 0%, #7fcdcd 100%);">
                        <div class="card-body text-center d-flex flex-column justify-content-center">
                            <h3><?= $stats['active'] ?></h3>
                            <p class="mb-0">Corsi Attivi</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white h-100" style="background: linear-gradient(135deg, #7fcdcd 0%, #c2e9fb 100%);">
                        <div class="card-body text-center d-flex flex-column justify-content-center">
                            <h3>€<?= $stats['avgPrice'] ?></h3>
                            <p class="mb-0">Prezzo Medio</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Form aggiunta/modifica corso -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <h4><?= $editCourse ? 'Modifica Corso' : 'Aggiungi Corso' ?></h4>
            
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
                <?php if ($editCourse): ?>
                    <input type="hidden" name="courseID" value="<?= $editCourse['courseID'] ?>">
                <?php endif; ?>
                <div class="col-md-6">
                    <label class="form-label">Nome Corso</label>
                    <input name="name" required class="form-control" type="text" 
                           value="<?= $editCourse ? htmlspecialchars($editCourse['name']) : (isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '') ?>" />
                </div>
                <div class="col-md-6">
                    <label class="form-label">Prezzo (€)</label>
                    <input name="price" required class="form-control" type="number" step="0.01" min="0"
                           value="<?= $editCourse ? $editCourse['price'] : (isset($_POST['price']) ? $_POST['price'] : '') ?>" />
                </div>
                <div class="col-md-6">
                    <label class="form-label">Max Partecipanti</label>
                    <input name="maxParticipants" required class="form-control" type="number" min="1"
                           value="<?= $editCourse ? $editCourse['maxParticipants'] : (isset($_POST['maxParticipants']) ? $_POST['maxParticipants'] : '20') ?>" />
                </div>
                <div class="col-md-6">
                    <label class="form-label">Data Inizio</label>
                    <input name="startDate" required class="form-control" type="date"
                           value="<?= $editCourse ? $editCourse['startDate'] : (isset($_POST['startDate']) ? $_POST['startDate'] : '') ?>" />
                </div>
                <div class="col-md-6">
                    <label class="form-label">Data Fine</label>
                    <input name="finishDate" required class="form-control" type="date"
                           value="<?= $editCourse ? $editCourse['finishDate'] : (isset($_POST['finishDate']) ? $_POST['finishDate'] : '') ?>" />
                </div>
                <div class="col-md-6">
                    <label class="form-label">Trainer Assegnati</label>
                    <select name="trainers[]" class="form-select" multiple size="4" required>
                        <?php foreach($trainers as $trainer): ?>
                            <option value="<?= $trainer['userID'] ?>" 
                                <?= (in_array($trainer['userID'], $assignedTrainers) || (isset($_POST['trainers']) && in_array($trainer['userID'], $_POST['trainers']))) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($trainer['firstName'] . ' ' . $trainer['lastName']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Tieni premuto Ctrl/Cmd per selezionare più trainer</small>
                </div>
                <div class="col-12">
                    <label class="form-label">Descrizione</label>
                    <textarea name="description" required class="form-control" rows="3"><?= $editCourse ? htmlspecialchars($editCourse['description']) : (isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '') ?></textarea>
                </div>
                <div class="col-12">
                    <button name="<?= $editCourse ? 'update' : 'add' ?>" class="btn <?= $editCourse ? 'btn-warning' : 'btn-success' ?>">
                        <?= $editCourse ? 'Modifica Corso' : 'Aggiungi Corso' ?>
                    </button>
                    <?php if ($editCourse): ?>
                        <a href="?" class="btn btn-secondary">Annulla</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabella corsi -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h4>Corsi Registrati</h4>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Nome</th><th>Prezzo</th><th>Max Partecipanti</th>
                            <th>Data Inizio</th><th>Data Fine</th><th>Stato</th><th>Trainer</th><th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($courses as $course): ?>
                            <?php
                            // Recupera i trainer per questo corso
                            $stmt = $conn->prepare("SELECT u.firstName, u.lastName FROM teaching t JOIN USER u ON t.trainerID = u.userID WHERE t.courseID = ?");
                            $stmt->bind_param('i', $course['courseID']);
                            $stmt->execute();
                            $courseTrainers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                            $trainerNames = array_map(function($t) { return $t['firstName'] . ' ' . $t['lastName']; }, $courseTrainers);
                            
                            // Determina lo stato del corso
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
                                <td><?= htmlspecialchars($course['name']) ?></td>
                                <td>€<?= number_format($course['price'], 2) ?></td>
                                <td><?= $course['maxParticipants'] ?></td>
                                <td><?= $course['startDate'] ? date('d/m/Y', strtotime($course['startDate'])) : '-' ?></td>
                                <td><?= $course['finishDate'] ? date('d/m/Y', strtotime($course['finishDate'])) : '-' ?></td>
                                <td><span class="<?= $statusClass ?>"><?= $status ?></span></td>
                                <td><?= !empty($trainerNames) ? htmlspecialchars(implode(', ', $trainerNames)) : 'Nessuno' ?></td>
                                <td>
                                    <a href="?edit=<?= $course['courseID'] ?>" class="btn btn-sm btn-warning">
                                        Modifica
                                    </a>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Sei sicuro di eliminare questo corso? Questa azione non può essere annullata.');">
                                        <input type="hidden" name="delete_id" value="<?= $course['courseID'] ?>">
                                        <button name="delete" class="btn btn-sm btn-danger">
                                            Elimina
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($courses)): ?>
                            <tr><td colspan="8" class="text-center">Nessun corso registrato.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>