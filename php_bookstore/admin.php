<?php
session_start();
require_once 'config.php';
if (!isset($_SESSION['admin'])) { header('Location: admin_login.php'); exit; }
$db = connectDatabase(); $message = ''; $error = '';
function rupiahAdmin($price) { return 'Rp' . number_format($price, 0, ',', '.'); }
function categoryExists(PDO $db, $name) { $q = $db->prepare('SELECT COUNT(*) FROM categories WHERE name = ?'); $q->execute([$name]); return (bool)$q->fetchColumn(); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_category') {
        $name = trim($_POST['category_name'] ?? '');
        try { if ($name === '') throw new Exception(); $db->prepare('INSERT INTO categories (name) VALUES (?)')->execute([$name]); $message = 'Kategori ditambahkan.'; } catch (Throwable $e) { $error = 'Kategori kosong atau sudah ada.'; }
    }
    if ($action === 'update_category') {
        $id = (int)$_POST['category_id']; $new = trim($_POST['category_name'] ?? ''); $q = $db->prepare('SELECT name FROM categories WHERE id=?'); $q->execute([$id]); $old = $q->fetchColumn();
        try { if (!$old || $new === '') throw new Exception(); $db->beginTransaction(); $db->prepare('UPDATE categories SET name=? WHERE id=?')->execute([$new,$id]); $db->prepare('UPDATE books SET category=? WHERE category=?')->execute([$new,$old]); $db->commit(); $message = 'Kategori diperbarui.'; } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); $error = 'Kategori gagal diperbarui.'; }
    }
    if ($action === 'delete_category') {
        $id=(int)$_POST['category_id']; $q=$db->prepare('SELECT name FROM categories WHERE id=?'); $q->execute([$id]); $name=$q->fetchColumn(); $q=$db->prepare('SELECT COUNT(*) FROM books WHERE category=?'); $q->execute([$name]);
        if (!$name) $error='Kategori tidak ditemukan.'; elseif ($q->fetchColumn()) $error='Kategori masih dipakai oleh buku.'; else { $db->prepare('DELETE FROM categories WHERE id=?')->execute([$id]); $message='Kategori dihapus.'; }
    }
    if ($action === 'delete_book') { try { $db->prepare('DELETE FROM books WHERE id=?')->execute([(int)$_POST['book_id']]); $message='Buku dihapus.'; } catch (PDOException $e) { $error='Buku sudah tercatat pada pesanan dan tidak bisa dihapus.'; } }
    if ($action === 'update_order') { $status=$_POST['status']??''; $paymentStatus=$_POST['payment_status']??''; if(in_array($status,['baru','diproses','selesai','dibatalkan'],true) && in_array($paymentStatus,['menunggu','menunggu_verifikasi','lunas'],true)) { $db->prepare('UPDATE orders SET status=?, payment_status=? WHERE id=?')->execute([$status,$paymentStatus,(int)$_POST['order_id']]); $message='Status pesanan diperbarui.'; } }
    if ($action === 'save_book') {
        $id=(int)($_POST['book_id']??0); $title=trim($_POST['title']??''); $author=trim($_POST['author']??''); $category=$_POST['category']??''; $price=(int)($_POST['price']??0); $stock=max(0,(int)($_POST['stock']??0)); $cover='';
        if ($id) { $q=$db->prepare('SELECT cover FROM books WHERE id=?'); $q->execute([$id]); $cover=$q->fetchColumn(); }
        if ($title==='' || $author==='' || $price<1 || !categoryExists($db,$category)) $error='Lengkapi data buku dengan benar.';
        if (!$error && isset($_FILES['cover']) && $_FILES['cover']['error']===UPLOAD_ERR_OK) { $types=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp']; $mime=mime_content_type($_FILES['cover']['tmp_name']); if (!isset($types[$mime])) $error='Sampul harus JPG, PNG, atau WEBP.'; else { $file='book-'.bin2hex(random_bytes(8)).'.'.$types[$mime]; if (move_uploaded_file($_FILES['cover']['tmp_name'],__DIR__.'/images/'.$file)) $cover='images/'.$file; else $error='Gagal mengunggah sampul.'; } }
        if (!$error && !$cover) $error='Unggah gambar sampul buku.';
        if (!$error) { if ($id) { $db->prepare('UPDATE books SET title=?,author=?,category=?,price=?,stock=?,cover=? WHERE id=?')->execute([$title,$author,$category,$price,$stock,$cover,$id]); $message='Buku diperbarui.'; } else { $db->prepare('INSERT INTO books (title,author,category,price,stock,cover) VALUES (?,?,?,?,?,?)')->execute([$title,$author,$category,$price,$stock,$cover]); $message='Buku ditambahkan.'; } }
    }
}
$categories=$db->query('SELECT id,name FROM categories ORDER BY name')->fetchAll();
$books=$db->query('SELECT id,title,author,category,price,stock,cover FROM books ORDER BY id DESC')->fetchAll();
$users=$db->query('SELECT name,email,created_at FROM users ORDER BY id DESC')->fetchAll();
$orders=$db->query('SELECT o.id,o.order_code,o.buyer_name,o.phone,o.address,o.total,o.payment_method,o.payment_proof,o.payment_status,o.status,u.name,u.email FROM orders o LEFT JOIN users u ON u.id=o.user_id ORDER BY o.id DESC')->fetchAll();
$editing=null; if(isset($_GET['edit'])) { $q=$db->prepare('SELECT * FROM books WHERE id=?');$q->execute([(int)$_GET['edit']]);$editing=$q->fetch(); }
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Admin — TraaBooks</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="style.css"><link rel="stylesheet" href="brand.css"><link rel="stylesheet" href="admin.css">
<style>
/* Popup bukti pembayaran */
.proof-modal {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 999999;
    background: rgba(0, 0, 0, .68);
    justify-content: center;
    align-items: center;
    padding: 20px;
}
.proof-modal.active { display: flex; }
.proof-content {
    background: #fff;
    color: #222;
    width: min(100%, 680px);
    max-height: 90vh;
    overflow: auto;
    border-radius: 16px;
    padding: 22px;
    box-shadow: 0 18px 60px rgba(0,0,0,.25);
}
.proof-header, .proof-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.proof-header h2 { margin: 0; }
.proof-close {
    border: 0;
    background: transparent;
    color: #333;
    font-size: 30px;
    line-height: 1;
    cursor: pointer;
}
.proof-image-container {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 160px;
    margin: 18px 0;
}
.proof-image-container img {
    display: block;
    max-width: 100%;
    max-height: 60vh;
    object-fit: contain;
}
.proof-actions { justify-content: flex-end; }
.proof-actions button, .proof-link {
    cursor: pointer;
}
.proof-link {
    margin-top: 7px;
    padding: 6px 10px;
    border: 0;
    border-radius: 7px;
    background: #eee5da;
    color: #4b3526;
    font: inherit;
}
.proof-pdf, .proof-cancel {
    border: 0;
    border-radius: 8px;
    padding: 10px 15px;
    font: inherit;
}
.proof-pdf { background: #4b3526; color: #fff; }
.proof-cancel { background: #eee; color: #222; }

/* Saat dicetak / disimpan sebagai PDF, hanya gambar bukti yang ditampilkan. */
@media print {
    @page { margin: 12mm; }
    body * { visibility: hidden !important; }
    #proofModal, #proofModal * { visibility: visible !important; }
    #proofModal {
        display: block !important;
        position: static !important;
        inset: auto !important;
        padding: 0 !important;
        background: #fff !important;
        width: 100% !important;
        height: auto !important;
    }
    #proofModal .proof-content {
        display: block !important;
        width: 100% !important;
        max-width: none !important;
        max-height: none !important;
        overflow: visible !important;
        padding: 0 !important;
        box-shadow: none !important;
        border-radius: 0 !important;
    }
    #proofModal .proof-header,
    #proofModal .proof-actions { display: none !important; }
    #proofModal .proof-image-container {
        margin: 0 !important;
        min-height: 0 !important;
    }
    #proofModal .proof-image-container img {
        max-width: 100% !important;
        max-height: 250mm !important;
        object-fit: contain !important;
    }
}
</style>

</head>
<body class="admin-page"><header class="admin-header"><a class="brand" href="index.php"><img src="images/logo-trabooks.png" alt="Logo TraaBooks"><span>TraaBooks</span></a><div><a href="index.php">Kembali ke Toko</a><a class="admin-out" href="admin_logout.php">Keluar</a></div></header><main class="admin-main"><p class="eyebrow">Dashboard administrator</p><h1>Kelola TraaBooks</h1><?php if($message):?><p class="admin-message"><?=htmlspecialchars($message)?></p><?php endif;?><?php if($error):?><p class="admin-error"><?=htmlspecialchars($error)?></p><?php endif;?>
<section class="admin-form-card"><h2><?=$editing?'Edit Buku':'Tambah Buku Baru'?></h2><form class="book-form" method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="save_book"><input type="hidden" name="book_id" value="<?=$editing['id']??0?>"><label>Judul buku<input name="title" value="<?=htmlspecialchars($editing['title']??'')?>" required></label><label>Penulis<input name="author" value="<?=htmlspecialchars($editing['author']??'')?>" required></label><label>Kategori<select name="category"><?php foreach($categories as $c):?><option value="<?=htmlspecialchars($c['name'])?>" <?=($editing['category']??'')===$c['name']?'selected':''?>><?=htmlspecialchars($c['name'])?></option><?php endforeach;?></select></label><label>Harga (Rp)<input type="number" name="price" min="1" value="<?=$editing['price']??''?>" required></label><label>Stok<input type="number" name="stock" min="0" value="<?=$editing['stock']??0?>" required></label><label class="cover-field">Sampul <?=$editing?'(kosongkan jika tidak diganti)':''?><input type="file" name="cover" accept="image/jpeg,image/png,image/webp" <?=$editing?'':'required'?>></label><button class="button dark" type="submit"><?=$editing?'Simpan Perubahan':'Simpan Buku'?></button></form></section>
<section class="admin-form-card"><h2>Kelola Kategori</h2><form class="category-add" method="post"><input type="hidden" name="action" value="add_category"><input name="category_name" placeholder="Kategori baru" required><button type="submit">Tambah</button></form><div class="category-list"><?php foreach($categories as $c):?><div><form method="post"><input type="hidden" name="action" value="update_category"><input type="hidden" name="category_id" value="<?=$c['id']?>"><input name="category_name" value="<?=htmlspecialchars($c['name'])?>"><button>Simpan</button></form><form method="post"><input type="hidden" name="action" value="delete_category"><input type="hidden" name="category_id" value="<?=$c['id']?>"><button class="danger">Hapus</button></form></div><?php endforeach;?></div></section>
<section class="admin-books"><h2>Daftar Buku (<?=count($books)?>)</h2><div class="admin-book-list"><?php foreach($books as $book):?><article class="admin-book"><img src="<?=htmlspecialchars($book['cover'])?>" alt=""><div><b><?=htmlspecialchars($book['title'])?></b><span><?=htmlspecialchars($book['author'])?> · <?=htmlspecialchars($book['category'])?></span><span><?=rupiahAdmin($book['price'])?> · Stok <?=intval($book['stock'])?></span></div><a href="admin.php?edit=<?=$book['id']?>">Edit</a><form method="post"><input type="hidden" name="action" value="delete_book"><input type="hidden" name="book_id" value="<?=$book['id']?>"><button>Hapus</button></form></article><?php endforeach;?></div></section>
<section class="admin-books"><h2>Daftar User (<?=count($users)?>)</h2><div class="data-table"><table><thead><tr><th>Nama</th><th>Email</th><th>Terdaftar</th></tr></thead><tbody><?php foreach($users as $u):?><tr><td><?=htmlspecialchars($u['name'])?></td><td><?=htmlspecialchars($u['email'])?></td><td><?=htmlspecialchars($u['created_at'])?></td></tr><?php endforeach;?></tbody></table></div></section>
<section class="admin-books"><h2>Daftar Pesanan (<?=count($orders)?>)</h2><div class="data-table"><table><thead><tr><th>Kode</th><th>Pembeli & Kontak</th><th>Alamat</th><th>Total</th><th>Pembayaran</th><th>Status</th></tr></thead><tbody><?php foreach($orders as $o):?><tr><td><?=htmlspecialchars($o['order_code'])?></td><td><?=htmlspecialchars($o['buyer_name'])?><small><?=htmlspecialchars($o['phone'])?></small></td><td><?=nl2br(htmlspecialchars($o['address']))?></td><td><?=rupiahAdmin($o['total'])?></td><td><?=htmlspecialchars($o['payment_method'])?><small><?=htmlspecialchars($o['payment_status'])?></small>
    <?php if($o['payment_proof']):?>
<button
    type="button"
    class="proof-link"
    data-proof="<?=htmlspecialchars($o['payment_proof'], ENT_QUOTES, 'UTF-8')?>"
    onclick="openProof(this.dataset.proof)">
    Bukti bayar
</button>
<?php endif;?>
</td><td><form method="post"><input type="hidden" name="action" value="update_order"><input type="hidden" name="order_id" value="<?=$o['id']?>"><select name="status"><option value="baru" <?=$o['status']==='baru'?'selected':''?>>baru</option><option value="diproses" <?=$o['status']==='diproses'?'selected':''?>>diproses</option><option value="selesai" <?=$o['status']==='selesai'?'selected':''?>>selesai</option><option value="dibatalkan" <?=$o['status']==='dibatalkan'?'selected':''?>>dibatalkan</option></select><select name="payment_status"><option value="menunggu" <?=$o['payment_status']==='menunggu'?'selected':''?>>menunggu</option><option value="menunggu_verifikasi" <?=$o['payment_status']==='menunggu_verifikasi'?'selected':''?>>verifikasi</option><option value="lunas" <?=$o['payment_status']==='lunas'?'selected':''?>>lunas</option></select><button>Simpan</button></form></td></tr><?php endforeach;?>
</tbody>
</table>
</div>
</section>
</main>
<!-- POPUP BUKTI PEMBAYARAN -->
<div id="proofModal" class="proof-modal">

    <div class="proof-content">

        <div class="proof-header">
            <h2>Bukti Pembayaran</h2>

            <button type="button"
                    class="proof-close"
                    onclick="closeProof()">
                &times;
            </button>
        </div>

        <div class="proof-image-container">
            <img id="proofImage"
                 src=""
                 alt="Bukti Pembayaran">
        </div>

        <div class="proof-actions">

            <button type="button"
                    class="proof-pdf"
                    onclick="printProof()">
                <span>↓</span> Unduh PDF
            </button>

            <button type="button"
                    class="proof-cancel"
                    onclick="closeProof()">
                Tutup
            </button>

        </div>

    </div>

</div>

<script>
(function () {
    function getProofElements() {
        return {
            modal: document.getElementById('proofModal'),
            image: document.getElementById('proofImage')
        };
    }

    window.openProof = function (imageUrl) {
        const { modal, image } = getProofElements();
        if (!modal || !image || !imageUrl) {
            alert('Popup atau alamat bukti pembayaran tidak ditemukan.');
            return;
        }
        image.onload = function () {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        };
        image.onerror = function () {
            alert('Gambar bukti pembayaran tidak dapat dibuka. Periksa lokasi file bukti.');
            image.onload = null;
            image.onerror = null;
        };
        image.src = imageUrl;
        // Tampilkan modal langsung agar tidak bergantung pada event pemuatan gambar.
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    };

    window.closeProof = function () {
        const { modal, image } = getProofElements();
        if (modal) modal.classList.remove('active');
        if (image) {
            image.onload = null;
            image.onerror = null;
            image.src = '';
        }
        document.body.style.overflow = '';
    };

    window.printProof = function () {
        const { image } = getProofElements();
        if (!image || !image.src || !image.complete || !image.naturalWidth) {
            alert('Tunggu sampai gambar bukti pembayaran selesai dimuat.');
            return;
        }
        window.print();
    };

    document.addEventListener('DOMContentLoaded', function () {
        const { modal } = getProofElements();
        if (!modal) return;

        modal.addEventListener('click', function (event) {
            if (event.target === modal) window.closeProof();
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modal.classList.contains('active')) {
                window.closeProof();
            }
        });
    });
})();
</script>
</body>
</html>
