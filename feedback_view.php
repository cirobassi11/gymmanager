<?php
require_once 'config.php';
session_start();

// Controllo accesso admin
if (!isset($_SESSION['userID'], $_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Recupera tutti i feedback
$stmt = $conn->prepare("
    SELECT f.feedbackID, f.date, f.rating, f.comment,
           u.firstName, u.lastName
    FROM FEEDBACKS f
    JOIN USERS u ON f.customerID = u.userID
    ORDER BY f.date DESC
");
$stmt->execute();
$feedbacks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Statistiche
function getFeedbackStats($conn) {
    // Totale feedback
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM FEEDBACKS");
    $stmt->execute();
    $totalFeedbacks = $stmt->get_result()->fetch_assoc()['total'];
    
    // Rating medio
    $stmt = $conn->prepare("SELECT AVG(rating) as avg_rating FROM FEEDBACKS WHERE rating IS NOT NULL");
    $stmt->execute();
    $avgRating = round($stmt->get_result()->fetch_assoc()['avg_rating'], 1);
    
    return [
        'totalFeedbacks' => $totalFeedbacks,
        'avgRating' => $avgRating ?: 0
    ];
}

$stats = getFeedbackStats($conn);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Visualizzazione Feedback</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <h2>Visualizzazione Feedback</h2>
                <a href="dashboard.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i>Torna alla Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Area Statistiche -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <h4>Statistiche Feedback</h4>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="card text-white h-100" style="background: linear-gradient(135deg, #6a85b6 0%, #bac8e0 100%);">
                        <div class="card-body text-center d-flex flex-column justify-content-center">
                            <h3><?= $stats['totalFeedbacks'] ?></h3>
                            <p class="mb-0">Feedback Totali</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card text-white h-100" style="background: linear-gradient(135deg, #a8c8ec 0%, #7fcdcd 100%);">
                        <div class="card-body text-center d-flex flex-column justify-content-center">
                            <h3><?= $stats['avgRating'] ?> <small>/5</small></h3>
                            <p class="mb-0">Rating Medio</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista Feedback -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h4>Feedback Ricevuti</h4>
            
            <?php if (!empty($feedbacks)): ?>
                <div class="row">
                    <?php foreach($feedbacks as $feedback): ?>
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-body">
                                <!-- Header con rating e data -->
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <?php if ($feedback['rating']): ?>
                                            <?= generateStars($feedback['rating']) ?>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted"><?= date('d/m/Y', strtotime($feedback['date'])) ?></small>
                                </div>

                                <!-- Cliente -->
                                <h6 class="card-title mb-3">
                                    <?= htmlspecialchars($feedback['firstName'] . ' ' . $feedback['lastName']) ?>
                                </h6>

                                <!-- Commento -->
                                <?php if ($feedback['comment']): ?>
                                    <p class="card-text mb-3">
                                        "<?= htmlspecialchars($feedback['comment']) ?>"
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <h5 class="text-muted">Nessun feedback disponibile</h5>
                    <p class="text-muted">Non sono ancora stati ricevuti feedback dai clienti.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>