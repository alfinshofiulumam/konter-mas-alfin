<?php
session_start();
require_once 'config.php';
require_once 'auth.php';
hanyaAdmin();

// === KUNCI MUTLAK: CUMA ADMIN YANG BOLEH EDIT ===
requireRole(['admin']);

$id = (int)($_GET['id'] ?? 0);
if($id <= 0){
    echo "<script>alert('ID tidak valid!');window.location='stok.php';</script>";
    exit();
}

$stmt = $conn->prepare("SELECT * FROM produk WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$p = $stmt->get_result()->fetch_assoc();

if(!$p){
    echo "<script>alert('Produk ID $id tidak ditemukan!');window.location='stok.php';</script>";
    exit();
}

if(isset($_POST['update'])){
    $nama = trim($_POST['nama']);
    $kategori = $_POST['kategori'];
    $merk = trim($_POST['merk']);
    $modal = (int)$_POST['modal'];
    $harga_jual = (int)$_POST['harga_jual'];
    $stok = (int)$_POST['stok'];

    if($modal < 0 || $harga_jual < 0 || $stok < 0){
        echo "<script>alert('Modal / Harga / Stok tidak boleh minus!');</script>";
    } else {
        $stmt2 = $conn->prepare("UPDATE produk SET nama=?, kategori=?, merk=?, modal=?, harga_jual=?, stok=? WHERE id=?");
        $stmt2->bind_param("sssiiii", $nama, $kategori, $merk, $modal, $harga_jual, $stok, $id);
        if($stmt2->execute()){
            echo "<script>alert('UPDATE SUKSES! Produk ID $id berhasil diupdate!');window.location='stok.php';</script>";
            exit();
        } else {
            echo "<script>alert('Gagal update: ".$conn->error."');</script>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Produk - V5.3 Admin Only</title>
    <link href="bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: Arial; padding: 20px; background: #f4f4f4; }
        .box { background: white; padding: 25px; border-radius: 12px; max-width: 500px; margin: auto; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="box">
        <h4 class="fw-bold">✏️ Edit Produk ID <?= $p['id'] ?> - V5.3</h4>
        <p class="text-muted small">Login: <?= htmlspecialchars($_SESSION['username'] ?? 'admin') ?> | 👑 ADMIN ONLY - Bisa Liat Modal</p>
        <form method="POST">
            <label>Nama Produk</label>
            <input type="text" name="nama" class="form-control mb-2" value="<?= htmlspecialchars($p['nama']) ?>" required>

            <label>Kategori</label>
            <select name="kategori" class="form-control mb-2" required>
                <option value="">-- Pilih Kategori --</option>
                <option value="PULSA" <?= $p['kategori']=='PULSA'?'selected':'' ?>>PULSA</option>
                <option value="PERDANA" <?= $p['kategori']=='PERDANA'?'selected':'' ?>>PERDANA</option>
                <option value="VOUCHER" <?= $p['kategori']=='VOUCHER'?'selected':'' ?>>VOUCHER</option>
                <option value="AKSESORIS" <?= strtoupper($p['kategori'])=='AKSESORIS' || strtoupper($p['kategori'])=='ACCESORRIES' || strtoupper($p['kategori'])=='ACCESSORIES' ?'selected':'' ?>>AKSESORIS</option>
            </select>

            <label>Merk</label>
            <input type="text" name="merk" class="form-control mb-2" value="<?= htmlspecialchars($p['merk']) ?>" required>

            <label>🔒 Modal (HPP) - <span class="badge bg-danger">ADMIN ONLY</span></label>
            <input type="number" name="modal" class="form-control mb-2" value="<?= $p['modal'] ?>" min="0" required>
            <small class="text-muted d-block mb-2" style="font-size:11px;">Hanya admin yang bisa liat & edit modal. Pramuniaga tidak bisa akses halaman ini.</small>

            <label>Harga Jual</label>
            <input type="number" name="harga_jual" class="form-control mb-2" value="<?= $p['harga_jual'] ?>" min="0" required>

            <label>Stok</label>
            <input type="number" name="stok" class="form-control mb-2" value="<?= $p['stok'] ?>" min="0" required>

            <button name="update" class="btn btn-success w-100 fw-bold">UPDATE - V5.3 Admin Only</button>
        </form>
        <br><a href="stok.php" class="btn btn-secondary w-100">Batal Kembali ke Stok</a>
    </div>
</body>
</html>