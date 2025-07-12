<?php
require_once 'config.php';
session_start();

// Controllo accesso admin
if (!isset($_SESSION['userID'], $_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Gestione POST
$error_message = '';
$success_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_membership'])) {
        // Aggiunta abbonamento
        if (!empty($_POST['name']) && !empty($_POST['price']) && !empty($_POST['duration'])) {
            $stmt = $conn->prepare("INSERT INTO MEMBERSHIPS (name, price, duration, description) VALUES (?, ?, ?, ?)");
            $stmt->bind_param(
                'sdis',
                $_POST['name'],
                $_POST['price'],
                $_POST['duration'],
                $_POST['description']
            );
            if ($stmt->execute()) {
                $success_message = 'Abbonamento aggiunto con successo!';
                unset($_POST);
            } else {
                $error_message = 'Errore durante l\'inserimento dell\'abbonamento.';
            }
        } else {
            $error_message = 'Compilare tutti i campi obbligatori.';
        }
    } elseif (isset($_POST['update_membership'])) {
        // Modifica abbonamento
        $membershipID = (int)$_POST['membershipID'];
        if ($membershipID > 0) {
            $stmt = $conn->prepare("UPDATE MEMBERSHIPS SET name = ?, price = ?, duration = ?, description = ? WHERE membershipID = ?");
            $stmt->bind_param(
                'sdisi',
                $_POST['name'],
                $_POST['price'],
                $_POST['duration'],
                $_POST['description'],
                $membershipID
            );
            if ($stmt->execute()) {
                $success_message = 'Abbonamento modificato con successo!';
            } else {
                $error_message = 'Errore durante la modifica dell\'abbonamento.';
            }
        }
    } elseif (isset($_POST['delete_membership'])) {
        // Eliminazione abbonamento
        $deleteID = (int)$_POST['delete_id'];
        if ($deleteID > 0) {
            $stmt = $conn->prepare("DELETE FROM MEMBERSHIPS WHERE membershipID = ?");
            $stmt->bind_param('i', $deleteID);
            if ($stmt->execute()) {
                $success_message = 'Abbonamento eliminato con successo!';
            } else {
                $error_message = 'Errore durante l\'eliminazione dell\'abbonamento.';
            }
        }
    } elseif (isset($_POST['add_promotion'])) {
        // Aggiunta promozione
        if (!empty($_POST['promo_name']) && !empty($_POST['discountRate']) && !empty($_POST['startDate']) && !empty($_POST['expirationDate'])) {
            $stmt = $conn->prepare("INSERT INTO PROMOTIONS (name, description, discountRate, startDate, expirationDate) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param(
                'ssdss',
                $_POST['promo_name'],
                $_POST['promo_description'],
                $_POST['discountRate'],
                $_POST['startDate'],
                $_POST['expirationDate']
            );
            if ($stmt->execute()) {
                $success_message = 'Promozione aggiunta con successo!';
                unset($_POST);
            } else {
                $error_message = 'Errore durante l\'inserimento della promozione.';
            }
        } else {
            $error_message = 'Compilare tutti i campi obbligatori della promozione.';
        }
    } elseif (isset($_POST['update_promotion'])) {
        // Modifica promozione
        $promotionID = (int)$_POST['promotionID'];
        if ($promotionID > 0) {
            $stmt = $conn->prepare("UPDATE PROMOTIONS SET name = ?, description = ?, discountRate = ?, startDate = ?, expirationDate = ? WHERE promotionID = ?");
            $stmt->bind_param(
                'ssdssi',
                $_POST['promo_name'],
                $_POST['promo_description'],
                $_POST['discountRate'],
                $_POST['startDate'],
                $_POST['expirationDate'],
                $promotionID
            );
            if ($stmt->execute()) {
                $success_message = 'Promozione modificata con successo!';
            } else {
                $error_message = 'Errore durante la modifica della promozione.';
            }
        }
    } elseif (isset($_POST['delete_promotion'])) {
        // Eliminazione promozione
        $deleteID = (int)$_POST['delete_promo_id'];
        if ($deleteID > 0) {
            $stmt = $conn->prepare("DELETE FROM PROMOTIONS WHERE promotionID = ?");
            $stmt->bind_param('i', $deleteID);
            if ($stmt->execute()) {
                $success_message = 'Promozione eliminata con successo!';
            } else {
                $error_message = 'Errore durante l\'eliminazione della promozione.';
            }
        }
    }
}

// Recupera tutti gli abbonamenti
$stmt = $conn->prepare("SELECT * FROM MEMBERSHIPS ORDER BY price ASC");
$stmt->execute();
$memberships = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Recupera tutte le promozioni
$stmt = $conn->prepare("SELECT * FROM PROMOTIONS ORDER BY expirationDate DESC");
$stmt->execute();
$promotions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Modifica abbonamento
$editMembership = null;
if (isset($_GET['edit_membership']) && is_numeric($_GET['edit_membership'])) {
    $membershipID = (int)$_GET['edit_membership'];
    $stmt = $conn->prepare("SELECT * FROM MEMBERSHIPS WHERE membershipID = ?");
    $stmt->bind_param('i', $membershipID);
    $stmt->execute();
    $editMembership = $stmt->get_result()->fetch_assoc();
}

// Modifica promozione
$editPromotion = null;
if (isset($_GET['edit_promotion']) && is_numeric($_GET['edit_promotion'])) {
    $promotionID = (int)$_GET['edit_promotion'];
    $stmt = $conn->prepare("SELECT * FROM PROMOTIONS WHERE promotionID = ?");
    $stmt->bind_param('i', $promotionID);
    $stmt->execute();
    $editPromotion = $stmt->get_result()->fetch_assoc();
}

// Statistiche
function getSubscriptionStats($conn) {
    // Totale abbonamenti
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM MEMBERSHIPS");
    $stmt->execute();
    $totalMemberships = $stmt->get_result()->fetch_assoc()['total'];
    
    // Abbonamenti attivi venduti
    $stmt = $conn->prepare("SELECT COUNT(*) as active FROM SUBSCRIPTIONS WHERE startDate <= CURDATE() AND expirationDate >= CURDATE()");
    $stmt->execute();
    $activeSubscriptions = $stmt->get_result()->fetch_assoc()['active'];
    
    // Promozioni attive
    $stmt = $conn->prepare("SELECT COUNT(*) as active FROM PROMOTIONS WHERE startDate <= CURDATE() AND expirationDate >= CURDATE()");
    $stmt->execute();
    $activePromotions = $stmt->get_result()->fetch_assoc()['active'];
    
    return [
        'totalMemberships' => $totalMemberships,
        'activeSubscriptions' => $activeSubscriptions,
        'activePromotions' => $activePromotions
    ];
}

$stats = getSubscriptionStats($conn);
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
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <h2>Gestione Abbonamenti e Promozioni</h2>
                <a href="dashboard.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i>Torna alla Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Area Statistiche -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <h4>Statistiche</h4>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="card text-white h-100" style="background: linear-gradient(135deg, #6a85b6 0%, #bac8e0 100%);">
                        <div class="card-body text-center d-flex flex-column justify-content-center">
                            <h3><?= $stats['totalMemberships'] ?></h3>
                            <p class="mb-0">Tipi di Abbonamento</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white h-100" style="background: linear-gradient(135deg, #a8c8ec 0%, #7fcdcd 100%);">
                        <div class="card-body text-center d-flex flex-column justify-content-center">
                            <h3><?= $stats['activeSubscriptions'] ?></h3>
                            <p class="mb-0">Abbonamenti Attivi</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white h-100" style="background: linear-gradient(135deg, #7fcdcd 0%, #c2e9fb 100%);">
                        <div class="card-body text-center d-flex flex-column justify-content-center">
                            <h3><?= $stats['activePromotions'] ?></h3>
                            <p class="mb-0">Promozioni Attive</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Abbonamenti -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <h4><?= $editMembership ? 'Modifica Abbonamento' : 'Aggiungi Abbonamento' ?></h4>
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>
            <form method="POST" class="row g-3">
                <?php if ($editMembership): ?>
                    <input type="hidden" name="membershipID" value="<?= $editMembership['membershipID'] ?>">
                <?php endif; ?>
                <div class="col-md-6">
                    <label class="form-label">Nome Abbonamento</label>
                    <input name="name" required class="form-control" type="text" 
                           value="<?= $editMembership ? htmlspecialchars($editMembership['name']) : (isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '') ?>" />
                </div>
                <div class="col-md-3">
                    <label class="form-label">Prezzo (€)</label>
                    <input name="price" required class="form-control" type="number" step="0.01" min="0"
                           value="<?= $editMembership ? $editMembership['price'] : (isset($_POST['price']) ? $_POST['price'] : '') ?>" />
                </div>
                <div class="col-md-3">
                    <label class="form-label">Durata (giorni)</label>
                    <input name="duration" required class="form-control" type="number" min="1"
                           value="<?= $editMembership ? $editMembership['duration'] : (isset($_POST['duration']) ? $_POST['duration'] : '') ?>" />
                </div>
                <div class="col-12">
                    <label class="form-label">Descrizione</label>
                    <textarea name="description" class="form-control" rows="3"><?= $editMembership ? htmlspecialchars($editMembership['description']) : (isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '') ?></textarea>
                </div>
                <div class="col-12">
                    <button name="<?= $editMembership ? 'update_membership' : 'add_membership' ?>" class="btn <?= $editMembership ? 'btn-warning' : 'btn-success' ?>">
                        <?= $editMembership ? 'Modifica Abbonamento' : 'Aggiungi Abbonamento' ?>
                    </button>
                    <?php if ($editMembership): ?>
                        <a href="?" class="btn btn-secondary">Annulla</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabella Abbonamenti -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h4>Abbonamenti Disponibili</h4>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Nome</th><th>Prezzo</th><th>Durata</th><th>Descrizione</th><th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($memberships as $membership): ?>
                        <tr>
                            <td><?= htmlspecialchars($membership['name']) ?></td>
                            <td>€<?= number_format($membership['price'], 2) ?></td>
                            <td><?= $membership['duration'] ?> giorni</td>
                            <td><?= htmlspecialchars(substr($membership['description'], 0, 50)) ?><?= strlen($membership['description']) > 50 ? '...' : '' ?></td>
                            <td>
                                <a href="?edit_membership=<?= $membership['membershipID'] ?>" class="btn btn-sm btn-warning">Modifica</a>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Sei sicuro di eliminare questo abbonamento?');">
                                    <input type="hidden" name="delete_id" value="<?= $membership['membershipID'] ?>">
                                    <button name="delete_membership" class="btn btn-sm btn-danger">Elimina</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($memberships)): ?>
                        <tr><td colspan="5" class="text-center">Nessun abbonamento disponibile.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Form Promozioni -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <h4><?= $editPromotion ? 'Modifica Promozione' : 'Aggiungi Promozione' ?></h4>
            <form method="POST" class="row g-3">
                <?php if ($editPromotion): ?>
                    <input type="hidden" name="promotionID" value="<?= $editPromotion['promotionID'] ?>">
                <?php endif; ?>
                <div class="col-md-6">
                    <label class="form-label">Nome Promozione</label>
                    <input name="promo_name" required class="form-control" type="text" 
                           value="<?= $editPromotion ? htmlspecialchars($editPromotion['name']) : (isset($_POST['promo_name']) ? htmlspecialchars($_POST['promo_name']) : '') ?>" />
                </div>
                <div class="col-md-3">
                    <label class="form-label">Sconto (%)</label>
                    <input name="discountRate" required class="form-control" type="number" step="0.01" min="0" max="100"
                           value="<?= $editPromotion ? $editPromotion['discountRate'] : (isset($_POST['discountRate']) ? $_POST['discountRate'] : '') ?>" />
                </div>
                <div class="col-md-3">
                    <label class="form-label">Data Inizio</label>
                    <input name="startDate" required class="form-control" type="date"
                           value="<?= $editPromotion ? $editPromotion['startDate'] : (isset($_POST['startDate']) ? $_POST['startDate'] : '') ?>" />
                </div>
                <div class="col-md-6">
                    <label class="form-label">Data Scadenza</label>
                    <input name="expirationDate" required class="form-control" type="date"
                           value="<?= $editPromotion ? $editPromotion['expirationDate'] : (isset($_POST['expirationDate']) ? $_POST['expirationDate'] : '') ?>" />
                </div>
                <div class="col-12">
                    <label class="form-label">Descrizione</label>
                    <textarea name="promo_description" class="form-control" rows="2"><?= $editPromotion ? htmlspecialchars($editPromotion['description']) : (isset($_POST['promo_description']) ? htmlspecialchars($_POST['promo_description']) : '') ?></textarea>
                </div>
                <div class="col-12">
                    <button name="<?= $editPromotion ? 'update_promotion' : 'add_promotion' ?>" class="btn <?= $editPromotion ? 'btn-warning' : 'btn-info' ?>">
                        <?= $editPromotion ? 'Modifica Promozione' : 'Aggiungi Promozione' ?>
                    </button>
                    <?php if ($editPromotion): ?>
                        <a href="?" class="btn btn-secondary">Annulla</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabella Promozioni -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h4>Promozioni</h4>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Nome</th><th>Sconto</th><th>Inizio</th><th>Scadenza</th><th>Stato</th><th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($promotions as $promotion): ?>
                        <?php
                        $now = new DateTime();
                        $start = new DateTime($promotion['startDate']);
                        $end = new DateTime($promotion['expirationDate']);
                        $status = $now < $start ? 'In programma' : ($now > $end ? 'Scaduta' : 'Attiva');
                        $statusClass = $status === 'Attiva' ? 'success' : ($status === 'In programma' ? 'warning' : 'danger');
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($promotion['name']) ?></td>
                            <td><?= $promotion['discountRate'] ?>%</td>
                            <td><?= date('d/m/Y', strtotime($promotion['startDate'])) ?></td>
                            <td><?= date('d/m/Y', strtotime($promotion['expirationDate'])) ?></td>
                            <td><span class="badge bg-<?= $statusClass ?>"><?= $status ?></span></td>
                            <td>
                                <a href="?edit_promotion=<?= $promotion['promotionID'] ?>" class="btn btn-sm btn-warning">Modifica</a>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Sei sicuro di eliminare questa promozione?');">
                                    <input type="hidden" name="delete_promo_id" value="<?= $promotion['promotionID'] ?>">
                                    <button name="delete_promotion" class="btn btn-sm btn-danger">Elimina</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($promotions)): ?>
                        <tr><td colspan="6" class="text-center">Nessuna promozione disponibile.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>