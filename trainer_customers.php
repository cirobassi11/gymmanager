<?php
require_once 'config.php';
session_start();

// Controllo accesso trainer
if (!isset($_SESSION['userID'], $_SESSION['role']) || $_SESSION['role'] !== 'trainer') {
    header('Location: login.php');
    exit();
}

$trainerID = $_SESSION['userID'];

// Raggruppamento corsi per cliente
$stmt = $conn->prepare("
    SELECT DISTINCT u.userID, u.firstName, u.lastName, u.email, u.phoneNumber, u.birthDate, u.gender,
           GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ') as course_names
    FROM USERS u
    JOIN ENROLLMENTS e ON u.userID = e.customerID
    JOIN COURSES c ON e.courseID = c.courseID
    JOIN TEACHINGS t ON c.courseID = t.courseID
    WHERE t.trainerID = ? AND u.role = 'customer'
    GROUP BY u.userID, u.firstName, u.lastName, u.email, u.phoneNumber, u.birthDate, u.gender
    ORDER BY u.firstName, u.lastName
");
$stmt->bind_param('i', $trainerID);
$stmt->execute();
$customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Se stiamo visualizzando i progressi di un cliente
$viewCustomerProgress = null;
$customerProgressData = [];
if (isset($_GET['view_progress']) && is_numeric($_GET['view_progress'])) {
    $customerID = (int)$_GET['view_progress'];
    
    // Verifica che il cliente sia seguito dal trainer
    $stmt = $conn->prepare("
        SELECT DISTINCT u.userID, u.firstName, u.lastName
        FROM USERS u
        JOIN ENROLLMENTS e ON u.userID = e.customerID
        JOIN COURSES c ON e.courseID = c.courseID
        JOIN TEACHINGS t ON c.courseID = t.courseID
        WHERE t.trainerID = ? AND u.userID = ? AND u.role = 'customer'
    ");
    $stmt->bind_param('ii', $trainerID, $customerID);
    $stmt->execute();
    $viewCustomerProgress = $stmt->get_result()->fetch_assoc();
    
    if ($viewCustomerProgress) {
        // Recupera i progress report del cliente
        $stmt = $conn->prepare("
            SELECT * FROM PROGRESS_REPORTS 
            WHERE customerID = ? 
            ORDER BY date DESC
            LIMIT 10
        ");
        $stmt->bind_param('i', $customerID);
        $stmt->execute();
        $customerProgressData = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

// Calcola età
function calcAge($birthDate) {
    if (empty($birthDate)) return 'N/A';
    try {
        $birth = new DateTime($birthDate);
        $now = new DateTime();
        return $now->diff($birth)->y;
    } catch (Exception $e) {
        return 'N/A';
    }
}

// Statistiche trainer
function getTrainerCustomerStats($conn, $trainerID) {
    // Totale clienti seguiti
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT e.customerID) as total
        FROM ENROLLMENTS e
        JOIN COURSES c ON e.courseID = c.courseID
        JOIN TEACHINGS t ON c.courseID = t.courseID
        WHERE t.trainerID = ? AND c.finishDate >= CURDATE()
    ");
    $stmt->bind_param('i', $trainerID);
    $stmt->execute();
    $totalCustomers = $stmt->get_result()->fetch_assoc()['total'];
    
    // Clienti attivi questo mese
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT e.customerID) as active
        FROM ENROLLMENTS e
        JOIN COURSES c ON e.courseID = c.courseID
        JOIN TEACHINGS t ON c.courseID = t.courseID
        WHERE t.trainerID = ? 
        AND c.finishDate >= CURDATE()
        AND e.enrollmentDate >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $stmt->bind_param('i', $trainerID);
    $stmt->execute();
    $activeThisMonth = $stmt->get_result()->fetch_assoc()['active'];
    
    // Media età clienti
    $stmt = $conn->prepare("
        SELECT ROUND(AVG(TIMESTAMPDIFF(YEAR, u.birthDate, CURDATE())), 1) as avgAge
        FROM USERS u
        JOIN ENROLLMENTS e ON u.userID = e.customerID
        JOIN COURSES c ON e.courseID = c.courseID
        JOIN TEACHINGS t ON c.courseID = t.courseID
        WHERE t.trainerID = ? 
        AND u.birthDate IS NOT NULL 
        AND c.finishDate >= CURDATE()
    ");
    $stmt->bind_param('i', $trainerID);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $avgAge = $result['avgAge'] ?? 0;
    
    return [
        'total' => $totalCustomers,
        'activeThisMonth' => $activeThisMonth,
        'avgAge' => $avgAge
    ];
}

$stats = getTrainerCustomerStats($conn, $trainerID);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Clienti Seguiti</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2>Clienti Seguiti</h2>
                </div>
                <div>
                    <?php if (isset($_GET['view_progress'])): ?>
                        <a href="?" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-arrow-left me-2"></i>Torna ai Clienti
                        </a>
                    <?php endif; ?>
                    <a href="dashboard.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i>Torna alla Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if (!isset($_GET['view_progress'])): ?>
        <!-- Area Statistiche -->
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <h4>Statistiche Clienti</h4>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="card text-white h-100" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                            <div class="card-body text-center d-flex flex-column justify-content-center">
                                <h3><?= $stats['total'] ?></h3>
                                <p class="mb-0">Clienti Totali</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white h-100" style="background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);">
                            <div class="card-body text-center d-flex flex-column justify-content-center">
                                <h3><?= $stats['activeThisMonth'] ?></h3>
                                <p class="mb-0">Nuovi iscritti in questo mese</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white h-100" style="background: linear-gradient(135deg, #fd7e14 0%, #e83e8c 100%);">
                            <div class="card-body text-center d-flex flex-column justify-content-center">
                                <h3><?= $stats['avgAge'] ?></h3>
                                <p class="mb-0">Età Media</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista Clienti -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h4>I Tuoi Clienti</h4>
                <?php if (!empty($customers)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Età</th>
                                    <th>Contatti</th>
                                    <th>Genere</th>
                                    <th>Corsi Seguiti</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customers as $customer): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($customer['firstName'] . ' ' . $customer['lastName']) ?></strong>
                                    </td>
                                    <td><?= calcAge($customer['birthDate']) ?> anni</td>
                                    <td>
                                        <div><?= htmlspecialchars($customer['email']) ?></div>
                                        <?php if ($customer['phoneNumber']): ?>
                                            <small class="text-muted"><?= htmlspecialchars($customer['phoneNumber']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($customer['gender']) ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark">
                                            <?= htmlspecialchars($customer['course_names']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="?view_progress=<?= $customer['userID'] ?>" class="btn btn-sm btn-info">
                                            Vedi Progressi
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <h5 class="text-muted">Nessun cliente iscritto ai tuoi corsi</h5>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($viewCustomerProgress): ?>
        <!-- Visualizzazione progressi cliente -->
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <h3>Progressi di <?= htmlspecialchars($viewCustomerProgress['firstName'] . ' ' . $viewCustomerProgress['lastName']) ?></h3>
                
                <?php if (!empty($customerProgressData)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Peso (kg)</th>
                                    <th>Grasso Corporeo (%)</th>
                                    <th>Massa Muscolare (kg)</th>
                                    <th>BMI</th>
                                    <th>Note</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($customerProgressData as $progress): ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($progress['date'])) ?></td>
                                    <td><?= $progress['weight'] ? number_format($progress['weight'], 1) : '-' ?></td>
                                    <td><?= $progress['bodyFatPercent'] ? number_format($progress['bodyFatPercent'], 1) : '-' ?></td>
                                    <td><?= $progress['muscleMass'] ? number_format($progress['muscleMass'], 1) : '-' ?></td>
                                    <td><?= $progress['bmi'] ? number_format($progress['bmi'], 1) : '-' ?></td>
                                    <td>
                                        <?php if ($progress['description']): ?>
                                            <span title="<?= htmlspecialchars($progress['description']) ?>">
                                                <?= htmlspecialchars(substr($progress['description'], 0, 50)) ?>
                                                <?= strlen($progress['description']) > 50 ? '...' : '' ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if (count($customerProgressData) >= 10): ?>
                        <div class="text-center mt-3">
                            <small class="text-muted">Mostrati gli ultimi 10 report di progresso</small>
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="text-center py-5">
                        <h5 class="text-muted">Nessuna misurazione di progresso</h5>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php else: ?>
        <!-- Cliente non trovato o non autorizzato -->
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <h5 class="text-danger">Cliente non trovato</h5>
                <p class="text-muted">Il cliente richiesto non esiste o non è tra quelli che segui.</p>
                <a href="?" class="btn btn-primary">Torna alla lista clienti</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>