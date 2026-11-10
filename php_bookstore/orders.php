<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$db = connectDatabase();
$userId = (int) $_SESSION['user']['id'];

$ordersQuery = $db->prepare(
    'SELECT id, order_code, buyer_name, phone, address, total, payment_method, payment_proof, payment_status, status, created_at
     FROM orders
     WHERE user_id = ?
     ORDER BY id DESC'
);
$ordersQuery->execute([$userId]);
$orders = $ordersQuery->fetchAll();

$itemsQuery = $db->prepare(
    'SELECT books.title, order_items.quantity, order_items.price
     FROM order_items
     JOIN books ON books.id = order_items.book_id
     WHERE order_items.order_id = ?'
);

function rupiahOrder($price)
{
    return 'Rp' . number_format($price, 0, ',', '.');
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Pesanan Saya — TraaBooks</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap"
        rel="stylesheet"
    >
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="brand.css">
    <link rel="stylesheet" href="features.css">
</head>
<body class="orders-page">
    <header class="orders-header">
        <a class="brand" href="index.php">
            <img src="images/logo-trabooks.png" alt="Logo TraaBooks">
            <span>TraaBooks</span>
        </a>
        <a href="index.php">Kembali ke Toko</a>
    </header>

    <main class="orders-main">
        <p class="eyebrow">Akun saya</p>
        <h1>Pesanan Saya</h1>

        <?php if (!$orders): ?>
            <div class="order-card">
                <p>Belum ada pesanan. Yuk cari buku favoritmu.</p>
                <a class="button dark" href="index.php">Belanja Buku</a>
            </div>
        <?php endif; ?>

        <?php foreach ($orders as $order): ?>
            <article class="order-card">
                <div class="order-head">
                    <div>
                        <span><?= htmlspecialchars($order['order_code']) ?></span>
                        <small><?= htmlspecialchars($order['created_at']) ?></small>
                    </div>
                    <b class="status-badge"><?= htmlspecialchars($order['status']) ?></b>
                </div>

                <div class="order-detail">
                    <div>
                        <b>Pengiriman</b>
                        <p>
                            <?= htmlspecialchars($order['buyer_name']) ?> ·
                            <?= htmlspecialchars($order['phone']) ?><br>
                            <?= nl2br(htmlspecialchars($order['address'])) ?>
                        </p>
                    </div>
                    <div>
                        <b>Pembayaran</b>
                        <p>
                            <?= htmlspecialchars($order['payment_method']) ?><br>
                            Status: <?= htmlspecialchars($order['payment_status']) ?>
                        </p>
                    </div>
                </div>

                <div class="order-items">
                    <?php
                    $itemsQuery->execute([$order['id']]);

                    foreach ($itemsQuery->fetchAll() as $item):
                    ?>
                        <p>
                            <?= htmlspecialchars($item['title']) ?>
                            <span>
                                <?= $item['quantity'] ?> ×
                                <?= rupiahOrder($item['price']) ?>
                            </span>
                        </p>
                    <?php endforeach; ?>
                </div>

                <div class="order-total">
                    <b>Total</b>
                    <strong><?= rupiahOrder($order['total']) ?></strong>
                </div>

                <?php if ($order['payment_proof']): ?>
                    <a
                        class="proof-link"
                        href="<?= htmlspecialchars($order['payment_proof']) ?>"
                        target="_blank"
                    >Lihat bukti pembayaran</a>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </main>
</body>
</html>