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
    if (isset($_POST['buy_subscription'])) {
        // Validazione acquisto abbonamento
        $membershipID = (int)$_POST['membershipID'];
        $startDate = $_POST['startDate'];
        $promotionID = !empty($_POST['promotionID']) ? (int)$_POST['promotionID'] : null;
        
        if (empty($startDate)) {
            $validation_errors[] = 'La data di inizio è obbligatoria.';
        }
        
        if ($membershipID <= 0) {
            $validation_errors[] = 'Seleziona un abbonamento valido.';
        }
        
        // Verifica che la data di inizio non sia nel passato
        if (!empty($startDate) && $startDate < date('Y-m-d')) {
            $validation_errors[] = 'La data di inizio non può essere nel passato.';
        }
        
        // Verifica che il cliente non abbia già un abbonamento attivo
        $stmt = $conn->prepare("
            SELECT subscriptionID 
            FROM SUBSCRIPTIONS 
            WHERE customerID = ? AND startDate <= CURDATE() AND expirationDate >= CURDATE()
        ");
        $stmt->bind_param('i', $customerID);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $validation_errors[] = 'Hai già un abbonamento attivo. Non puoi acquistarne un altro.';
        }
        
        if (empty($validation_errors)) {
            // Recupera i dettagli dell'abbonamento
            $stmt = $conn->prepare("SELECT * FROM MEMBERSHIPS WHERE membershipID = ?");
            $stmt->bind_param('i', $membershipID);
            $stmt->execute();
            $membership = $stmt->get_result()->fetch_assoc();
            
            if (!$membership) {
                $error_message = 'Abbonamento non trovato.';
            } else {
                // Calcola la data di scadenza
                $start = new DateTime($startDate);
                $expirationDate = clone $start;
                $expirationDate->add(new DateInterval('P' . $membership['duration'] . 'D'));
                
                // Calcola il prezzo finale con eventuale promozione
                $finalPrice = $membership['price'];
                $discount = 0;
                
                if ($promotionID) {
                    $stmt = $conn->prepare("
                        SELECT * FROM PROMOTIONS 
                        WHERE promotionID = ? AND startDate <= CURDATE() AND expirationDate >= CURDATE()
                    ");
                    $stmt->bind_param('i', $promotionID);
                    $stmt->execute();
                    $promotion = $stmt->get_result()->fetch_assoc();
                    
                    if ($promotion) {
                        $discount = ($finalPrice * $promotion['discountRate']) / 100;
                        $finalPrice = $finalPrice - $discount;
                    }
                }
                
                // Inserisci la sottoscrizione
                $stmt = $conn->prepare("
                    INSERT INTO SUBSCRIPTIONS (startDate, expirationDate, customerID, promotionID, membershipID) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    'ssiii',
                    $startDate,
                    $expirationDate->format('Y-m-d'),
                    $customerID,
                    $promotionID,
                    $membershipID
                );
                
                if ($stmt->execute()) {
                    $subscriptionID = $conn->insert_id;
                    
                    // Inserisci il pagamento (senza specificare il metodo)
                    $stmt = $conn->prepare("
                        INSERT INTO PAYMENTS (date, amount, customerID, subscriptionID) 
                        VALUES (CURDATE(), ?, ?, ?)
                    ");
                    $stmt->bind_param('dii', $finalPrice, $customerID, $subscriptionID);
                    $stmt->execute();
                    
                    $success_message = 'Abbonamento acquistato con successo!';
                    if ($discount > 0) {
                        $success_message .= ' (Sconto applicato: €' . number_format($discount, 2) . ')';
                    }
                } else {
                    $error_message = 'Errore durante l\'acquisto dell\'abbonamento.';
                }
            }
        }
    }
}

// Recupera l'abbonamento corrente del cliente
$stmt = $conn->prepare("
    SELECT s.*, m.name as membership_name, m.price, m.description,
           p.name as promotion_name, p.discountRate,
           pay.amount as paid_amount
    FROM SUBSCRIPTIONS s
    JOIN MEMBERSHIPS m ON s.membershipID = m.membershipID
    LEFT JOIN PROMOTIONS p ON s.promotionID = p.promotionID
    LEFT JOIN PAYMENTS pay ON s.subscriptionID = pay.subscriptionID
    WHERE s.customerID = ? AND s.startDate <= CURDATE() AND s.expirationDate >= CURDATE()
    ORDER BY s.startDate DESC
    LIMIT 1
");
$stmt->bind_param('i', $customerID);
$stmt->execute();
$currentSubscription = $stmt->get_result()->fetch_assoc();

// Recupera lo storico abbonamenti
$stmt = $conn->prepare("
    SELECT s.*, m.name as membership_name, m.price, m.description,
           p.name as promotion_name, p.discountRate,
           pay.amount as paid_amount
    FROM SUBSCRIPTIONS s
    JOIN MEMBERSHIPS m ON s.membershipID = m.membershipID
    LEFT JOIN PROMOTIONS p ON s.promotionID = p.promotionID
    LEFT JOIN PAYMENTS pay ON s.subscriptionID = pay.subscriptionID
    WHERE s.customerID = ?
    ORDER BY s.startDate DESC
");
$stmt->bind_param('i', $customerID);
$stmt->execute();
$subscriptionHistory = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Stato degli abbonamenti
foreach($subscriptionHistory as &$sub) {
    $today = new DateTime();
    $startDate = new DateTime($sub['startDate']);
    $expirationDate = new DateTime($sub['expirationDate']);
    
    if ($expirationDate < $today) {
        $sub['status'] = 'Scaduto';
        $sub['status_class'] = 'secondary';
    } elseif ($startDate > $today) {
        $sub['status'] = 'In attesa';
        $sub['status_class'] = 'warning';
    } else {
        $sub['status'] = 'Attivo';
        $sub['status_class'] = 'success';
    }
}

// Recupera tutti gli abbonamenti disponibili
$stmt = $conn->prepare("SELECT * FROM MEMBERSHIPS ORDER BY price ASC");
$stmt->execute();
$availableMemberships = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Recupera le promozioni attive
$stmt = $conn->prepare("
    SELECT * FROM PROMOTIONS 
    WHERE startDate <= CURDATE() AND expirationDate >= CURDATE()
    ORDER BY discountRate DESC
");
$stmt->execute();
$activePromotions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Informazioni cliente
$stmt = $conn->prepare("SELECT firstName, lastName, email FROM USERS WHERE userID = ?");
$stmt->bind_param('i', $customerID);
$stmt->execute();
$customerInfo = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Gestione Abbonamenti</title>
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
                    <h2>I Tuoi Abbonamenti</h2>
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

    <!-- Abbonamento Corrente -->
    <?php if ($currentSubscription): ?>
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <h4 class="mb-3">Il Tuo Abbonamento Attivo</h4>
                <div class="row">
                    <div class="col-md-8">
                        <h5><?= htmlspecialchars($currentSubscription['membership_name']) ?></h5>
                        <p class="text-muted"><?= htmlspecialchars($currentSubscription['description']) ?></p>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <strong>Data Inizio:</strong><br>
                                <span class="text-success"><?= date('d/m/Y', strtotime($currentSubscription['startDate'])) ?></span>
                            </div>
                            <div class="col-md-6">
                                <strong>Data Scadenza:</strong><br>
                                <span class="text-danger"><?= date('d/m/Y', strtotime($currentSubscription['expirationDate'])) ?></span>
                            </div>
                            <div class="col-md-6">
                                <strong>Prezzo Pagato:</strong><br>
                                <span class="fw-bold">€<?= number_format($currentSubscription['paid_amount'], 2) ?></span>
                                <?php if ($currentSubscription['promotion_name']): ?>
                                    <br><small class="text-success">
                                        <?= htmlspecialchars($currentSubscription['promotion_name']) ?>
                                        (-<?= $currentSubscription['discountRate'] ?>%)
                                    </small>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <strong>Giorni Rimanenti:</strong><br>
                                <?php 
                                $today = new DateTime();
                                $expiration = new DateTime($currentSubscription['expirationDate']);
                                $diff = $today->diff($expiration);
                                $daysLeft = $diff->days;
                                $color = $daysLeft <= 7 ? 'text-danger' : ($daysLeft <= 30 ? 'text-warning' : 'text-success');
                                ?>
                                <span class="fw-bold <?= $color ?>"><?= $daysLeft ?> giorni</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Nessun abbonamento attivo -->
        <div class="card mb-4 shadow-sm">
            <div class="card-body text-center">
                <h4 class="text-warning mb-3">Nessun Abbonamento Attivo</h4>
                <p class="text-muted">Scegli uno degli abbonamenti disponibili qui sotto per iniziare ad allenarti!</p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Promozioni Attive -->
    <?php if (!empty($activePromotions)): ?>
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <h4 class="mb-3">Promozioni Attive</h4>
                <div class="row">
                    <?php foreach($activePromotions as $promotion): ?>
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="card border-info h-100">
                            <div class="card-body text-center">
                                <h6 class="card-title"><?= htmlspecialchars($promotion['name']) ?></h6>
                                <h4 class="text-info"><?= $promotion['discountRate'] ?>%</h4>
                                <p class="card-text small"><?= htmlspecialchars($promotion['description']) ?></p>
                                <small class="text-muted">
                                    Valida fino al <?= date('d/m/Y', strtotime($promotion['expirationDate'])) ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Abbonamenti Disponibili -->
    <?php if (!$currentSubscription): ?>
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <h4>Abbonamenti Disponibili</h4>
                <div class="row">
                    <?php foreach($availableMemberships as $membership): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title text-primary"><?= htmlspecialchars($membership['name']) ?></h5>
                                <p class="card-text"><?= htmlspecialchars($membership['description']) ?></p>
                                
                                <div class="mb-3">
                                    <h4 class="text-success">€<?= number_format($membership['price'], 2) ?></h4>
                                    <small class="text-muted"><?= $membership['duration'] ?> giorni</small>
                                </div>

                                <!-- Form acquisto -->
                                <form method="POST">
                                    <input type="hidden" name="membershipID" value="<?= $membership['membershipID'] ?>">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Data Inizio</label>
                                        <input name="startDate" required class="form-control" type="date" 
                                               min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" />
                                    </div>
                                    
                                    <?php if (!empty($activePromotions)): ?>
                                    <div class="mb-3">
                                        <label class="form-label">Promozione (opzionale)</label>
                                        <select name="promotionID" class="form-select">
                                            <option value="">Nessuna promozione</option>
                                            <?php foreach($activePromotions as $promotion): ?>
                                                <option value="<?= $promotion['promotionID'] ?>">
                                                    <?= htmlspecialchars($promotion['name']) ?> (-<?= $promotion['discountRate'] ?>%)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <button name="buy_subscription" class="btn btn-success w-100">
                                        Acquista Abbonamento
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Storico Abbonamenti -->
    <?php if (!empty($subscriptionHistory)): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h4>Storico Abbonamenti</h4>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Abbonamento</th>
                                <th>Data Inizio</th>
                                <th>Data Scadenza</th>
                                <th>Prezzo Pagato</th>
                                <th>Promozione</th>
                                <th>Stato</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($subscriptionHistory as $sub): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($sub['membership_name']) ?></strong>
                                        <br><small class="text-muted"><?= htmlspecialchars($sub['description']) ?></small>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($sub['startDate'])) ?></td>
                                    <td><?= date('d/m/Y', strtotime($sub['expirationDate'])) ?></td>
                                    <td>€<?= number_format($sub['paid_amount'], 2) ?></td>
                                    <td>
                                        <?php if ($sub['promotion_name']): ?>
                                            <span class="badge bg-info">
                                                <?= htmlspecialchars($sub['promotion_name']) ?> (-<?= $sub['discountRate'] ?>%)
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">Nessuna</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $sub['status_class'] ?>"><?= $sub['status'] ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script>
// Calcolo prezzo dinamico con promozione
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        const promotionSelect = form.querySelector('select[name="promotionID"]');
        const membershipID = form.querySelector('input[name="membershipID"]');
        
        if (promotionSelect && membershipID) {
            const originalPrice = <?= json_encode(array_column($availableMemberships, 'price', 'membershipID')) ?>;
            const promotions = <?= json_encode(array_column($activePromotions, 'discountRate', 'promotionID')) ?>;
            
            promotionSelect.addEventListener('change', function() {
                const price = originalPrice[membershipID.value];
                const promotionID = this.value;
                
                let finalPrice = price;
                let priceText = '€' + price.toFixed(2);
                
                if (promotionID && promotions[promotionID]) {
                    const discount = (price * promotions[promotionID]) / 100;
                    finalPrice = price - discount;
                    priceText = '€' + finalPrice.toFixed(2) + ' <small class="text-muted"><s>€' + price.toFixed(2) + '</s></small>';
                }
                
                const priceDisplay = form.closest('.card-body').querySelector('h4.text-success');
                if (priceDisplay) {
                    priceDisplay.innerHTML = priceText;
                }
            });
        }
    });
});
</script>
</body>
</html>