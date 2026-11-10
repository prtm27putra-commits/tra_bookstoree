<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['admin'])) {
    header('Location: admin_login.php');
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $db = connectDatabase();
    $query = $db->prepare('SELECT password FROM admins WHERE id = ?');
    $query->execute([$_SESSION['admin']['id']]);
    $passwordHash = $query->fetchColumn();

    if (!password_verify($currentPassword, $passwordHash)) $error = 'Password lama tidak sesuai.';
    elseif (strlen($newPassword) < 6) $error = 'Password baru minimal 6 karakter.';
    elseif ($newPassword !== $confirmPassword) $error = 'Konfirmasi password baru tidak sama.';
    else { $db->prepare('UPDATE admins SET password = ? WHERE id = ?')->execute([password_hash($newPassword, PASSWORD_DEFAULT), $_SESSION['admin']['id']]); $message = 'Password admin berhasil diubah.'; }
}
?>
<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Ubah Password Admin — TraaBooks</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="style.css"><link rel="stylesheet" href="brand.css"><link rel="stylesheet" href="auth.css"></head><body class="auth-page admin-auth-page"><main class="auth-card"><a class="brand" href="admin.php"><img src="images/logo-trabooks.png" alt="Logo TraaBooks"><span>TraaBooks</span></a><p class="eyebrow">Keamanan Admin</p><h1>Ubah Password</h1><?php if($message):?><p class="auth-info"><?=htmlspecialchars($message)?></p><?php endif;?><?php if($error):?><p class="auth-error"><?=htmlspecialchars($error)?></p><?php endif;?><form class="auth-form" method="post"><label>Password lama<input type="password" name="current_password" required></label><label>Password baru<input type="password" name="new_password" minlength="6" required></label><label>Konfirmasi password baru<input type="password" name="confirm_password" minlength="6" required></label><button class="button dark" type="submit">Simpan Password Baru</button></form><p class="auth-switch"><a href="admin.php">Kembali ke dashboard</a></p></main></body></html>
