<?php
session_start();
require_once 'config.php';

$error = '';
$identity = '';
$loginNotice = $_SESSION['login_notice'] ?? '';
unset($_SESSION['login_notice']);

if (isset($_SESSION['admin'])) {
    header('Location: admin.php');
    exit;
}

if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identity = trim($_POST['identity'] ?? '');
    $password = $_POST['password'] ?? '';
    $db = connectDatabase();

    $adminQuery = $db->prepare(
        'SELECT id, username, password FROM admins WHERE email = ? LIMIT 1'
    );
    $adminQuery->execute([$identity]);
    $admin = $adminQuery->fetch();

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin'] = [
            'id' => (int) $admin['id'],
            'username' => $admin['username'],
        ];

        header('Location: admin.php');
        exit;
    }

    $query = $db->prepare(
        'SELECT id, name, password FROM users WHERE email = ? LIMIT 1'
    );
    $query->execute([$identity]);
    $account = $query->fetch();

    if ($account && password_verify($password, $account['password'])) {
        $_SESSION['user'] = [
            'id' => (int) $account['id'],
            'name' => $account['name'],
        ];

        header('Location: index.php');
        exit;
    }

    $error = 'Email atau password tidak sesuai.';
}
?>

<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Masuk — TraaBooks</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="brand.css">
    <link rel="stylesheet" href="auth.css">
</head>
<body class="auth-page">
    <main class="auth-card">
        <a class="brand" href="index.php">
            <img src="images/logo-trabooks.png" alt="Logo TraaBooks">
            <span>TraaBooks</span>
        </a>

        <p class="eyebrow">Selamat datang</p>
        <h1>Masuk ke akunmu.</h1>

        <?php if ($loginNotice): ?>
            <p class="auth-info">
                <?= htmlspecialchars($loginNotice) ?>
            </p>
        <?php endif; ?>

        <?php if ($error): ?>
            <p class="auth-error">
                <?= htmlspecialchars($error) ?>
            </p>
        <?php endif; ?>

        <form class="auth-form" method="post">
            <label>
                Email
                <input
                    type="email"
                    name="identity"
                    value="<?= htmlspecialchars($identity) ?>"
                    required
                >
            </label>

            <label>
                Password
                <input
                    type="password"
                    name="password"
                    required
                >
            </label>

            <button class="button dark" type="submit">Masuk</button>
        </form>

        <p class="auth-switch">
            Belum punya akun?
            <a href="register.php">Buat akun sekarang</a>
        </p>
    </main>
</body>
</html>