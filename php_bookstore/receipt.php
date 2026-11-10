
<?php
session_start();

require_once 'config.php';

$db = connectDatabase();

// Pastikan pengguna sudah login
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user']['id'];
$orderId = (int) ($_GET['id'] ?? 0);

// Ambil data pesanan milik pengguna yang sedang login
$stmt = $db->prepare(
    'SELECT * FROM orders WHERE id = ? AND user_id = ?'
);

$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    http_response_code(404);
    exit('Pesanan tidak ditemukan atau kamu tidak memiliki akses.');
}

// Ambil detail buku
$stmt = $db->prepare(
    'SELECT oi.quantity, oi.price, b.title
     FROM order_items oi
     LEFT JOIN books b ON b.id = oi.book_id
     WHERE oi.order_id = ?'
);

$stmt->execute([$orderId]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format rupiah
function rupiah($price) {
    return 'Rp ' . number_format($price, 0, ',', '.');
}

// Ambil tanggal transaksi
$dateValue = $order['created_at']
    ?? $order['order_date']
    ?? $order['tanggal']
    ?? null;

$dateFormatted = '-';

if ($dateValue) {
    try {
        $dateFormatted = date(
            'd F Y H:i',
            strtotime($dateValue)
        );
    } catch (Throwable $e) {
        $dateFormatted = $dateValue;
    }
}

// Nomor pesanan
$orderNumber = '#' . str_pad(
    $order['id'],
    5,
    '0',
    STR_PAD_LEFT
);

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Struk Pesanan - TraaBooks</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 35px 20px;
            background: #F1F6FC;
            color: #334155;
            font-family: 'DM Sans', sans-serif;
        }

        .receipt-wrapper {
            max-width: 850px;
            margin: auto;
        }

        /* TOMBOL */

        .receipt-actions {
            display: flex;
            justify-content: space-between;
            margin-bottom: 24px;
        }

        .receipt-actions a,
        .receipt-actions button {
            border: none;
            text-decoration: none;
            padding: 14px 24px;
            border-radius: 9px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
            transition: .2s;
        }

        .back-btn {
            background: #E4EEF9;
            color: #285783;
        }

        .back-btn:hover {
            background: #D3E4F6;
        }

        .print-btn {
            background: #2864A5;
            color: white;
        }

        .print-btn:hover {
            background: #194D85;
        }

        /* STRUK */

        .receipt {
            background: #FFFFFF;
            border: 1px solid #DCE8F5;
            border-radius: 15px;
            padding: 45px;
            box-shadow: 0 8px 30px rgba(40, 100, 165, .07);
        }

        /* HEADER */

        .receipt-header {
            text-align: center;
            padding-bottom: 25px;
            border-bottom: 1px dashed #D5E3F1;
        }

        .receipt-logo {
            font-family: 'Playfair Display', serif;
            font-size: 36px;
            font-weight: 700;
            color: #2864A5;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .receipt-logo span {
            color: #79A9D8;
        }

        /* IKON BUKU */

        .book-icon {
            width: 34px;
            height: 34px;
            color: #2864A5;
            flex-shrink: 0;
        }

        .receipt-subtitle {
            margin-top: 5px;
            color: #8495A8;
            font-size: 14px;
        }

        /* INFORMASI PESANAN */

        .receipt-info {
            padding: 25px 0;
            border-bottom: 1px dashed #D5E3F1;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin: 11px 0;
            font-size: 14px;
        }

        .info-row strong {
            text-align: right;
            color: #243B53;
        }

        /* DETAIL PESANAN */

        .section-title {
            font-size: 18px;
            margin: 27px 0 20px;
            color: #285783;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
        }

        .items-table th {
            background: #EAF2FB;
            color: #285783;
            padding: 14px 10px;
            text-align: left;
            font-size: 14px;
        }

        .items-table td {
            padding: 16px 10px;
            border-bottom: 1px solid #E8EFF7;
            font-size: 14px;
            color: #475569;
        }

        .items-table th:nth-child(2),
        .items-table td:nth-child(2) {
            text-align: center;
        }

        .items-table th:last-child,
        .items-table td:last-child {
            text-align: right;
        }

        .book-price {
            display: block;
            color: #8495A8;
            font-size: 12px;
            margin-top: 4px;
        }

        /* TOTAL PEMBAYARAN */

        .total-section {
            border-top: 1px dashed #D5E3F1;
            margin-top: 25px;
            padding-top: 23px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 20px;
            font-weight: 700;
            color: #2864A5;
        }

        /* STATUS PEMBAYARAN */

        .payment-status {
            display: inline-block;
            margin-top: 20px;
            padding: 9px 14px;
            border-radius: 30px;
            background: #EAF2FB;
            color: #2864A5;
            font-size: 12px;
            font-weight: 700;
        }

        /* FOOTER */

        .receipt-footer {
            text-align: center;
            border-top: 1px dashed #D5E3F1;
            margin-top: 30px;
            padding-top: 25px;
            color: #8495A8;
            font-size: 13px;
            line-height: 1.8;
        }

        .receipt-footer strong {
            display: block;
            font-family: 'Playfair Display', serif;
            font-size: 20px;
            color: #2864A5;
        }

        /* RESPONSIVE */

        @media (max-width: 600px) {

            body {
                padding: 15px 10px;
            }

            .receipt {
                padding: 22px 16px;
            }

            .receipt-logo {
                font-size: 29px;
            }

            .info-row {
                font-size: 12px;
                gap: 10px;
            }

            .items-table th,
            .items-table td {
                padding: 12px 5px;
                font-size: 12px;
            }

            .total-row {
                font-size: 17px;
            }

        }

        /* CETAK STRUK */

        @media print {

            body {
                background: white;
                padding: 0;
            }

            .receipt-wrapper {
                max-width: 100%;
            }

            .receipt {
                border: none;
                box-shadow: none;
                padding: 25px;
            }

            .receipt-actions {
                display: none;
            }

            @page {
                size: A4;
                margin: 15mm;
            }

        }

    </style>

</head>

<body>

<div class="receipt-wrapper">

    <div class="receipt-actions">

        <a href="index.php" class="back-btn">
            ← Kembali
        </a>

        <button class="print-btn" onclick="window.print()">
            Cetak Struk
        </button>

    </div>

    <div class="receipt">

        <div class="receipt-header">

            <h1 class="receipt-logo">

                <!-- Ikon buku -->
                <svg class="book-icon"
                     xmlns="http://www.w3.org/2000/svg"
                     viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-width="1.8"
                     stroke-linecap="round"
                     stroke-linejoin="round"
                     aria-hidden="true">

                    <path d="M12 7v14"/>
                    <path d="M3 18V5a2 2 0 0 1 2-2h3a4 4 0 0 1 4 4 4 4 0 0 1 4-4h3a2 2 0 0 1 2 2v13"/>
                    <path d="M3 18a2 2 0 0 1 2-2h3a4 4 0 0 1 4 4"/>
                    <path d="M21 18a2 2 0 0 0-2-2h-3a4 4 0 0 0-4 4"/>

                </svg>

                Traa<span>Books</span>

            </h1>

            <div class="receipt-subtitle">
                Struk / Bukti Pesanan
            </div>

        </div>

        <div class="receipt-info">

            <div class="info-row">
                <span>No. Pesanan</span>
                <strong><?= htmlspecialchars($orderNumber) ?></strong>
            </div>

            <div class="info-row">
                <span>Tanggal</span>
                <strong><?= htmlspecialchars($dateFormatted) ?></strong>
            </div>

            <div class="info-row">
                <span>Nama Pemesan</span>
                <strong><?= htmlspecialchars($order['buyer_name']) ?></strong>
            </div>

            <div class="info-row">
                <span>Nomor HP</span>
                <strong><?= htmlspecialchars($order['phone']) ?></strong>
            </div>

            <div class="info-row">
                <span>Metode Bayar</span>
                <strong><?= htmlspecialchars($order['payment_method']) ?></strong>
            </div>

            <div class="info-row">
                <span>Alamat</span>
                <strong><?= nl2br(htmlspecialchars($order['address'])) ?></strong>
            </div>

        </div>

        <h2 class="section-title">
            Detail Pesanan
        </h2>

        <table class="items-table">

            <thead>
                <tr>
                    <th>Buku</th>
                    <th>Qty</th>
                    <th>Subtotal</th>
                </tr>
            </thead>

            <tbody>

                <?php foreach ($items as $item): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars($item['title'] ?? 'Buku tidak tersedia') ?>

                            <span class="book-price">
                                <?= rupiah($item['price']) ?>
                            </span>
                        </td>

                        <td>
                            <?= (int) $item['quantity'] ?>
                        </td>

                        <td>
                            <?= rupiah($item['price'] * $item['quantity']) ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

        <div class="total-section">

            <div class="total-row">

                <span>Total Pembayaran</span>

                <span><?= rupiah($order['total']) ?></span>

            </div>

            <div class="payment-status">
                <?= htmlspecialchars($order['payment_status'] ?? 'menunggu') ?>
            </div>

        </div>

        <div class="receipt-footer">

            <strong>Terima Kasih!</strong>

            Terima kasih telah berbelanja di TraaBooks.

            <br>

            Setiap buku punya cerita, setiap pembaca punya perjalanan.

            <br><br>

            <b>TraaBooks — Read more, grow more.</b>

        </div>

    </div>

</div>

</body>
</html>
