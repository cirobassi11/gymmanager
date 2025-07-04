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
    if (isset($_POST['add_equipment'])) {
        // Aggiunta attrezzatura
        if (!empty($_POST['name'])) {
            $stmt = $conn->prepare("INSERT INTO EQUIPMENT (name, description, state, administratorID) VALUES (?, ?, ?, ?)");
            $stmt->bind_param(
                'sssi',
                $_POST['name'],
                $_POST['description'],
                $_POST['state'],
                $_SESSION['userID']
            );
            if ($stmt->execute()) {
                $success_message = 'Attrezzatura aggiunta con successo!';
                unset($_POST);
            } else {
                $error_message = 'Errore durante l\'inserimento dell\'attrezzatura.';
            }
        } else {
            $error_message = 'Il nome dell\'attrezzatura è obbligatorio.';
        }
    } elseif (isset($_POST['update_equipment'])) {
        // Modifica attrezzatura
        $equipmentID = (int)$_POST['equipmentID'];
        if ($equipmentID > 0) {
            $stmt = $conn->prepare("UPDATE EQUIPMENT SET name = ?, description = ?, state = ? WHERE equipmentID = ?");
            $stmt->bind_param(
                'sssi',
                $_POST['name'],
                $_POST['description'],
                $_POST['state'],
                $equipmentID
            );
            if ($stmt->execute()) {
                $success_message = 'Attrezzatura modificata con successo!';
            } else {
                $error_message = 'Errore durante la modifica dell\'attrezzatura.';
            }
        }
    } elseif (isset($_POST['delete_equipment'])) {
        // Eliminazione attrezzatura
        $deleteID = (int)$_POST['delete_id'];
        if ($deleteID > 0) {
            $stmt = $conn->prepare("DELETE FROM EQUIPMENT WHERE equipmentID = ?");
            $stmt->bind_param('i', $deleteID);
            if ($stmt->execute()) {
                $success_message = 'Attrezzatura eliminata con successo!';
            } else {
                $error_message = 'Errore durante l\'eliminazione dell\'attrezzatura.';
            }
        }
    } elseif (isset($_POST['add_maintenance'])) {
        // Aggiunta manutenzione
        if (!empty($_POST['equipmentID']) && !empty($_POST['maintenanceDate'])) {
            $stmt = $conn->prepare("INSERT INTO MAINTENANCE (equipmentID, maintenanceDate, maintenanceCost, description, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param(
                'isdss',
                $_POST['equipmentID'],
                $_POST['maintenanceDate'],
                $_POST['maintenanceCost'],
                $_POST['maintenance_description'],
                $_POST['maintenance_status']
            );
            if ($stmt->execute()) {
                $success_message = 'Intervento di manutenzione aggiunto con successo!';
                unset($_POST);
            } else {
                $error_message = 'Errore durante l\'inserimento della manutenzione.';
            }
        } else {
            $error_message = 'Attrezzatura e data sono obbligatori.';
        }
    } elseif (isset($_POST['update_maintenance'])) {
        // Modifica manutenzione
        $maintenanceID = (int)$_POST['maintenanceID'];
        if ($maintenanceID > 0) {
            $stmt = $conn->prepare("UPDATE MAINTENANCE SET equipmentID = ?, maintenanceDate = ?, maintenanceCost = ?, description = ?, status = ? WHERE maintenanceID = ?");
            $stmt->bind_param(
                'isdssi',
                $_POST['equipmentID'],
                $_POST['maintenanceDate'],
                $_POST['maintenanceCost'],
                $_POST['maintenance_description'],
                $_POST['maintenance_status'],
                $maintenanceID
            );
            if ($stmt->execute()) {
                $success_message = 'Manutenzione modificata con successo!';
            } else {
                $error_message = 'Errore durante la modifica della manutenzione.';
            }
        }
    } elseif (isset($_POST['delete_maintenance'])) {
        // Eliminazione manutenzione
        $deleteID = (int)$_POST['delete_maintenance_id'];
        if ($deleteID > 0) {
            $stmt = $conn->prepare("DELETE FROM MAINTENANCE WHERE maintenanceID = ?");
            $stmt->bind_param('i', $deleteID);
            if ($stmt->execute()) {
                $success_message = 'Manutenzione eliminata con successo!';
            } else {
                $error_message = 'Errore durante l\'eliminazione della manutenzione.';
            }
        }
    }
}

// Recupera tutte le attrezzature
$stmt = $conn->prepare("
    SELECT e.*, 
           COUNT(m.maintenanceID) as maintenance_count,
           CASE WHEN SUM(m.maintenanceCost) IS NULL THEN 0 ELSE SUM(m.maintenanceCost) END AS total_cost,
           MAX(m.maintenanceDate) as last_maintenance
    FROM EQUIPMENT e
    LEFT JOIN MAINTENANCE m ON e.equipmentID = m.equipmentID
    GROUP BY e.equipmentID
    ORDER BY e.name ASC
");
$stmt->execute();
$equipment = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Recupera tutte le manutenzioni
$stmt = $conn->prepare("
    SELECT m.*, e.name as equipment_name 
    FROM MAINTENANCE m
    JOIN EQUIPMENT e ON m.equipmentID = e.equipmentID
    ORDER BY m.maintenanceDate DESC
");
$stmt->execute();
$maintenances = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Se è una modifica attrezzatura, recupera i dati
$editEquipment = null;
if (isset($_GET['edit_equipment']) && is_numeric($_GET['edit_equipment'])) {
    $equipmentID = (int)$_GET['edit_equipment'];
    $stmt = $conn->prepare("SELECT * FROM EQUIPMENT WHERE equipmentID = ?");
    $stmt->bind_param('i', $equipmentID);
    $stmt->execute();
    $editEquipment = $stmt->get_result()->fetch_assoc();
}

// Se è una modifica manutenzione, recupera i dati
$editMaintenance = null;
if (isset($_GET['edit_maintenance']) && is_numeric($_GET['edit_maintenance'])) {
    $maintenanceID = (int)$_GET['edit_maintenance'];
    $stmt = $conn->prepare("SELECT * FROM MAINTENANCE WHERE maintenanceID = ?");
    $stmt->bind_param('i', $maintenanceID);
    $stmt->execute();
    $editMaintenance = $stmt->get_result()->fetch_assoc();
}

// Statistiche
function getEquipmentStats($conn) {
    // Totale attrezzature
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM EQUIPMENT");
    $stmt->execute();
    $totalEquipment = $stmt->get_result()->fetch_assoc()['total'];
    
    // Percentuale in buono stato
    $stmt = $conn->prepare("SELECT COUNT(*) as available FROM EQUIPMENT WHERE state = 'available'");
    $stmt->execute();
    $availableEquipment = $stmt->get_result()->fetch_assoc()['available'];
    $goodStatePercentage = $totalEquipment > 0 ? round(($availableEquipment / $totalEquipment) * 100, 1) : 0;
    
    // Manutenzioni totali
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM MAINTENANCE");
    $stmt->execute();
    $totalMaintenances = $stmt->get_result()->fetch_assoc()['total'];
    
    // Attrezzature che richiedono manutenzione più frequente (top 5)
    $stmt = $conn->prepare("
        SELECT e.name, COUNT(m.maintenanceID) as maintenance_count
        FROM EQUIPMENT e
        LEFT JOIN MAINTENANCE m ON e.equipmentID = m.equipmentID
        GROUP BY e.equipmentID, e.name
        HAVING maintenance_count > 0
        ORDER BY maintenance_count DESC
        LIMIT 5
    ");
    $stmt->execute();
    $frequentMaintenance = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Macchinari con costi di manutenzione più elevati (top 5)
    $stmt = $conn->prepare("
        SELECT e.name, COALESCE(SUM(m.maintenanceCost), 0) as total_cost
        FROM EQUIPMENT e
        LEFT JOIN MAINTENANCE m ON e.equipmentID = m.equipmentID
        GROUP BY e.equipmentID, e.name
        HAVING total_cost > 0
        ORDER BY total_cost DESC
        LIMIT 5
    ");
    $stmt->execute();
    $expensiveMaintenance = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    return [
        'totalEquipment' => $totalEquipment,
        'goodStatePercentage' => $goodStatePercentage,
        'totalMaintenances' => $totalMaintenances,
        'frequentMaintenance' => $frequentMaintenance,
        'expensiveMaintenance' => $expensiveMaintenance
    ];
}

$stats = getEquipmentStats($conn);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Gestione Attrezzature</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <h2>Gestione Attrezzature</h2>
                <a href="dashboard.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i>Torna alla Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Area Statistiche -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <h4>Statistiche Attrezzature</h4>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="card text-white h-100" style="background: linear-gradient(135deg, #6a85b6 0%, #bac8e0 100%);">
                        <div class="card-body text-center d-flex flex-column justify-content-center">
                            <h3><?= $stats['totalEquipment'] ?></h3>
                            <p class="mb-0">Attrezzature Totali</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white h-100" style="background: linear-gradient(135deg, #a8c8ec 0%, #7fcdcd 100%);">
                        <div class="card-body text-center d-flex flex-column justify-content-center">
                            <h3><?= $stats['goodStatePercentage'] ?>%</h3>
                            <p class="mb-0">In Buono Stato</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white h-100" style="background: linear-gradient(135deg, #7fcdcd 0%, #c2e9fb 100%);">
                        <div class="card-body text-center d-flex flex-column justify-content-center">
                            <h3><?= $stats['totalMaintenances'] ?></h3>
                            <p class="mb-0">Interventi Totali</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistiche Dettagliate -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5>Manutenzioni Più Frequenti</h5>
                    <?php if (!empty($stats['frequentMaintenance'])): ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach($stats['frequentMaintenance'] as $item): ?>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span><?= htmlspecialchars($item['name']) ?></span>
                                    <span class="badge bg-warning"><?= $item['maintenance_count'] ?> interventi</span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted">Nessuna manutenzione registrata.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5>Costi Manutenzione Più Elevati</h5>
                    <?php if (!empty($stats['expensiveMaintenance'])): ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach($stats['expensiveMaintenance'] as $item): ?>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span><?= htmlspecialchars($item['name']) ?></span>
                                    <span class="badge bg-danger">€<?= number_format($item['total_cost'], 2) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted">Nessun costo registrato.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Attrezzature -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <h4><?= $editEquipment ? 'Modifica Attrezzatura' : 'Aggiungi Attrezzatura' ?></h4>
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>
            <form method="POST" class="row g-3">
                <?php if ($editEquipment): ?>
                    <input type="hidden" name="equipmentID" value="<?= $editEquipment['equipmentID'] ?>">
                <?php endif; ?>
                <div class="col-md-6">
                    <label class="form-label">Nome Attrezzatura</label>
                    <input name="name" required class="form-control" type="text" 
                           value="<?= $editEquipment ? htmlspecialchars($editEquipment['name']) : (isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '') ?>" />
                </div>
                <div class="col-md-6">
                    <label class="form-label">Stato</label>
                    <select name="state" class="form-select" required>
                        <option value="available" <?= ($editEquipment && $editEquipment['state'] === 'available') || (isset($_POST['state']) && $_POST['state'] === 'available') ? 'selected' : '' ?>>Disponibile</option>
                        <option value="maintenance" <?= ($editEquipment && $editEquipment['state'] === 'maintenance') || (isset($_POST['state']) && $_POST['state'] === 'maintenance') ? 'selected' : '' ?>>In Manutenzione</option>
                        <option value="broken" <?= ($editEquipment && $editEquipment['state'] === 'broken') || (isset($_POST['state']) && $_POST['state'] === 'broken') ? 'selected' : '' ?>>Rotta</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Descrizione</label>
                    <textarea name="description" class="form-control" rows="3"><?= $editEquipment ? htmlspecialchars($editEquipment['description']) : (isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '') ?></textarea>
                </div>
                <div class="col-12">
                    <button name="<?= $editEquipment ? 'update_equipment' : 'add_equipment' ?>" class="btn <?= $editEquipment ? 'btn-warning' : 'btn-success' ?>">
                        <?= $editEquipment ? 'Modifica Attrezzatura' : 'Aggiungi Attrezzatura' ?>
                    </button>
                    <?php if ($editEquipment): ?>
                        <a href="?" class="btn btn-secondary">Annulla</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabella Attrezzature -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h4>Attrezzature</h4>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Nome</th><th>Stato</th><th>Ultima Manutenzione</th><th>N° Interventi</th><th>Costi Totali</th><th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($equipment as $eq): ?>
                        <?php
                        $stateClass = $eq['state'] === 'available' ? 'success' : ($eq['state'] === 'maintenance' ? 'warning' : 'danger');
                        $stateText = $eq['state'] === 'available' ? 'Disponibile' : ($eq['state'] === 'maintenance' ? 'Manutenzione' : 'Rotta');
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($eq['name']) ?></td>
                            <td><span class="badge bg-<?= $stateClass ?>"><?= $stateText ?></span></td>
                            <td><?= $eq['last_maintenance'] ? date('d/m/Y', strtotime($eq['last_maintenance'])) : 'Mai' ?></td>
                            <td><?= $eq['maintenance_count'] ?></td>
                            <td>€<?= number_format($eq['total_cost'], 2) ?></td>
                            <td>
                                <a href="?edit_equipment=<?= $eq['equipmentID'] ?>" class="btn btn-sm btn-warning">Modifica</a>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Sei sicuro di eliminare questa attrezzatura?');">
                                    <input type="hidden" name="delete_id" value="<?= $eq['equipmentID'] ?>">
                                    <button name="delete_equipment" class="btn btn-sm btn-danger">Elimina</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($equipment)): ?>
                        <tr><td colspan="6" class="text-center">Nessuna attrezzatura disponibile.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Form Manutenzioni -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <h4><?= $editMaintenance ? 'Modifica Manutenzione' : 'Aggiungi Manutenzione' ?></h4>
            <form method="POST" class="row g-3">
                <?php if ($editMaintenance): ?>
                    <input type="hidden" name="maintenanceID" value="<?= $editMaintenance['maintenanceID'] ?>">
                <?php endif; ?>
                <div class="col-md-6">
                    <label class="form-label">Attrezzatura</label>
                    <select name="equipmentID" class="form-select" required>
                        <option value="">Seleziona attrezzatura</option>
                        <?php foreach($equipment as $eq): ?>
                            <option value="<?= $eq['equipmentID'] ?>" 
                                <?= ($editMaintenance && $editMaintenance['equipmentID'] == $eq['equipmentID']) || (isset($_POST['equipmentID']) && $_POST['equipmentID'] == $eq['equipmentID']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($eq['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Data Manutenzione</label>
                    <input name="maintenanceDate" required class="form-control" type="date"
                           value="<?= $editMaintenance ? $editMaintenance['maintenanceDate'] : (isset($_POST['maintenanceDate']) ? $_POST['maintenanceDate'] : '') ?>" />
                </div>
                <div class="col-md-3">
                    <label class="form-label">Costo (€)</label>
                    <input name="maintenanceCost" class="form-control" type="number" step="0.01" min="0"
                           value="<?= $editMaintenance ? $editMaintenance['maintenanceCost'] : (isset($_POST['maintenanceCost']) ? $_POST['maintenanceCost'] : '') ?>" />
                </div>
                <div class="col-md-6">
                    <label class="form-label">Stato</label>
                    <select name="maintenance_status" class="form-select" required>
                        <option value="scheduled" <?= ($editMaintenance && $editMaintenance['status'] === 'scheduled') || (isset($_POST['maintenance_status']) && $_POST['maintenance_status'] === 'scheduled') ? 'selected' : '' ?>>Programmata</option>
                        <option value="in_progress" <?= ($editMaintenance && $editMaintenance['status'] === 'in_progress') || (isset($_POST['maintenance_status']) && $_POST['maintenance_status'] === 'in_progress') ? 'selected' : '' ?>>In Corso</option>
                        <option value="completed" <?= ($editMaintenance && $editMaintenance['status'] === 'completed') || (isset($_POST['maintenance_status']) && $_POST['maintenance_status'] === 'completed') ? 'selected' : '' ?>>Completata</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Descrizione</label>
                    <textarea name="maintenance_description" class="form-control" rows="3"><?= $editMaintenance ? htmlspecialchars($editMaintenance['description']) : (isset($_POST['maintenance_description']) ? htmlspecialchars($_POST['maintenance_description']) : '') ?></textarea>
                </div>
                <div class="col-12">
                    <button name="<?= $editMaintenance ? 'update_maintenance' : 'add_maintenance' ?>" class="btn <?= $editMaintenance ? 'btn-warning' : 'btn-info' ?>">
                        <?= $editMaintenance ? 'Modifica Manutenzione' : 'Aggiungi Manutenzione' ?>
                    </button>
                    <?php if ($editMaintenance): ?>
                        <a href="?" class="btn btn-secondary">Annulla</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabella Manutenzioni -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h4>Storico Manutenzioni</h4>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Attrezzatura</th><th>Data</th><th>Costo</th><th>Stato</th><th>Descrizione</th><th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($maintenances as $maintenance): ?>
                        <?php
                        $statusClass = $maintenance['status'] === 'completed' ? 'success' : ($maintenance['status'] === 'in_progress' ? 'warning' : 'secondary');
                        $statusText = $maintenance['status'] === 'completed' ? 'Completata' : ($maintenance['status'] === 'in_progress' ? 'In Corso' : 'Programmata');
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($maintenance['equipment_name']) ?></td>
                            <td><?= date('d/m/Y', strtotime($maintenance['maintenanceDate'])) ?></td>
                            <td>€<?= number_format($maintenance['maintenanceCost'], 2) ?></td>
                            <td><span class="badge bg-<?= $statusClass ?>"><?= $statusText ?></span></td>
                            <td><?= htmlspecialchars(substr($maintenance['description'], 0, 30)) ?><?= strlen($maintenance['description']) > 30 ? '...' : '' ?></td>
                            <td>
                                <a href="?edit_maintenance=<?= $maintenance['maintenanceID'] ?>" class="btn btn-sm btn-warning">Modifica</a>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Sei sicuro di eliminare questa manutenzione?');">
                                    <input type="hidden" name="delete_maintenance_id" value="<?= $maintenance['maintenanceID'] ?>">
                                    <button name="delete_maintenance" class="btn btn-sm btn-danger">Elimina</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($maintenances)): ?>
                        <tr><td colspan="6" class="text-center">Nessuna manutenzione registrata.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>