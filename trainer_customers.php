<?php
require_once 'config.php';
session_start();

// Controllo accesso trainer
if (!isset($_SESSION['userID'], $_SESSION['role']) || $_SESSION['role'] !== 'trainer') {
    header('Location: login.php');
    exit();
}

$trainerID = $_SESSION['userID'];

// Recupera i clienti seguiti dal trainer attraverso i corsi (query semplice)
$stmt = $conn->prepare("
    SELECT DISTINCT u.userID, u.firstName, u.lastName, u.email, u.phoneNumber, u.birthDate, u.gender,
           e.enrollmentDate, c.name as course_name, c.courseID, c.finishDate
    FROM USER u
    JOIN enrollment e ON u.userID = e.customerID
    JOIN COURSE c ON e.courseID = c.courseID
    JOIN teaching t ON c.courseID = t.courseID
    WHERE t.trainerID = ? AND u.role = 'customer'
    ORDER BY u.firstName, u.lastName, e.enrollmentDate DESC
");
$stmt->bind_param('i', $trainerID);
$stmt->execute();
$customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Raggruppa i clienti per evitare duplicati e calcola lo stato in PHP
$groupedCustomers = [];
foreach ($customers as $customer) {
    $customerID = $customer['userID'];
    if (!isset($groupedCustomers[$customerID])) {
        $groupedCustomers[$customerID] = [
            'info' => $customer,
            'courses' => []
        ];
    }
    
    // Calcola lo stato del corso in PHP
    $today = new DateTime();
    $finishDate = new DateTime($customer['finishDate']);
    
    if ($finishDate >= $today) {
        $status = 'active';
    } else {
        $status = 'completed';
    }
    
    $groupedCustomers[$customerID]['courses'][] = [
        'courseID' => $customer['courseID'],
        'name' => $customer['course_name'],
        'enrollmentDate' => $customer['enrollmentDate'],
        'status' => $status
    ];
}

// Recupera informazioni del trainer
$stmt = $conn->prepare("SELECT firstName, lastName, specialization FROM USER WHERE userID = ?");
$stmt->bind_param('i', $trainerID);
$stmt->execute();
$trainerInfo = $stmt->get_result()->fetch_assoc();

// Calcola età da data di nascita
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

// Statistiche trainer (query semplificate)
function getTrainerCustomerStats($conn, $trainerID) {
    // Totale clienti seguiti
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT e.customerID) as total
        FROM enrollment e
        JOIN COURSE c ON e.courseID = c.courseID
        JOIN teaching t ON c.courseID = t.courseID
        WHERE t.trainerID = ? AND c.finishDate >= CURDATE()
    ");
    $stmt->bind_param('i', $trainerID);
    $stmt->execute();
    $totalCustomers = $stmt->get_result()->fetch_assoc()['total'];
    
    // Clienti attivi questo mese
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT e.customerID) as active
        FROM enrollment e
        JOIN COURSE c ON e.courseID = c.courseID
        JOIN teaching t ON c.courseID = t.courseID
        WHERE t.trainerID = ? 
        AND c.finishDate >= CURDATE()
        AND e.enrollmentDate >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $stmt->bind_param('i', $trainerID);
    $stmt->execute();
    $activeThisMonth = $stmt->get_result()->fetch_assoc()['active'];
    
    // Media età clienti
    $stmt = $conn->prepare("
        SELECT u.birthDate
        FROM USER u
        JOIN enrollment e ON u.userID = e.customerID
        JOIN COURSE c ON e.courseID = c.courseID
        JOIN teaching t ON c.courseID = t.courseID
        WHERE t.trainerID = ? AND u.birthDate IS NOT NULL AND c.finishDate >= CURDATE()
    ");
    $stmt->bind_param('i', $trainerID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $ages = [];
    while ($row = $result->fetch_assoc()) {
        $age = calcAge($row['birthDate']);
        if (is_numeric($age)) {
            $ages[] = $age;
        }
    }
    $avgAge = !empty($ages) ? round(array_sum($ages) / count($ages), 1) : 0;
    
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
                <a href="dashboard.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i>Torna alla Dashboard
                </a>
            </div>
        </div>
    </div>

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
                            <p class="mb-0">Nuovi Questo Mese</p>
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
            <?php if (!empty($groupedCustomers)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Età</th>
                                <th>Contatti</th>
                                <th>Genere</th>
                                <th>Corsi Seguiti</th>

                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($groupedCustomers as $customerData): 
                                $customer = $customerData['info'];
                                $courses = $customerData['courses'];
                            ?>
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
                                    <?php foreach ($courses as $course): ?>
                                        <div class="mb-1">
                                            <span class="badge bg-light text-dark me-1">
                                                <?= htmlspecialchars($course['name']) ?>
                                            </span>
                                            <span class="badge bg-<?= $course['status'] === 'active' ? 'success' : 'secondary' ?> me-1">
                                                <?= $course['status'] === 'active' ? 'Attivo' : 'Completato' ?>
                                            </span>
                                            <br><small class="text-muted">dal <?= date('d/m/Y', strtotime($course['enrollmentDate'])) ?></small>
                                        </div>
                                    <?php endforeach; ?>
                                </td>

                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <h5 class="text-muted">Nessun cliente ancora</h5>
                    <p class="text-muted">Quando i clienti si iscriveranno ai tuoi corsi, appariranno qui.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>



<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>