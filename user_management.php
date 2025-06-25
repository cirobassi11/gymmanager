<?php
require_once 'config.php';
session_start();

// Controllo accesso admin
if (!isset($_SESSION['userID'], $_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Calcola età da data di nascita
function calcAge($bdate) {
    $d1 = new DateTime($bdate);
    $d2 = new DateTime();
    return $d2->diff($d1)->y;
}

// Gestione POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
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
            $_POST['phone'],
            $_POST['role']
        );
        $stmt->execute();
    } elseif (isset($_POST['delete'])) {
        $stmt = $conn->prepare("DELETE FROM USER WHERE userID = ?");
        $stmt->bind_param('i', $_POST['delete_id']);
        $stmt->execute();
    }
}

// Recupera utenti per ruolo
function getUsersByRole($conn, $role) {
    $stmt = $conn->prepare("SELECT userID, email, userName, firstName, lastName, birthDate, gender, phoneNumber FROM USER WHERE role = ?");
    $stmt->bind_param('s', $role);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$roles = ['customer' => 'Cliente', 'trainer' => 'Trainer', 'admin' => 'Admin'];
$usersByRole = [];
foreach (array_keys($roles) as $role) {
    $usersByRole[$role] = getUsersByRole($conn, $role);
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Gestione Utenti</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h2>Gestione Utenti</h2>
        </div>
    </div>

    <!-- Form aggiunta utente -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <h4>Aggiungi Utente</h4>
            <form method="POST" class="row g-3">
                <?php foreach(['email','userName','password','firstName','lastName','birthDate','phone'] as $field): ?>
                    <div class="col-md-6">
                        <label class="form-label"><?= ucfirst($field) ?></label>
                        <input name="<?= $field ?>" required class="form-control" type="<?= $field === 'birthDate' ? 'date' : 'text' ?>" />
                    </div>
                <?php endforeach; ?>
                <div class="col-md-6">
                    <label class="form-label">Genere</label>
                    <select name="gender" class="form-select" required>
                        <option value="M">M</option>
                        <option value="F">F</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Ruolo</label>
                    <select name="role" class="form-select" required>
                        <option value="customer">Cliente</option>
                        <option value="trainer">Trainer</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="col-12">
                    <button name="add" class="btn btn-primary">Aggiungi</button>
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
                            <th>ID</th><th>Email</th><th>Nome</th><th>Cognome</th>
                            <th>Età</th><th>Genere</th><th>Telefono</th><th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($usersByRole[$key] as $u): ?>
                            <tr>
                                <td><?= $u['userID'] ?></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td><?= htmlspecialchars($u['firstName']) ?></td>
                                <td><?= htmlspecialchars($u['lastName']) ?></td>
                                <td><?= calcAge($u['birthDate']) ?></td>
                                <td><?= htmlspecialchars($u['gender']) ?></td>
                                <td><?= htmlspecialchars($u['phoneNumber']) ?></td>
                                <td>
                                    <a href="edit_user.php?id=<?= $u['userID'] ?>" class="btn btn-sm btn-secondary">Modifica</a>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="delete_id" value="<?= $u['userID'] ?>">
                                        <button name="delete" class="btn btn-sm btn-danger" onclick="return confirm('Eliminare?')">Elimina</button>
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
</body>
</html>