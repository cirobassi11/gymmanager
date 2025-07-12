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
    if (isset($_POST['add_progress'])) {
        // Validazione dati
        $date = $_POST['date'];
        $weight = !empty($_POST['weight']) ? (float)$_POST['weight'] : null;
        $bodyFatPercent = !empty($_POST['bodyFatPercent']) ? (float)$_POST['bodyFatPercent'] : null;
        $muscleMass = !empty($_POST['muscleMass']) ? (float)$_POST['muscleMass'] : null;
        $bmi = !empty($_POST['bmi']) ? (float)$_POST['bmi'] : null;
        $description = trim($_POST['description'] ?? '');

        if (empty($date)) {
            $validation_errors[] = 'La data è obbligatoria.';
        }

        if ($date > date('Y-m-d')) {
            $validation_errors[] = 'La data non può essere futura.';
        }

        if ($weight !== null && ($weight <= 0 || $weight > 500)) {
            $validation_errors[] = 'Il peso deve essere compreso tra 1 e 500 kg.';
        }

        if ($bodyFatPercent !== null && ($bodyFatPercent < 0 || $bodyFatPercent > 100)) {
            $validation_errors[] = 'La percentuale di grasso corporeo deve essere tra 0 e 100%.';
        }

        if ($muscleMass !== null && ($muscleMass <= 0 || $muscleMass > 200)) {
            $validation_errors[] = 'La massa muscolare deve essere compresa tra 1 e 200 kg.';
        }

        if ($bmi !== null && ($bmi < 10 || $bmi > 60)) {
            $validation_errors[] = 'Il BMI deve essere compreso tra 10 e 60.';
        }

        // Verifica che almeno un valore sia stato inserito
        if ($weight === null && $bodyFatPercent === null && $muscleMass === null && $bmi === null) {
            $validation_errors[] = 'Inserisci almeno un valore di misurazione.';
        }

        // Verifica che non esista già un report per quella data
        $stmt = $conn->prepare("SELECT progressReportID FROM PROGRESS_REPORTS WHERE customerID = ? AND date = ?");
        $stmt->bind_param('is', $customerID, $date);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $validation_errors[] = 'Esiste già un report per questa data. Modifica quello esistente.';
        }

        if (empty($validation_errors)) {
            // Inserisci il nuovo report
            $stmt = $conn->prepare("
                INSERT INTO PROGRESS_REPORTS (date, description, weight, bodyFatPercent, muscleMass, bmi, customerID) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                'ssddddi',
                $date,
                $description,
                $weight,
                $bodyFatPercent,
                $muscleMass,
                $bmi,
                $customerID
            );

            if ($stmt->execute()) {
                $success_message = 'Report dei progressi aggiunto con successo!';
                unset($_POST);
            } else {
                $error_message = 'Errore durante l\'inserimento del report.';
            }
        }
    } elseif (isset($_POST['update_progress'])) {
        $progressReportID = (int)$_POST['progressReportID'];
        $date = $_POST['date'];
        $weight = !empty($_POST['weight']) ? (float)$_POST['weight'] : null;
        $bodyFatPercent = !empty($_POST['bodyFatPercent']) ? (float)$_POST['bodyFatPercent'] : null;
        $muscleMass = !empty($_POST['muscleMass']) ? (float)$_POST['muscleMass'] : null;
        $bmi = !empty($_POST['bmi']) ? (float)$_POST['bmi'] : null;
        $description = trim($_POST['description'] ?? '');

        // Validazioni simili a quelle per l'inserimento
        if (empty($date)) {
            $validation_errors[] = 'La data è obbligatoria.';
        }

        if ($date > date('Y-m-d')) {
            $validation_errors[] = 'La data non può essere futura.';
        }

        if ($weight !== null && ($weight <= 0 || $weight > 500)) {
            $validation_errors[] = 'Il peso deve essere compreso tra 1 e 500 kg.';
        }

        if ($bodyFatPercent !== null && ($bodyFatPercent < 0 || $bodyFatPercent > 100)) {
            $validation_errors[] = 'La percentuale di grasso corporeo deve essere tra 0 e 100%.';
        }

        if ($muscleMass !== null && ($muscleMass <= 0 || $muscleMass > 200)) {
            $validation_errors[] = 'La massa muscolare deve essere compresa tra 1 e 200 kg.';
        }

        if ($bmi !== null && ($bmi < 10 || $bmi > 60)) {
            $validation_errors[] = 'Il BMI deve essere compreso tra 10 e 60.';
        }

        if ($weight === null && $bodyFatPercent === null && $muscleMass === null && $bmi === null) {
            $validation_errors[] = 'Inserisci almeno un valore di misurazione.';
        }

        if (empty($validation_errors)) {
            // Aggiorna il report esistente
            $stmt = $conn->prepare("
                UPDATE PROGRESS_REPORTS 
                SET date = ?, description = ?, weight = ?, bodyFatPercent = ?, muscleMass = ?, bmi = ?
                WHERE progressReportID = ? AND customerID = ?
            ");
            $stmt->bind_param(
                'ssddddi',
                $date,
                $description,
                $weight,
                $bodyFatPercent,
                $muscleMass,
                $bmi,
                $progressReportID,
                $customerID
            );

            if ($stmt->execute()) {
                $success_message = 'Report dei progressi modificato con successo!';
            } else {
                $error_message = 'Errore durante la modifica del report.';
            }
        }
    } elseif (isset($_POST['delete_progress'])) {
        $deleteID = (int)$_POST['delete_id'];
        if ($deleteID > 0) {
            $stmt = $conn->prepare("DELETE FROM PROGRESS_REPORTS WHERE progressReportID = ? AND customerID = ?");
            $stmt->bind_param('ii', $deleteID, $customerID);
            if ($stmt->execute()) {
                $success_message = 'Report eliminato con successo!';
            } else {
                $error_message = 'Errore durante l\'eliminazione del report.';
            }
        }
    }
}

// Recupera tutti i report del cliente
$stmt = $conn->prepare("
    SELECT * FROM PROGRESS_REPORTS 
    WHERE customerID = ? 
    ORDER BY date DESC
");
$stmt->bind_param('i', $customerID);
$stmt->execute();
$progressReports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Se è una modifica, recupera i dati del report
$editReport = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $reportID = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM PROGRESS_REPORTS WHERE progressReportID = ? AND customerID = ?");
    $stmt->bind_param('ii', $reportID, $customerID);
    $stmt->execute();
    $editReport = $stmt->get_result()->fetch_assoc();
}

// Informazioni cliente
$stmt = $conn->prepare("SELECT firstName, lastName, email FROM USERS WHERE userID = ?");
$stmt->bind_param('i', $customerID);
$stmt->execute();
$customerInfo = $stmt->get_result()->fetch_assoc();

// Statistiche e calcoli
$totalReports = count($progressReports);
$latestReport = !empty($progressReports) ? $progressReports[0] : null;
$firstReport = !empty($progressReports) ? end($progressReports) : null;

// Calcola variazioni se ci sono almeno 2 report
$weightChange = null;
$bodyFatChange = null;
$muscleMassChange = null;
$bmiChange = null;

if ($totalReports >= 2) {
    if ($latestReport['weight'] && $firstReport['weight']) {
        $weightChange = $latestReport['weight'] - $firstReport['weight'];
    }
    if ($latestReport['bodyFatPercent'] && $firstReport['bodyFatPercent']) {
        $bodyFatChange = $latestReport['bodyFatPercent'] - $firstReport['bodyFatPercent'];
    }
    if ($latestReport['muscleMass'] && $firstReport['muscleMass']) {
        $muscleMassChange = $latestReport['muscleMass'] - $firstReport['muscleMass'];
    }
    if ($latestReport['bmi'] && $firstReport['bmi']) {
        $bmiChange = $latestReport['bmi'] - $firstReport['bmi'];
    }
}

// Prepara dati per i grafici (ultimi 12 report)
$chartData = array_slice(array_reverse($progressReports), 0, 12);
$chartLabels = array_map(function($report) {
    return date('d/m/Y', strtotime($report['date']));
}, $chartData);

$weightData = array_map(function($report) {
    return $report['weight'] ?: 'null';
}, $chartData);

$bodyFatData = array_map(function($report) {
    return $report['bodyFatPercent'] ?: 'null';
}, $chartData);

$muscleMassData = array_map(function($report) {
    return $report['muscleMass'] ?: 'null';
}, $chartData);

$bmiData = array_map(function($report) {
    return $report['bmi'] ?: 'null';
}, $chartData);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>I Tuoi Progressi</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
</head>
<body class="bg-light">
<div class="container py-5">
    <!-- Header -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2>I Tuoi Progressi</h2>
                </div>
                <a href="dashboard.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i>Torna alla Dashboard
                </a>
            </div>
        </div>
    </div>

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

    <!-- Statistiche Riepilogative -->
    <?php if ($latestReport): ?>
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <h4>Ultime Misurazioni</h4>
                <div class="row g-3">
                    <?php if ($latestReport['weight']): ?>
                    <div class="col-md-3">
                        <div class="card text-white h-100" style="background: linear-gradient(135deg, #6a85b6 0%, #bac8e0 100%);">
                            <div class="card-body text-center d-flex flex-column justify-content-center">
                                <h3><?= number_format($latestReport['weight'], 1) ?> kg</h3>
                                <p class="mb-0">Peso</p>
                                <?php if ($weightChange !== null): ?>
                                    <small class="<?= $weightChange >= 0 ? 'text-warning' : 'text-success' ?>">
                                        <?= $weightChange >= 0 ? '+' : '' ?><?= number_format($weightChange, 1) ?> kg
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($latestReport['bodyFatPercent']): ?>
                    <div class="col-md-3">
                        <div class="card text-white h-100" style="background: linear-gradient(135deg, #a8c8ec 0%, #7fcdcd 100%);">
                            <div class="card-body text-center d-flex flex-column justify-content-center">
                                <h3><?= number_format($latestReport['bodyFatPercent'], 1) ?>%</h3>
                                <p class="mb-0">Grasso Corporeo</p>
                                <?php if ($bodyFatChange !== null): ?>
                                    <small class="<?= $bodyFatChange <= 0 ? 'text-success' : 'text-warning' ?>">
                                        <?= $bodyFatChange >= 0 ? '+' : '' ?><?= number_format($bodyFatChange, 1) ?>%
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($latestReport['muscleMass']): ?>
                    <div class="col-md-3">
                        <div class="card text-white h-100" style="background: linear-gradient(135deg, #7fcdcd 0%, #c2e9fb 100%);">
                            <div class="card-body text-center d-flex flex-column justify-content-center">
                                <h3><?= number_format($latestReport['muscleMass'], 1) ?> kg</h3>
                                <p class="mb-0">Massa Muscolare</p>
                                <?php if ($muscleMassChange !== null): ?>
                                    <small class="<?= $muscleMassChange >= 0 ? 'text-success' : 'text-warning' ?>">
                                        <?= $muscleMassChange >= 0 ? '+' : '' ?><?= number_format($muscleMassChange, 1) ?> kg
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($latestReport['bmi']): ?>
                    <div class="col-md-3">
                        <div class="card text-white h-100" style="background: linear-gradient(135deg, #c2e9fb 0%, #a8c8ec 100%);">
                            <div class="card-body text-center d-flex flex-column justify-content-center">
                                <h3><?= number_format($latestReport['bmi'], 1) ?></h3>
                                <p class="mb-0">BMI</p>
                                <?php if ($bmiChange !== null): ?>
                                    <small class="text-light">
                                        <?= $bmiChange >= 0 ? '+' : '' ?><?= number_format($bmiChange, 1) ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($totalReports >= 2): ?>
                    <div class="row mt-3">
                        <div class="col-12">
                            <small class="text-muted">
                                Confronto con la prima misurazione del <?= date('d/m/Y', strtotime($firstReport['date'])) ?>
                            </small>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Grafici -->
    <?php if (count($chartData) >= 2): ?>
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <h4>Andamento nel Tempo</h4>
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <h6>Peso e BMI</h6>
                        <div style="position: relative; height: 300px;">
                            <canvas id="weightBmiChart"></canvas>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <h6>Composizione Corporea</h6>
                        <div style="position: relative; height: 300px;">
                            <canvas id="bodyCompositionChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Form aggiunta/modifica report -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <h4><?= $editReport ? 'Modifica Report' : 'Aggiungi Nuovo Report' ?></h4>
            <form method="POST" class="row g-3">
                <?php if ($editReport): ?>
                    <input type="hidden" name="progressReportID" value="<?= $editReport['progressReportID'] ?>">
                <?php endif; ?>
                
                <div class="col-md-6">
                    <label class="form-label">Data</label>
                    <input name="date" required class="form-control" type="date" 
                           max="<?= date('Y-m-d') ?>"
                           value="<?= $editReport ? $editReport['date'] : (isset($_POST['date']) ? $_POST['date'] : date('Y-m-d')) ?>" />
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Peso (kg)</label>
                    <input name="weight" class="form-control" type="number" step="0.1" min="1" max="500"
                           placeholder="es. 70.5"
                           value="<?= $editReport ? $editReport['weight'] : (isset($_POST['weight']) ? $_POST['weight'] : '') ?>" />
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Grasso Corporeo (%)</label>
                    <input name="bodyFatPercent" class="form-control" type="number" step="0.1" min="0" max="100"
                           placeholder="es. 15.2"
                           value="<?= $editReport ? $editReport['bodyFatPercent'] : (isset($_POST['bodyFatPercent']) ? $_POST['bodyFatPercent'] : '') ?>" />
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Massa Muscolare (kg)</label>
                    <input name="muscleMass" class="form-control" type="number" step="0.1" min="1" max="200"
                           placeholder="es. 45.8"
                           value="<?= $editReport ? $editReport['muscleMass'] : (isset($_POST['muscleMass']) ? $_POST['muscleMass'] : '') ?>" />
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">BMI</label>
                    <input name="bmi" class="form-control" type="number" step="0.1" min="10" max="60"
                           placeholder="es. 22.1"
                           value="<?= $editReport ? $editReport['bmi'] : (isset($_POST['bmi']) ? $_POST['bmi'] : '') ?>" />
                </div>
                
                <div class="col-12">
                    <label class="form-label">Note (opzionale)</label>
                    <textarea name="description" class="form-control" rows="3"
                              placeholder="Aggiungi note sui tuoi progressi, sensazioni, obiettivi..."><?= $editReport ? htmlspecialchars($editReport['description']) : (isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '') ?></textarea>
                </div>
                
                <div class="col-12">
                    <button name="<?= $editReport ? 'update_progress' : 'add_progress' ?>" class="btn <?= $editReport ? 'btn-warning' : 'btn-success' ?>">
                        <?= $editReport ? 'Modifica Report' : 'Aggiungi Report' ?>
                    </button>
                    <?php if ($editReport): ?>
                        <a href="?" class="btn btn-secondary">Annulla</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Storico Report -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h4>Storico Progressi (<?= $totalReports ?> report)</h4>
            <?php if (!empty($progressReports)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Peso</th>
                                <th>Grasso Corporeo</th>
                                <th>Massa Muscolare</th>
                                <th>BMI</th>
                                <th>Note</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($progressReports as $report): ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($report['date'])) ?></td>
                                    <td><?= $report['weight'] ? number_format($report['weight'], 1) . ' kg' : '-' ?></td>
                                    <td><?= $report['bodyFatPercent'] ? number_format($report['bodyFatPercent'], 1) . '%' : '-' ?></td>
                                    <td><?= $report['muscleMass'] ? number_format($report['muscleMass'], 1) . ' kg' : '-' ?></td>
                                    <td><?= $report['bmi'] ? number_format($report['bmi'], 1) : '-' ?></td>
                                    <td>
                                        <?php if ($report['description']): ?>
                                            <span title="<?= htmlspecialchars($report['description']) ?>">
                                                <?= htmlspecialchars(substr($report['description'], 0, 30)) ?>
                                                <?= strlen($report['description']) > 30 ? '...' : '' ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="?edit=<?= $report['progressReportID'] ?>" class="btn btn-sm btn-warning">
                                            Modifica
                                        </a>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('Sei sicuro di eliminare questo report?');">
                                            <input type="hidden" name="delete_id" value="<?= $report['progressReportID'] ?>">
                                            <button name="delete_progress" class="btn btn-sm btn-danger">
                                                Elimina
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <h5 class="text-muted">Nessun report di progresso</h5>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>

<?php if (count($chartData) >= 2): ?>
<script>
// Dati per i grafici
const chartLabels = <?= json_encode($chartLabels) ?>;
const weightData = [<?= implode(',', $weightData) ?>];
const bmiData = [<?= implode(',', $bmiData) ?>];
const bodyFatData = [<?= implode(',', $bodyFatData) ?>];
const muscleMassData = [<?= implode(',', $muscleMassData) ?>];

// Configurazione comune per i grafici
const commonOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'top',
        }
    },
    scales: {
        y: {
            beginAtZero: false,
            grid: {
                color: 'rgba(0,0,0,0.1)'
            }
        },
        x: {
            grid: {
                color: 'rgba(0,0,0,0.1)'
            }
        }
    },
    interaction: {
        intersect: false,
        mode: 'index'
    },
    elements: {
        point: {
            radius: 4,
            hoverRadius: 6
        },
        line: {
            tension: 0.2
        }
    }
};

// Grafico Peso e BMI
new Chart(document.getElementById('weightBmiChart'), {
    type: 'line',
    data: {
        labels: chartLabels,
        datasets: [{
            label: 'Peso (kg)',
            data: weightData,
            borderColor: '#6a85b6',
            backgroundColor: 'rgba(106, 133, 182, 0.1)',
            yAxisID: 'y'
        }, {
            label: 'BMI',
            data: bmiData,
            borderColor: '#a8c8ec',
            backgroundColor: 'rgba(168, 200, 236, 0.1)',
            yAxisID: 'y1'
        }]
    },
    options: {
        ...commonOptions,
        scales: {
            ...commonOptions.scales,
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                grid: {
                    drawOnChartArea: false,
                },
            }
        }
    }
});

// Grafico Composizione Corporea
new Chart(document.getElementById('bodyCompositionChart'), {
    type: 'line',
    data: {
        labels: chartLabels,
        datasets: [{
            label: 'Grasso Corporeo (%)',
            data: bodyFatData,
            borderColor: '#7fcdcd',
            backgroundColor: 'rgba(127, 205, 205, 0.1)'
        }, {
            label: 'Massa Muscolare (kg)',
            data: muscleMassData,
            borderColor: '#c2e9fb',
            backgroundColor: 'rgba(194, 233, 251, 0.1)'
        }]
    },
    options: commonOptions
});
</script>
<?php endif; ?>
</body>
</html>