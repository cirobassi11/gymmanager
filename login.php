<?php
// login.php
require_once 'config.php';

$error = '';

// Se il form è stato inviato
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Pulizia dati ricevuti
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = "Compila tutti i campi.";
    } else {
        // Query preparata per sicurezza SQL injection
        $stmt = $conn->prepare("SELECT userID, password, role, userName FROM USER WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // Supponiamo che la password in DB sia hashed con password_hash
            if ($password == $user['password']) {
                // Login OK: memorizza dati sessione
                $_SESSION['userID'] = $user['userID'];
                $_SESSION['userName'] = $user['userName'];
                $_SESSION['role'] = $user['role'];

                // Reindirizza alla home o pagina protetta
                header('Location: dashboard.php');
                exit();
            } else {
                $error = "Password errata.";
            }
        } else {
            $error = "Utente non trovato.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Palestra - Login</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="login.css" />
</head>
<body class="bg-light">
    <div class="container-fluid vh-100 d-flex align-items-center justify-content-center">
        <div class="card shadow-sm" style="width: 100%; max-width: 400px;">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <h3 class="text-primary">Palestra</h3>
                    <p class="text-muted">Accedi al tuo account</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form action="login.php" method="POST" novalidate>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input
                            type="email"
                            class="form-control"
                            id="email"
                            name="email"
                            required
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        />
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input
                            type="password"
                            class="form-control"
                            id="password"
                            name="password"
                            required
                        />
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mb-3">Accedi</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>