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

$currentAdminID = $_SESSION['userID']; // ID dell'admin attualmente loggato

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

// Funzione per verificare se un campo unique esiste già
function checkUniqueField($conn, $field, $value, $excludeUserID = null) {
    $sql = "SELECT userID FROM USER WHERE $field = ?";
    $params = [$value];
    $types = 's';
    
    if ($excludeUserID !== null) {
        $sql .= " AND userID != ?";
        $params[] = $excludeUserID;
        $types .= 'i';
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

// Funzione per contare quanti admin ci sono
function countAdmins($conn) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM USER WHERE role = 'admin'");
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['count'];
}

// Gestione POST
$error_message = '';
$success_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $validation_errors = [];
        
        // Validazioni base
        if ($_POST['password'] !== $_POST['confirm_password']) {
            $validation_errors[] = 'Le password non corrispondono!';
        }
        if (strlen($_POST['password']) < 4) {
            $validation_errors[] = 'La password deve essere di almeno 4 caratteri!';
        }
        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            $validation_errors[] = 'Email non valida!';
        }
        if (empty(trim($_POST['userName']))) {
            $validation_errors[] = 'Il nome utente è obbligatorio!';
        }
        if (empty(trim($_POST['firstName']))) {
            $validation_errors[] = 'Il nome è obbligatorio!';
        }
        if (empty(trim($_POST['lastName']))) {
            $validation_errors[] = 'Il cognome è obbligatorio!';
        }
        
        // Controllo campi unique
        if (checkUniqueField($conn, 'email', $_POST['email'])) {
            $validation_errors[] = 'Esiste già un utente con questa email!';
        }
        
        // Se non ci sono errori, procedi con l'inserimento
        if (empty($validation_errors)) {
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
                // Gestisci errori MySQL specifici
                $mysql_error = $conn->error;
                if (strpos($mysql_error, 'Duplicate entry') !== false) {
                    if (strpos($mysql_error, 'email') !== false) {
                        $error_message = 'Esiste già un utente con questa email!';
                    } else {
                        $error_message = 'Errore: valore duplicato rilevato!';
                    }
                } else {
                    $error_message = 'Errore durante l\'inserimento dell\'utente: ' . $mysql_error;
                }
            }
        } else {
            $error_message = implode('<br>', $validation_errors);
        }
        
    } elseif (isset($_POST['update'])) {
        $validation_errors = [];
        $userID = (int)$_POST['userID'];
        
        // SICUREZZA: Impedisci all'admin di modificare se stesso
        if ($userID === $currentAdminID) {
            $validation_errors[] = 'Non puoi modificare il tuo stesso account per motivi di sicurezza. Chiedi a un altro amministratore.';
        }
        
        // Controllo se l'utente esiste
        if (empty($validation_errors)) {
            $stmt = $conn->prepare("SELECT userID, role FROM USER WHERE userID = ?");
            $stmt->bind_param('i', $userID);
            $stmt->execute();
            $targetUser = $stmt->get_result()->fetch_assoc();
            
            if (!$targetUser) {
                $validation_errors[] = 'Utente non trovato.';
            } else {
                // SICUREZZA: Se stiamo modificando un admin e ce n'è solo uno, impediscilo
                if ($targetUser['role'] === 'admin' && $_POST['role'] !== 'admin') {
                    $adminCount = countAdmins($conn);
                    if ($adminCount <= 1) {
                        $validation_errors[] = 'Non puoi rimuovere il ruolo admin dall\'ultimo amministratore del sistema!';
                    }
                }
            }
        }
        
        // Validazioni base
        if (empty($validation_errors)) {
            if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                $validation_errors[] = 'Email non valida!';
            }
            if (empty(trim($_POST['userName']))) {
                $validation_errors[] = 'Il nome utente è obbligatorio!';
            }
            if (empty(trim($_POST['firstName']))) {
                $validation_errors[] = 'Il nome è obbligatorio!';
            }
            if (empty(trim($_POST['lastName']))) {
                $validation_errors[] = 'Il cognome è obbligatorio!';
            }
            
            // Controllo campi unique (escludendo l'utente corrente)
            if (checkUniqueField($conn, 'email', $_POST['email'], $userID)) {
                $validation_errors[] = 'Esiste già un altro utente con questa email!';
            }
            
            // Controllo password se inserita
            if (!empty($_POST['password']) || !empty($_POST['confirm_password'])) {
                if ($_POST['password'] !== $_POST['confirm_password']) {
                    $validation_errors[] = 'Le password non corrispondono!';
                } elseif (strlen($_POST['password']) < 4) {
                    $validation_errors[] = 'La password deve essere di almeno 4 caratteri!';
                }
            }
        }
        
        if (empty($validation_errors)) {
            // Decidi se aggiornare la password o meno
            if (!empty($_POST['password'])) {
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
            } else {
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
            
            if ($stmt->execute()) {
                $success_message = 'Utente modificato con successo!';
            } else {
                // Gestisci errori MySQL specifici
                $mysql_error = $conn->error;
                if (strpos($mysql_error, 'Duplicate entry') !== false) {
                    if (strpos($mysql_error, 'email') !== false) {
                        $error_message = 'Esiste già un altro utente con questa email!';
                    } else {
                        $error_message = 'Errore: valore duplicato rilevato!';
                    }
                } else {
                    $error_message = 'Errore durante la modifica dell\'utente: ' . $mysql_error;
                }
            }
        } else {
            $error_message = implode('<br>', $validation_errors);
        }
        
    } elseif (isset($_POST['delete'])) {
        $deleteID = (int)$_POST['delete_id'];
        
        // SICUREZZA: Impedisci all'admin di eliminare se stesso
        if ($deleteID === $currentAdminID) {
            $error_message = 'Non puoi eliminare il tuo stesso account per motivi di sicurezza!';
        } elseif ($deleteID > 0) {
            // Controlla se è l'ultimo admin
            $stmt = $conn->prepare("SELECT role FROM USER WHERE userID = ?");
            $stmt->bind_param('i', $deleteID);
            $stmt->execute();
            $targetUser = $stmt->get_result()->fetch_assoc();
            
            if ($targetUser && $targetUser['role'] === 'admin') {
                $adminCount = countAdmins($conn);
                if ($adminCount <= 1) {
                    $error_message = 'Non puoi eliminare l\'ultimo amministratore del sistema!';
                } else {
                    // Procedi con l'eliminazione dopo aver controllato le dipendenze
                    processUserDeletion($conn, $deleteID);
                }
            } else {
                // Non è un admin, procedi normalmente
                $this->processUserDeletion($conn, $deleteID);
            }
        } else {
            $error_message = 'ID utente non valido.';
        }
    }
}

// Funzione per gestire l'eliminazione utente
function processUserDeletion($conn, $deleteID) {
    global $error_message, $success_message;
    
    // Controlla se l'utente può essere eliminato (non ha dipendenze)
    $canDelete = true;
    $dependencies = [];
    
    // Controlla se è un cliente con abbonamenti
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM SUBSCRIPTION WHERE customerID = ?");
    $stmt->bind_param('i', $deleteID);
    $stmt->execute();
    $subscriptions = $stmt->get_result()->fetch_assoc()['count'];
    if ($subscriptions > 0) {
        $dependencies[] = "$subscriptions abbonamento/i";
        $canDelete = false;
    }
    
    // Controlla se è un trainer con corsi assegnati
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM teaching WHERE trainerID = ?");
    $stmt->bind_param('i', $deleteID);
    $stmt->execute();
    $courses = $stmt->get_result()->fetch_assoc()['count'];
    if ($courses > 0) {
        $dependencies[] = "$courses corso/i";
        $canDelete = false;
    }
    
    // Controlla se è un cliente iscritto a corsi
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM enrollment WHERE customerID = ?");
    $stmt->bind_param('i', $deleteID);
    $stmt->execute();
    $enrollments = $stmt->get_result()->fetch_assoc()['count'];
    if ($enrollments > 0) {
        $dependencies[] = "$enrollments iscrizione/i a corsi";
        $canDelete = false;
    }
    
    if ($canDelete) {
        $stmt = $conn->prepare("DELETE FROM USER WHERE userID = ?");
        $stmt->bind_param('i', $deleteID);
        if ($stmt->execute()) {
            $success_message = 'Utente eliminato con successo!';
        } else {
            $error_message = 'Errore durante l\'eliminazione dell\'utente: ' . $conn->error;
        }
    } else {
        $error_message = 'Impossibile eliminare l\'utente. Ha ancora: ' . implode(', ', $dependencies) . '. Rimuovi prima queste dipendenze.';
    }
}

// Recupera utenti per ruolo
function getUsersByRole($conn, $role) {
    $stmt = $conn->prepare("SELECT userID, email, userName, firstName, lastName, birthDate, gender, phoneNumber, role FROM USER WHERE role = ?");
    $stmt->bind_param('s', $role);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Se è una modifica, recupera i dati dell'utente
$editUser = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $userID = (int)$_GET['edit'];
    
    // SICUREZZA: Impedisci all'admin di modificare se stesso
    if ($userID === $currentAdminID) {
        $error_message = 'Non puoi modificare il tuo stesso account per motivi di sicurezza. Chiedi a un altro amministratore.';
    } else {
        $stmt = $conn->prepare("SELECT * FROM USER WHERE userID = ?");
        $stmt->bind_param('i', $userID);
        $stmt->execute();
        $editUser = $stmt->get_result()->fetch_assoc();
    }
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
$adminCount = countAdmins($conn);
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
                <div class="alert alert-danger">
                    <?= $error_message ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="row g-3" id="userForm">
                <?php if ($editUser): ?>
                    <input type="hidden" name="userID" value="<?= $editUser['userID'] ?>">
                <?php endif; ?>
                
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input name="email" required class="form-control" type="email" 
                           value="<?= $editUser ? htmlspecialchars($editUser['email']) : (isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '') ?>" />
                    <div class="form-text">L'email deve essere unica nel sistema</div>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Nome Utente</label>
                    <input name="userName" required class="form-control" type="text" 
                           value="<?= $editUser ? htmlspecialchars($editUser['userName']) : (isset($_POST['userName']) ? htmlspecialchars($_POST['userName']) : '') ?>" />
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Password <?= $editUser ? '(lascia vuoto per non modificare)' : '' ?></label>
                    <input name="password" <?= $editUser ? '' : 'required' ?> class="form-control" type="password" minlength="4" />
                    <div class="form-text">Minimo 4 caratteri</div>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Conferma Password</label>
                    <input name="confirm_password" <?= $editUser ? '' : 'required' ?> class="form-control" type="password" minlength="4" />
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
                    <input name="birthDate" required class="form-control" type="date" max="<?= date('Y-m-d') ?>"
                           value="<?= $editUser ? $editUser['birthDate'] : (isset($_POST['birthDate']) ? $_POST['birthDate'] : '') ?>" />
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Telefono</label>
                    <input name="phoneNumber" required class="form-control" type="tel" 
                           value="<?= $editUser ? htmlspecialchars($editUser['phoneNumber']) : (isset($_POST['phoneNumber']) ? htmlspecialchars($_POST['phoneNumber']) : '') ?>" />
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Genere</label>
                    <select name="gender" class="form-select" required>
                        <option value="">Seleziona genere</option>
                        <option value="M" <?= ($editUser && $editUser['gender'] === 'M') || (isset($_POST['gender']) && $_POST['gender'] === 'M') ? 'selected' : '' ?>>Maschio</option>
                        <option value="F" <?= ($editUser && $editUser['gender'] === 'F') || (isset($_POST['gender']) && $_POST['gender'] === 'F') ? 'selected' : '' ?>>Femmina</option>
                        <option value="Other" <?= ($editUser && $editUser['gender'] === 'Other') || (isset($_POST['gender']) && $_POST['gender'] === 'Other') ? 'selected' : '' ?>>Altro</option>
                    </select>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Ruolo</label>
                    <select name="role" class="form-select" required>
                        <option value="">Seleziona ruolo</option>
                        <option value="customer" <?= ($editUser && $editUser['role'] === 'customer') || (isset($_POST['role']) && $_POST['role'] === 'customer') ? 'selected' : '' ?>>Cliente</option>
                        <option value="trainer" <?= ($editUser && $editUser['role'] === 'trainer') || (isset($_POST['role']) && $_POST['role'] === 'trainer') ? 'selected' : '' ?>>Trainer</option>
                        <option value="admin" <?= ($editUser && $editUser['role'] === 'admin') || (isset($_POST['role']) && $_POST['role'] === 'admin') ? 'selected' : '' ?>>Admin</option>
                    </select>
                    <?php if ($editUser && $editUser['role'] === 'admin' && $adminCount <= 1): ?>
                        <div class="form-text text-warning">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            Questo è l'ultimo amministratore: non puoi rimuovere il ruolo admin!
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="col-12">
                    <button name="<?= $editUser ? 'update' : 'add' ?>" class="btn <?= $editUser ? 'btn-warning' : 'btn-success' ?>" type="submit">
                        <?= $editUser ? 'Modifica Utente' : 'Aggiungi Utente' ?>
                    </button>
                    <?php if ($editUser): ?>
                        <a href="?" class="btn btn-secondary">
                            Annulla
                        </a>
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
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Email</th><th>Nome Utente</th><th>Nome</th><th>Cognome</th>
                                <th>Età</th><th>Genere</th><th>Telefono</th><th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($usersByRole[$key] as $u): ?>
                                <?php 
                                $isCurrentAdmin = ($u['userID'] == $currentAdminID);
                                $isLastAdmin = ($key === 'admin' && $adminCount <= 1 && $u['userID'] == $currentAdminID);
                                ?>
                                <tr <?= $isCurrentAdmin ? 'class="table-info"' : '' ?>>
                                    <td><?= htmlspecialchars($u['email']) ?></td>
                                    <td><?= htmlspecialchars($u['userName']) ?></td>
                                    <td><?= htmlspecialchars($u['firstName']) ?></td>
                                    <td><?= htmlspecialchars($u['lastName']) ?></td>
                                    <td><?= calcAge($u['birthDate']) ?></td>
                                    <td><?= htmlspecialchars($u['gender']) ?></td>
                                    <td><?= htmlspecialchars($u['phoneNumber']) ?></td>
                                    <td>
                                        <?php if ($isCurrentAdmin): ?>
                                            <span class="text-muted">
                                                Non modificabile (sei tu)
                                            </span>
                                        <?php else: ?>
                                            <a href="?edit=<?= $u['userID'] ?>" class="btn btn-sm btn-warning" title="Modifica utente">
                                                Modifica
                                            </a>
                                            <?php if ($isLastAdmin): ?>
                                                <button class="btn btn-sm btn-secondary" disabled title="Non puoi eliminare l'ultimo admin">
                                                    Protetto
                                                </button>
                                            <?php else: ?>
                                                <form method="POST" style="display:inline" onsubmit="return confirm('Sei sicuro di eliminare questo utente?\n\nATTENZIONE: L\'operazione non può essere annullata.');">
                                                    <input type="hidden" name="delete_id" value="<?= $u['userID'] ?>">
                                                    <button name="delete" class="btn btn-sm btn-danger" title="Elimina utente">
                                                        Elimina
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($usersByRole[$key])): ?>
                                <tr><td colspan="8" class="text-center text-muted">Nessun <?= strtolower($label) ?> registrato.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Script per validazione lato client -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('userForm');
        const passwordField = document.querySelector('input[name="password"]');
        const confirmPasswordField = document.querySelector('input[name="confirm_password"]');
        const roleField = document.querySelector('select[name="role"]');
        
        // Validazione password in tempo reale
        function validatePasswords() {
            const password = passwordField.value;
            const confirmPassword = confirmPasswordField.value;
            
            if (password && confirmPassword) {
                if (password !== confirmPassword) {
                    confirmPasswordField.setCustomValidity('Le password non corrispondono');
                    confirmPasswordField.classList.add('is-invalid');
                } else {
                    confirmPasswordField.setCustomValidity('');
                    confirmPasswordField.classList.remove('is-invalid');
                    confirmPasswordField.classList.add('is-valid');
                }
            }
        }
        
        passwordField.addEventListener('input', validatePasswords);
        confirmPasswordField.addEventListener('input', validatePasswords);
        
        // Validazione email
        const emailField = document.querySelector('input[name="email"]');
        emailField.addEventListener('blur', function() {
            const email = this.value;
            if (email && !email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                this.setCustomValidity('Inserisci un indirizzo email valido');
                this.classList.add('is-invalid');
            } else {
                this.setCustomValidity('');
                this.classList.remove('is-invalid');
                if (email) this.classList.add('is-valid');
            }
        });
        
        // Protezione cambio ruolo admin
        const isEditingAdmin = <?= $editUser && $editUser['role'] === 'admin' ? 'true' : 'false' ?>;
        const adminCount = <?= $adminCount ?>;
        
        if (isEditingAdmin && adminCount <= 1) {
            roleField.addEventListener('change', function() {
                if (this.value !== 'admin') {
                    alert('Non puoi rimuovere il ruolo admin dall\'ultimo amministratore del sistema!');
                    this.value = 'admin';
                }
            });
        }
        
        // Validazione form prima dell'invio
        form.addEventListener('submit', function(e) {
            if (isEditingAdmin && adminCount <= 1 && roleField.value !== 'admin') {
                e.preventDefault();
                alert('Operazione non consentita: non puoi rimuovere il ruolo admin dall\'ultimo amministratore!');
                return false;
            }
        });
    });
    
    // Grafico distribuzione genere
    const genderData = <?= json_encode($stats['genderStats']) ?>;
    if (genderData && genderData.length > 0) {
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
    }
</script>
</body>
</html>