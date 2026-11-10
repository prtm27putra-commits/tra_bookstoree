<?php
session_start();

require_once 'config.php';

$db = connectDatabase();

$search = trim($_GET['search'] ?? '');

$categories = $db->query(
    'SELECT name FROM categories ORDER BY name'
)->fetchAll(PDO::FETCH_COLUMN);

$booksQuery = 'SELECT id, title, author, category, price, stock, cover FROM books';
$booksParams = [];

if ($search !== '') {
    $booksQuery .= ' WHERE title LIKE ? OR author LIKE ? OR category = ?';
    $booksParams = ["%$search%", "%$search%", $search];
}

$booksQuery .= ' ORDER BY id';

$statement = $db->prepare($booksQuery);
$statement->execute($booksParams);

$books = $statement->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);

$allBooks = $db->query(
    'SELECT id, title, author, category, price, stock, cover FROM books ORDER BY id'
)->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);

$_SESSION['cart'] ??= [];

$notice = '';
$checkoutError = '';

$checkout = [
    'buyer_name' => $_SESSION['user']['name'] ?? '',
    'phone' => '',
    'address' => '',
    'payment_method' => 'COD',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['book_id'] ?? 0);

    if ($action === 'add' && !isset($_SESSION['user'])) {
        $_SESSION['login_notice'] = 'Silakan masuk terlebih dahulu untuk membeli buku.';
        header('Location: login.php');
        exit;
    }

    if ($action === 'add' && isset($allBooks[$id])) {
        $quantity = $_SESSION['cart'][$id] ?? 0;

        if ($quantity >= $allBooks[$id]['stock']) {
            $notice = 'Stok buku tidak mencukupi.';
        } else {
            $_SESSION['cart'][$id] = $quantity + 1;
            $notice = 'Buku berhasil ditambahkan ke keranjang.';
        }
    }

    if ($action === 'remove' && isset($_SESSION['cart'][$id])) {
        unset($_SESSION['cart'][$id]);
        $notice = 'Buku dihapus dari keranjang.';
    }

    if ($action === 'checkout' && !isset($_SESSION['user'])) {
        $_SESSION['login_notice'] = 'Silakan masuk terlebih dahulu untuk membuat pesanan.';
        header('Location: login.php');
        exit;
    }

    if ($action === 'checkout' && $_SESSION['cart']) {
        $checkout['buyer_name'] = trim($_POST['buyer_name'] ?? '');
        $checkout['phone'] = trim($_POST['phone'] ?? '');
        $checkout['address'] = trim($_POST['address'] ?? '');
        $checkout['payment_method'] = $_POST['payment_method'] ?? 'COD';

        $paymentOptions = ['COD', 'Transfer Bank', 'E-Wallet'];

        if (
            $checkout['buyer_name'] === '' ||
            $checkout['phone'] === '' ||
            $checkout['address'] === '' ||
            !in_array($checkout['payment_method'], $paymentOptions, true)
        ) {
            $checkoutError = 'Lengkapi nama, nomor HP, alamat, dan metode pembayaran terlebih dahulu.';
        } else {
            $totalOrder = 0;

            foreach ($_SESSION['cart'] as $bookId => $quantity) {
                if (isset($allBooks[$bookId])) {
                    $totalOrder += $allBooks[$bookId]['price'] * $quantity;
                }
            }

            $db->beginTransaction();

            try {
                foreach ($_SESSION['cart'] as $bookId => $quantity) {
                    $stock = $db->prepare(
                        'UPDATE books SET stock = stock - ? WHERE id = ? AND stock >= ?'
                    );

                    $stock->execute([$quantity, $bookId, $quantity]);

                    if ($stock->rowCount() !== 1) {
                        throw new RuntimeException('Stok salah satu buku sudah habis.');
                    }
                }

                $paymentProof = null;

                if ($checkout['payment_method'] !== 'COD') {
                    if (
                        !isset($_FILES['payment_proof']) ||
                        $_FILES['payment_proof']['error'] === UPLOAD_ERR_NO_FILE
                    ) {
                        throw new RuntimeException('Unggah bukti bayar untuk Transfer Bank atau E-Wallet.');
                    }

                    if ($_FILES['payment_proof']['error'] !== UPLOAD_ERR_OK) {
                        throw new RuntimeException('Bukti bayar gagal diunggah. Silakan coba lagi.');
                    }

                    $allowed = [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp'
                    ];

                    $mime = mime_content_type($_FILES['payment_proof']['tmp_name']);

                    if (!isset($allowed[$mime])) {
                        throw new RuntimeException('Bukti bayar harus berupa JPG, PNG, atau WEBP.');
                    }

                    $filename = 'payment-' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];

                    if (!move_uploaded_file(
                        $_FILES['payment_proof']['tmp_name'],
                        __DIR__ . '/images/' . $filename
                    )) {
                        throw new RuntimeException('Gagal mengunggah bukti bayar.');
                    }

                    $paymentProof = 'images/' . $filename;
                }

                $orderCode = 'TRB-' . date('YmdHis') . '-' . random_int(100, 999);
                $userId = $_SESSION['user']['id'] ?? null;
                $paymentStatus = $paymentProof
                    ? 'menunggu_verifikasi'
                    : 'menunggu';

                $order = $db->prepare(
                    'INSERT INTO orders
                    (user_id, buyer_name, phone, address, order_code, total,
                    payment_method, payment_proof, payment_status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );

                $order->execute([
                    $userId,
                    $checkout['buyer_name'],
                    $checkout['phone'],
                    $checkout['address'],
                    $orderCode,
                    $totalOrder,
                    $checkout['payment_method'],
                    $paymentProof,
                    $paymentStatus
                ]);

                $orderId = (int) $db->lastInsertId();

                $item = $db->prepare(
                    'INSERT INTO order_items (order_id, book_id, quantity, price)
                    VALUES (?, ?, ?, ?)'
                );

                foreach ($_SESSION['cart'] as $bookId => $quantity) {
                    $item->execute([
                        $orderId,
                        $bookId,
                        $quantity,
                        $allBooks[$bookId]['price']
                    ]);
                }

                $db->commit();
            } catch (Throwable $exception) {
                $db->rollBack();
                $checkoutError = $exception->getMessage();
            }

            if ($checkoutError === '') {
                $_SESSION['cart'] = [];

                header('Location: receipt.php?id=' . $orderId);
                exit;
            }
        }
    }
}

$cartCount = array_sum($_SESSION['cart']);
$total = 0;

foreach ($_SESSION['cart'] as $id => $quantity) {
    if (isset($allBooks[$id])) {
        $total += $allBooks[$id]['price'] * $quantity;
    }
}

function rupiah($price)
{
    return 'Rp' . number_format($price, 0, ',', '.');
}
?>

<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TraaBooks — Toko Buku</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="cart.css">
    <link rel="stylesheet" href="brand.css">
    <link rel="stylesheet" href="about.css">
    <link rel="stylesheet" href="whatsapp.css">
    <link rel="stylesheet" href="account.css">
    <link rel="stylesheet" href="features.css">

    <style>
        .category-dropdown {
            position: relative;
        }

        .category-trigger {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 0;
            border: 0;
            background: transparent;
            color: inherit;
            font: inherit;
            cursor: pointer;
        }

        .category-menu {
            position: absolute;
            top: calc(100% + 12px);
            left: 0;
            z-index: 20;
            display: none;
            min-width: 160px;
            padding: 7px;
            border: 1px solid #D7E2EC;
            background: #fff;
            box-shadow: 0 8px 20px rgba(16, 42, 67, .12);
        }

        .category-dropdown:hover .category-menu,
        .category-dropdown:focus-within .category-menu {
            display: grid;
        }

        .category-menu a {
            padding: 10px 12px;
            color: #102A43;
            text-decoration: none;
        }

        .category-menu a:hover,
        .category-menu a:focus {
            background: #EAF2F8;
        }
    </style>
</head>

<body>
<header class="navbar">
    <a class="brand" href="#home">
        <img src="images/logo-trabooks.png" alt="Logo TraaBooks">
        <span>TraaBooks</span>
    </a>

    <nav>
        <div class="category-dropdown">
            <button class="category-trigger" type="button" aria-haspopup="true">
                Kategori <span aria-hidden="true">▾</span>
            </button>
            <div class="category-menu">
                <?php foreach ($categories as $category): ?>
                    <?php $categoryAnchor = strtolower(str_replace(' ', '-', $category)); ?>
                    <a href="#<?= htmlspecialchars($categoryAnchor) ?>">
                        <?= htmlspecialchars($category) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <a href="#tentang">Tentang Kami</a>
    </nav>

    <form class="search-form" method="get" action="index.php" id="searchForm">
        <input
            type="search"
            name="search"
            id="searchInput"
            value="<?= htmlspecialchars($search) ?>"
            placeholder="Cari buku atau penulis"
        >
        <button type="submit">Cari</button>
    </form>

    <div class="header-actions">
        <?php if (isset($_SESSION['user'])): ?>
            <span class="user-name">
                Hai, <?= htmlspecialchars($_SESSION['user']['name']) ?>
            </span>
            <a class="account-link" href="orders.php">Pesanan Saya</a>
            <a class="account-link" href="logout.php">Keluar</a>
        <?php else: ?>
            <a class="account-link" href="login.php">Masuk</a>
            <a class="account-link register-link" href="register.php">Daftar</a>
        <?php endif; ?>

        <a class="cart-btn" href="#keranjang">
            Keranjang <span><?= $cartCount ?></span>
        </a>
    </div>
</header>

<main id="home">
    <section class="hero">
        <div class="hero-copy">
            <p class="eyebrow">Buku untuk setiap perjalanan</p>
            <h1>
                Temukan cerita<br>
                yang tinggal lebih lama.
            </h1>
            <p class="hero-text">
                Pilihan buku untuk menemani ruang paling sunyi dalam dirimu.
            </p>
            <a class="button dark" href="#fiksi">
                Belanja Buku <span>→</span>
            </a>
        </div>

        <div class="hero-art">
            <div class="sun"></div>
            <div class="arch"></div>
            <img src="images/hero-rak-buku.jpg" alt="Rak buku TraaBooks">
            <div class="hero-note">
                <span>✦</span>
                <p>4 buku<br><b>pilihan</b></p>
            </div>
        </div>
    </section>

    <?php if ($notice): ?>
        <p class="notice"><?= htmlspecialchars($notice) ?></p>
    <?php endif; ?>

    <?php foreach ($categories as $position => $category): ?>
        <?php $anchor = strtolower(str_replace(' ', '-', $category)); ?>

        <section class="collection <?= $position % 2 ? 'alt' : '' ?>" id="<?= htmlspecialchars($anchor) ?>">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Kategori</p>
                    <h2><?= htmlspecialchars($category) ?></h2>
                </div>
                <p class="section-copy">
                    Pilihan buku <?= htmlspecialchars(strtolower($category)) ?> TraaBooks.
                </p>
            </div>

            <?php $categoryBooks = array_filter($books, fn($book) => $book['category'] === $category); ?>

            <?php if ($categoryBooks): ?>
                <div class="books">
                    <?php foreach ($books as $id => $book): ?>
                        <?php if ($book['category'] !== $category) continue; ?>

                        <article class="book-card">
                            <div class="cover-wrap">
                                <span class="tag"><?= htmlspecialchars($category) ?></span>
                                <img
                                    src="<?= htmlspecialchars($book['cover']) ?>"
                                    alt="Sampul <?= htmlspecialchars($book['title']) ?>"
                                >
                            </div>

                            <div class="book-info">
                                <p><?= htmlspecialchars($book['author']) ?></p>
                                <h3><?= htmlspecialchars($book['title']) ?></h3>
                                <b><?= rupiah($book['price']) ?></b>
                                <small class="stock-label">Stok: <?= $book['stock'] ?></small>

                                <form method="post">
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="book_id" value="<?= $id ?>">
                                    <button
                                        class="add-button"
                                        type="submit"
                                        <?= $book['stock'] < 1 ? 'disabled' : '' ?>
                                    >
                                        <?= $book['stock'] < 1 ? 'Stok Habis' : 'Tambah ke keranjang' ?>
                                    </button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="empty">Belum ada buku pada kategori ini.</p>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>

    <section class="cart-section" id="keranjang">
        <p class="eyebrow">Pesananmu</p>
        <h2>Keranjang Belanja</h2>

        <?php if (!$cartCount): ?>
            <p class="empty">
                Keranjang masih kosong. Pilih buku yang ingin kamu baca.
            </p>
        <?php else: ?>
            <div class="cart-list">
                <?php foreach ($_SESSION['cart'] as $id => $quantity): ?>
                    <?php if (!isset($allBooks[$id])) continue; ?>
                    <?php $book = $allBooks[$id]; ?>

                    <div class="cart-item">
                        <img src="<?= htmlspecialchars($book['cover']) ?>" alt="">
                        <div>
                            <h3><?= htmlspecialchars($book['title']) ?></h3>
                            <p><?= $quantity ?> × <?= rupiah($book['price']) ?></p>
                        </div>
                        <form method="post">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="book_id" value="<?= $id ?>">
                            <button class="remove-button" type="submit">Hapus</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="cart-total">
                <b>Total</b>
                <strong><?= rupiah($total) ?></strong>

                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="checkout">

                    <div class="checkout-form">
                        <h3>Data Pengiriman</h3>

                        <?php if ($checkoutError): ?>
                            <p class="checkout-error">
                                <?= htmlspecialchars($checkoutError) ?>
                            </p>
                        <?php endif; ?>

                        <label>
                            Nama pembeli
                            <input
                                type="text"
                                name="buyer_name"
                                value="<?= htmlspecialchars($checkout['buyer_name']) ?>"
                                required
                            >
                        </label>

                        <label>
                            Nomor HP / WhatsApp
                            <input
                                type="tel"
                                name="phone"
                                value="<?= htmlspecialchars($checkout['phone']) ?>"
                                placeholder="08xxxxxxxxxx"
                                required
                            >
                        </label>

                        <label class="full-field">
                            Alamat lengkap
                            <textarea name="address" rows="3" required><?= htmlspecialchars($checkout['address']) ?></textarea>
                        </label>

                        <label class="full-field">
                            Metode pembayaran
                            <select name="payment_method" id="paymentMethod">
                                <option value="COD" <?= $checkout['payment_method'] === 'COD' ? 'selected' : '' ?>>
                                    COD — Bayar saat pesanan diterima
                                </option>
                                <option value="Transfer Bank" <?= $checkout['payment_method'] === 'Transfer Bank' ? 'selected' : '' ?>>
                                    Transfer Bank
                                </option>
                                <option value="E-Wallet" <?= $checkout['payment_method'] === 'E-Wallet' ? 'selected' : '' ?>>
                                    E-Wallet
                                </option>
                            </select>
                        </label>

                        <div id="paymentProofSection">
                            <div class="payment-guide full-field">
                                <b>Instruksi Pembayaran</b>
                                <span>
                                    Transfer Bank: MANDIRI (60014544757) a.n. NURAINI HANNY A.
                                    E-Wallet: DANA 089636809446.
                                    Unggah bukti bayar setelah melakukan pembayaran.
                                </span>
                            </div>

                            <label class="full-field">
                                Bukti bayar (untuk Transfer atau E-Wallet)
                                <input
                                    type="file"
                                    id="paymentProofInput"
                                    name="payment_proof"
                                    accept="image/jpeg,image/png,image/webp"
                                >
                            </label>
                        </div>
                    </div>

                    <button class="button dark" type="submit">
                        Buat Pesanan <span>→</span>
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </section>

    <section class="journal" id="tentang">
        <div>
            <p class="eyebrow">About Us</p>
            <h2>Tentang TraaBooks</h2>
            <p>
                TraaBooks adalah toko buku online yang hadir untuk membantu setiap pembaca
                menemukan buku yang tepat. Kami memilih bacaan fiksi dan nonfiksi yang
                inspiratif, relevan, dan mudah dijangkau.
            </p>
            <p>
                Kami percaya satu buku dapat membuka cara pandang baru, menumbuhkan
                kebiasaan baik, dan menemani perjalanan hidup.
            </p>
        </div>

        <div class="about-values">
            <div>
                <b>Kurasi Pilihan</b>
                <span>Buku yang dipilih untuk menemani pembaca bertumbuh.</span>
            </div>
            <div>
                <b>Untuk Semua</b>
                <span>Koleksi fiksi dan nonfiksi untuk beragam minat baca.</span>
            </div>
            <blockquote>“Read more, grow more.”</blockquote>
        </div>
    </section>
</main>

<footer>
    <a class="brand brand-footer" href="#home">
        <img src="images/logo-trabooks.png" alt="Logo TraaBooks">
        <span>TraaBooks</span>
    </a>
    <p>© 2026 TraaBooks. Dibuat untuk para pembaca.</p>
    <div>
        <a href="#home">Instagram</a>
        <a href="#home">Newsletter</a>
    </div>
</footer>

<a
    class="whatsapp-button"
    href="https://wa.me/6289636809446"
    target="_blank"
    rel="noopener"
    aria-label="Chat WhatsApp TraaBooks"
>
    <span class="whatsapp-icon">☎</span>
    <span>WhatsApp<br><b>089636809446</b></span>
</a>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchForm = document.getElementById('searchForm');
    const searchInput = document.getElementById('searchInput');
    let searchTimer;

    if (searchForm && searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimer);

            searchTimer = setTimeout(function () {
                searchForm.requestSubmit();
            }, 500);
        });
    }

    const paymentMethod = document.getElementById('paymentMethod');
    const proofSection = document.getElementById('paymentProofSection');
    const proofInput = document.getElementById('paymentProofInput');

    if (!paymentMethod || !proofSection || !proofInput) return;

    function updatePaymentProofField() {
        const needsProof = paymentMethod.value !== 'COD';

        proofSection.hidden = !needsProof;
        proofInput.required = needsProof;

        if (!needsProof) {
            proofInput.value = '';
        }
    }

    paymentMethod.addEventListener('change', updatePaymentProofField);
    updatePaymentProofField();
});
</script>

</body>
</html>