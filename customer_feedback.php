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
    if (isset($_POST['add_feedback'])) {
        // Validazione dati
        $date = date('Y-m-d');
        $rating = !empty($_POST['rating']) ? (int)$_POST['rating'] : null;
        $comment = trim($_POST['comment'] ?? '');

        if ($rating === null || $rating < 1 || $rating > 5) {
            $validation_errors[] = 'La valutazione deve essere compresa tra 1 e 5.';
        }

        if (empty($comment)) {
            $comment = null;
        } elseif (strlen($comment) > 1000) {
            $validation_errors[] = 'Il commento non può superare i 1000 caratteri.';
        }

        if (empty($validation_errors)) {
            // Inserisci il feedback
            $stmt = $conn->prepare("
                INSERT INTO FEEDBACKS (date, rating, comment, customerID) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param(
                'sisi',
                $date,
                $rating,
                $comment,
                $customerID
            );

            if ($stmt->execute()) {
                $success_message = 'Feedback inviato con successo! Grazie per il tuo contributo.';
                unset($_POST);
            } else {
                $error_message = 'Errore durante l\'invio del feedback.';
            }
        }
    }
}

// Recupera i feedback già inviati dal cliente
$stmt = $conn->prepare("
    SELECT f.*
    FROM FEEDBACKS f
    WHERE f.customerID = ?
    ORDER BY f.date DESC
");
$stmt->bind_param('i', $customerID);
$stmt->execute();
$myFeedbacks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Informazioni cliente
$stmt = $conn->prepare("SELECT firstName, lastName, email FROM USERS WHERE userID = ?");
$stmt->bind_param('i', $customerID);
$stmt->execute();
$customerInfo = $stmt->get_result()->fetch_assoc();

// Statistiche
$totalFeedbacks = count($myFeedbacks);
$avgRating = 0;
if ($totalFeedbacks > 0) {
    $avgRating = array_sum(array_column($myFeedbacks, 'rating')) / $totalFeedbacks;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Lascia un Feedback</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
    <style>
        .feedback-card {
            transition: transform 0.2s;
        }
        .feedback-card:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2>Lascia un Feedback</h2>
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

    <!-- Statistiche -->
    <?php if ($totalFeedbacks > 0): ?>
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <h4>Le Tue Statistiche Feedback</h4>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="card text-white h-100" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                            <div class="card-body text-center d-flex flex-column justify-content-center">
                                <h3><?= $totalFeedbacks ?></h3>
                                <p class="mb-0">Feedback Inviati</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card text-white h-100" style="background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);">
                            <div class="card-body text-center d-flex flex-column justify-content-center">
                                <h3><?= number_format($avgRating, 1) ?>/5</h3>
                                <p class="mb-0">Valutazione Media</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Form Nuovo Feedback -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <h4>Nuovo Feedback</h4>
            <form method="POST" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Valutazione</label>
                    <input name="rating" required class="form-control" type="number" 
                           min="1" max="5" placeholder="Inserisci un numero da 1 a 5"
                           value="<?= isset($_POST['rating']) ? $_POST['rating'] : '' ?>" />
                </div>

                <div class="col-12">
                    <label class="form-label">Commento (facoltativo)</label>
                    <textarea name="comment" class="form-control" rows="4"
                              maxlength="1000"><?= isset($_POST['comment']) ? htmlspecialchars($_POST['comment']) : '' ?></textarea>
                    <div class="form-text">Massimo 1000 caratteri</div>
                </div>

                <div class="col-12">
                    <button name="add_feedback" class="btn btn-success">
                        Invia Feedback
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- I Tuoi Feedback -->
    <?php if (!empty($myFeedbacks)): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h4>I Tuoi Feedback</h4>
                <div class="row">
                    <?php foreach($myFeedbacks as $feedback): ?>
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="card h-100 border-0 shadow-sm feedback-card">
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

                                <!-- Commento -->
                                <p class="card-text">
                                    "<?= htmlspecialchars($feedback['comment']) ?>"
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="card shadow-sm mb-4">
            <div class="card-body text-center py-5">
                <h5 class="text-muted">Nessun feedback inviato</h5>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>