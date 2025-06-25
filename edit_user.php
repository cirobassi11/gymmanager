<?php
require_once 'config.php';

session_start();

if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Validazione parametro GET
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "ID cliente non valido.";
    exit();
}

$userID = intval($_GET['id']);

// Recupero dati del cliente
$stmt = $conn->prepare("SELECT userID, email, userName, firstName, lastName, birthDate, gender, phoneNumber FROM USER WHERE userID = ? AND role = 'customer'");
$stmt->bind_param('i', $userID);
$stmt->execute();
$result = $stmt->get_result();
$customer = $result->fetch_assoc();

if (!$customer) {
    echo "Cliente non trovato.";
    exit();
}

// Aggiornamento dati se POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $conn->prepare("UPDATE USER SET email = ?, userName = ?, firstName = ?, lastName = ?, birthDate = ?, gender = ?, phoneNumber = ? WHERE userID = ?");
    $stmt->bind_param(
        'sssssssi',
        $_POST['email'],
        $_POST['userName'],
        $_POST['firstName'],
        $_POST['lastName'],
        $_POST['birthDate'],
        $_POST['gender'],
        $_POST['phone'],
        $userID
    );
    $stmt->execute();

    header('Location: user_management.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Modifica Cliente</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h4>Modifica Cliente</h4>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="mb-3">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($customer['email']) ?>">
                </div>
                <div class="mb-3">
                    <label>Username</label>
                    <input type="text" name="userName" class="form-control" required value="<?= htmlspecialchars($customer['userName']) ?>">
                </div>
                <div class="mb-3">
                    <label>Nome</label>
                    <input type="text" name="firstName" class="form-control" required value="<?= htmlspecialchars($customer['firstName']) ?>">
                </div>
                <div class="mb-3">
                    <label>Cognome</label>
                    <input type="text" name="lastName" class="form-control" required value="<?= htmlspecialchars($customer['lastName']) ?>">
                </div>
                <div class="mb-3">
                    <label>Data di nascita</label>
                    <input type="date" name="birthDate" class="form-control" value="<?= htmlspecialchars($customer['birthDate']) ?>">
                </div>
                <div class="mb-3">
                    <label>Genere</label>
                    <select name="gender" class="form-select">
                        <option value="M" <?= $customer['gender'] === 'M' ? 'selected' : '' ?>>Maschio</option>
                        <option value="F" <?= $customer['gender'] === 'F' ? 'selected' : '' ?>>Femmina</option>
                        <option value="Other" <?= $customer['gender'] === 'Other' ? 'selected' : '' ?>>Altro</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label>Telefono</label>
                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($customer['phoneNumber']) ?>">
                </div>
                <div class="d-flex justify-content-between">
                    <a href="user_management.php" class="btn btn-secondary">Annulla</a>
                    <button type="submit" class="btn btn-success">Salva Modifiche</button>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>