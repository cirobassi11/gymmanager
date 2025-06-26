<?php
require_once 'config.php';
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Controllo accesso admin
if (!isset($_SESSION['userID'], $_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Calcola età da data di nascita
function calcAge($bdate) {
    if (empty($bdate)) return 'N/A';
    try {
        $d1 = new DateTime($bdate);
        $d2 = new DateTime();
        return $d2->diff($d1)->y;
    } catch (Exception $e) {
        return 'N/A';
    }
}

// Gestione POST
$error_message = '';
$success_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        // Controllo conferma password
        if ($_POST['password'] !== $_POST['confirm_password']) {
            $error_message = 'Le password non corrispondono!';
        } elseif (strlen($_POST['password']) < 4) {
            $error_message = 'La password deve essere di almeno 4 caratteri!';
        } elseif (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            $error_message = 'Email non valida!';
        } else {
            $stmt = $conn->prepare("INSERT INTO USER (email, password, userName, firstName, lastName, birthDate, gender, phoneNumber, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param(
                'sssssssss',
                $_POST['email'],
                $_POST['password'],
                $_POST['userName'],
                $_POST['firstName'],
                $_POST['lastName'],
                $_POST['birthDate'],
                $_POST['gender'],
                $_POST['phoneNumber'],
                $_POST['role']
            );
            if ($stmt->execute()) {
                $success_message = 'Utente aggiunto con successo!';
                // Pulisci $_POST per svuotare il form
                unset($_POST);
            } else {
                $error_message = 'Errore durante l\'inserimento dell\'utente.';
            }
        }
    } elseif (isset($_POST['update'])) {
        $hasError = false;
        $userID = (int)$_POST['userID']; // Sanificazione input
        
        // Controllo se l'utente esiste
        $stmt = $conn->prepare("SELECT userID FROM USER WHERE userID = ?");
        $stmt->bind_param('i', $userID);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            $error_message = 'Utente non trovato.';
            $hasError = true;
        }
        
        // Validazione email
        if (!$hasError && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            $error_message = 'Email non valida!';
            $hasError = true;
        }
        
        // Controllo conferma password solo se sono state inserite
        if (!$hasError && (!empty($_POST['password']) || !empty($_POST['confirm_password']))) {
            if ($_POST['password'] !== $_POST['confirm_password']) {
                $error_message = 'Le password non corrispondono!';
                $hasError = true;
            } elseif (strlen($_POST['password']) < 4) {
                $error_message = 'La password deve essere di almeno 4 caratteri!';
                $hasError = true;
            } else {
                // Aggiorna con nuova password
                $stmt = $conn->prepare("UPDATE USER SET email = ?, password = ?, userName = ?, firstName = ?, lastName = ?, birthDate = ?, gender = ?, phoneNumber = ?, role = ? WHERE userID = ?");
                $stmt->bind_param(
                    'sssssssssi',
                    $_POST['email'],
                    $_POST['password'],
                    $_POST['userName'],
                    $_POST['firstName'],
                    $_POST['lastName'],
                    $_POST['birthDate'],
                    $_POST['gender'],
                    $_POST['phoneNumber'],
                    $_POST['role'],
                    $userID
                );
            }
        } elseif (!$hasError) {
            // Aggiorna senza cambiare password
            $stmt = $conn->prepare("UPDATE USER SET email = ?, userName = ?, firstName = ?, lastName = ?, birthDate = ?, gender = ?, phoneNumber = ?, role = ? WHERE userID = ?");
            $stmt->bind_param(
                'ssssssssi',
                $_POST['email'],
                $_POST['userName'],
                $_POST['firstName'],
                $_POST['lastName'],
                $_POST['birthDate'],
                $_POST['gender'],
                $_POST['phoneNumber'],
                $_POST['role'],
                $userID
            );
        }
        
        if (!$hasError) {
            if ($stmt->execute()) {
                $success_message = 'Utente modificato con successo!';
            } else {
                $error_message = 'Errore durante la modifica dell\'utente.';
            }
        }
    } elseif (isset($_POST['delete'])) {
        $deleteID = (int)$_POST['delete_id'];
        if ($deleteID > 0) {
            $stmt = $conn->prepare("DELETE FROM USER WHERE userID = ?");
            $stmt->bind_param('i', $deleteID);
            if ($stmt->execute()) {
                $success_message = 'Utente eliminato con successo!';
            } else {
                $error_message = 'Errore durante l\'eliminazione dell\'utente.';
            }
        } else {
            $error_message = 'ID utente non valido.';
        }
    }
}

// Recupera utenti per ruolo
function getUsersByRole($conn, $role) {
    $stmt = $conn->prepare("SELECT userID, email, userName, firstName, lastName, birthDate, gender, phoneNumber FROM USER WHERE role = ?");
    $stmt->bind_param('s', $role);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Se è una modifica, recupera i dati dell'utente
$editUser = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $userID = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM USER WHERE userID = ?");
    $stmt->bind_param('i', $userID);
    $stmt->execute();
    $editUser = $stmt->get_result()->fetch_assoc();
}

// Funzioni per le statistiche
function getCustomerStats($conn) {
    // Età media clienti
    $stmt = $conn->prepare("SELECT birthDate FROM USER WHERE role = 'customer' AND birthDate IS NOT NULL");
    $stmt->execute();
    $result = $stmt->get_result();
    $ages = [];
    while ($row = $result->fetch_assoc()) {
        $ages[] = calcAge($row['birthDate']);
    }
    $avgAge = !empty($ages) ? round(array_sum($ages) / count($ages), 1) : 0;
    
    // Distribuzione di genere
    $stmt = $conn->prepare("SELECT gender, COUNT(*) as count FROM USER WHERE role = 'customer' GROUP BY gender");
    $stmt->execute();
    $genderStats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Totale clienti con abbonamenti attivi
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT customerID) as count
        FROM SUBSCRIPTION
        WHERE CURDATE() BETWEEN startDate AND expirationDate
    ");
    $stmt->execute();
    $activeSubscriptions = $stmt->get_result()->fetch_assoc()['count'];
    
    return [
        'avgAge' => $avgAge,
        'genderStats' => $genderStats,
        'activeSubscriptions' => $activeSubscriptions
    ];
}

$roles = ['customer' => 'Cliente', 'trainer' => 'Trainer', 'admin' => 'Admin'];
$usersByRole = [];
foreach (array_keys($roles) as $role) {
    $usersByRole[$role] = getUsersByRole($conn, $role);
}

$stats = getCustomerStats($conn);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Gestione Utenti</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <h2>Gestione Utenti</h2>
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
                    <div class="card text-white h-100" style="background: linear-gradient(135deg, #6a85b6 0%, #bac8e0 100%);">
                        <div class="card-body text-center d-flex flex-column justify-content-center">
                            <h3><?= $stats['avgAge'] ?></h3>
                            <p class="mb-0">Età Media Clienti</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white h-100" style="background: linear-gradient(135deg, #a8c8ec 0%, #7fcdcd 100%);">
                        <div class="card-body text-center d-flex flex-column justify-content-center">
                            <h3><?= $stats['activeSubscriptions'] ?></h3>
                            <p class="mb-0">Clienti con Abbonamento</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white h-100" style="background: linear-gradient(135deg, #7fcdcd 0%, #c2e9fb 100%);">
                        <div class="card-body text-center d-flex flex-column justify-content-center">
                            <div style="height: 120px; position: relative; background: rgba(255,255,255,0.2); border-radius: 12px; padding: 8px;">
                                <canvas id="genderChart"></canvas>
                            </div>
                            <p class="mb-0 mt-2">Distribuzione Genere</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Form aggiunta/modifica utente -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <h4><?= $editUser ? 'Modifica Utente' : 'Aggiungi Utente' ?></h4>
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>
            <form method="POST" class="row g-3">
                <?php if ($editUser): ?>
                    <input type="hidden" name="userID" value="<?= $editUser['userID'] ?>">
                <?php endif; ?>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input name="email" required class="form-control" type="email" 
                           value="<?= $editUser ? htmlspecialchars($editUser['email']) : (isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '') ?>" />
                </div>
                <div class="col-md-6">
                    <label class="form-label">Nome Utente</label>
                    <input name="userName" required class="form-control" type="text" 
                           value="<?= $editUser ? htmlspecialchars($editUser['userName']) : (isset($_POST['userName']) ? htmlspecialchars($_POST['userName']) : '') ?>" />
                </div>
                <div class="col-md-6">
                    <label class="form-label">Password <?= $editUser ? '(lascia vuoto per non modificare)' : '' ?></label>
                    <input name="password" <?= $editUser ? '' : 'required' ?> class="form-control" type="password" />
                </div>
                <div class="col-md-6">
                    <label class="form-label">Conferma Password</label>
                    <input name="confirm_password" <?= $editUser ? '' : 'required' ?> class="form-control" type="password" />
                </div>
                <div class="col-md-6">
                    <label class="form-label">Nome</label>
                    <input name="firstName" required class="form-control" type="text" 
                           value="<?= $editUser ? htmlspecialchars($editUser['firstName']) : (isset($_POST['firstName']) ? htmlspecialchars($_POST['firstName']) : '') ?>" />
                </div>
                <div class="col-md-6">
                    <label class="form-label">Cognome</label>
                    <input name="lastName" required class="form-control" type="text" 
                           value="<?= $editUser ? htmlspecialchars($editUser['lastName']) : (isset($_POST['lastName']) ? htmlspecialchars($_POST['lastName']) : '') ?>" />
                </div>
                <div class="col-md-6">
                    <label class="form-label">Data di Nascita</label>
                    <input name="birthDate" required class="form-control" type="date" 
                           value="<?= $editUser ? $editUser['birthDate'] : (isset($_POST['birthDate']) ? $_POST['birthDate'] : '') ?>" />
                </div>
                <div class="col-md-6">
                    <label class="form-label">Telefono</label>
                    <input name="phoneNumber" required class="form-control" type="text" 
                           value="<?= $editUser ? htmlspecialchars($editUser['phoneNumber']) : (isset($_POST['phoneNumber']) ? htmlspecialchars($_POST['phoneNumber']) : '') ?>" />
                </div>
                <div class="col-md-6">
                    <label class="form-label">Genere</label>
                    <select name="gender" class="form-select" required>
                        <option value="M" <?= ($editUser && $editUser['gender'] === 'M') || (isset($_POST['gender']) && $_POST['gender'] === 'M') ? 'selected' : '' ?>>M</option>
                        <option value="F" <?= ($editUser && $editUser['gender'] === 'F') || (isset($_POST['gender']) && $_POST['gender'] === 'F') ? 'selected' : '' ?>>F</option>
                        <option value="Other" <?= ($editUser && $editUser['gender'] === 'Other') || (isset($_POST['gender']) && $_POST['gender'] === 'Other') ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Ruolo</label>
                    <select name="role" class="form-select" required>
                        <option value="customer" <?= ($editUser && $editUser['role'] === 'customer') || (isset($_POST['role']) && $_POST['role'] === 'customer') ? 'selected' : '' ?>>Cliente</option>
                        <option value="trainer" <?= ($editUser && $editUser['role'] === 'trainer') || (isset($_POST['role']) && $_POST['role'] === 'trainer') ? 'selected' : '' ?>>Trainer</option>
                        <option value="admin" <?= ($editUser && $editUser['role'] === 'admin') || (isset($_POST['role']) && $_POST['role'] === 'admin') ? 'selected' : '' ?>>Admin</option>
                    </select>
                </div>
                <div class="col-12">
                    <button name="<?= $editUser ? 'update' : 'add' ?>" class="btn <?= $editUser ? 'btn-warning' : 'btn-success' ?>">
                        <?= $editUser ? 'Modifica Utente' : 'Aggiungi Utente' ?>
                    </button>
                    <?php if ($editUser): ?>
                        <a href="?" class="btn btn-secondary">Annulla</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabelle per ciascun ruolo -->
    <?php foreach ($roles as $key => $label): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h4><?= $label ?> Registrati</h4>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Email</th><th>Nome Utente</th><th>Nome</th><th>Cognome</th>
                            <th>Età</th><th>Genere</th><th>Telefono</th><th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($usersByRole[$key] as $u): ?>
                            <tr>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td><?= htmlspecialchars($u['userName']) ?></td>
                                <td><?= htmlspecialchars($u['firstName']) ?></td>
                                <td><?= htmlspecialchars($u['lastName']) ?></td>
                                <td><?= calcAge($u['birthDate']) ?></td>
                                <td><?= htmlspecialchars($u['gender']) ?></td>
                                <td><?= htmlspecialchars($u['phoneNumber']) ?></td>
                                <td>
                                    <a href="?edit=<?= $u['userID'] ?>" class="btn btn-sm btn-warning">Modifica</a>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Sei sicuro di eliminare questo utente?');">
                                        <input type="hidden" name="delete_id" value="<?= $u['userID'] ?>">
                                        <button name="delete" class="btn btn-sm btn-danger">Elimina</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($usersByRole[$key])): ?>
                            <tr><td colspan="8" class="text-center">Nessun <?= strtolower($label) ?> registrato.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
    const genderData = <?= json_encode($stats['genderStats']) ?>;
    const labels = genderData.map(g => g.gender);
    const data = genderData.map(g => g.count);

    new Chart(document.getElementById('genderChart'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Numero Utenti',
                data: data,
                backgroundColor: ['#8b9dc3', '#ff7675', '#95a5a6'],
                borderColor: ['#6a85b6', '#e84393', '#7f8c8d'],
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(255,255,255,0.95)',
                    titleColor: '#2c3e50',
                    bodyColor: '#2c3e50',
                    borderColor: '#bdc3c7',
                    borderWidth: 1,
                    cornerRadius: 8,
                    callbacks: {
                        label: context => `${context.parsed.x} ${context.label}`
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: { 
                        color: '#2c3e50',
                        font: { size: 11, weight: '500' }
                    },
                    grid: { 
                        color: 'rgba(44, 62, 80, 0.15)',
                        lineWidth: 1
                    }
                },
                y: {
                    ticks: {
                        font: { 
                            weight: 'bold',
                            size: 12
                        },
                        color: '#2c3e50'
                    },
                    grid: { display: false }
                }
            },
            elements: {
                bar: {
                    borderSkipped: false,
                }
            }
        }
    });
</script>
</body>
</html>