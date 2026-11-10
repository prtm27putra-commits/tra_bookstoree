<?php
session_start();
require_once 'config.php';

if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
        $error = 'Isi nama dan email yang valid. Password minimal 6 karakter.';
    } else {
        try {
            $db = connectDatabase();
            $query = $db->prepare('INSERT INTO users (name, email, password) VALUES (?, ?, ?)');
            $query->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            $_SESSION['user'] = ['id' => (int) $db->lastInsertId(), 'name' => $name];
            header('Location: index.php');
            exit;
        } catch (PDOException $exception) {
            $error = 'Email tersebut sudah terdaftar.';
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daftar — TraaBooks</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="brand.css">
    <link rel="stylesheet" href="auth.css">
    <style>
        .required-note {
            color: #6b7280;
            font-size: 0.85em;
            font-weight: 400;
            margin-left: 6px;
        }

        .required-star {
            color: #e53935;
            font-weight: 700;
        }
    </style>
</head>
<body class="auth-page">
    <main class="auth-card">
        <a class="brand" href="index.php"><img src="images/logo-trabooks.png" alt="Logo TraaBooks"><span>TraaBooks</span></a>
        <p class="eyebrow">Buat akun</p>
        <h1>Mulai perjalanan membacamu.</h1>

        <?php if ($error): ?>
            <p class="auth-error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form class="auth-form" method="post">
            <label>
                Nama lengkap
                <span class="required-note">Data harus lengkap <span class="required-star">*</span></span>
                <input type="text" name="name" value="<?= htmlspecialchars($name) ?>" required>
            </label>

            <label>
                Email
                <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
            </label>

            <label>
                Password
                <input type="password" name="password" minlength="6" required>
            </label>

            <button class="button dark" type="submit">Daftar</button>
        </form>

        <p class="auth-switch">Sudah punya akun? <a href="login.php">Masuk di sini</a></p>
    </main>
</body>
</html>