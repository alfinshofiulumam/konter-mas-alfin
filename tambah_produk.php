<?php
require_once 'config.php';
require_once 'auth.php';
hanyaAdmin();

// === FIX PENTING: Nyalain lagi error display SETELAH config.php yang matiin ===
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF); // Matikan mode STRICT biar gak jadi 500, kita handle manual

requireRole(['admin']);

$pesan_error = "";

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    try {
        $nama = trim($_POST['nama'] ?? '');
        $kategori = $_POST['kategori'] ?? '';
        $merk = trim($_POST['merk'] ?? '');
        $stok = (int)($_POST['stok'] ?? 0);
        $modal = (int)($_POST['modal'] ?? 0);
        $harga_jual = (int)($_POST['harga_jual'] ?? 0);



        // CEK DUPLIKAT - TANPA get_result()
        $cek = $conn->prepare("SELECT id FROM produk WHERE LOWER(nama) = LOWER(?) LIMIT 1");
        if(!$cek) throw new Exception("Prepare cek gagal: ".$conn->error);

        $cek->bind_param("s", $nama);
        $cek->execute();
        $cek->store_result();

        if($cek->num_rows > 0){
            $cek->close();
            throw new Exception("GAGAL! Produk \"$nama\" sudah ada! Pakai EDIT atau Tambah Stok.");
        }
        $cek->close();

        // INSERT
        $stmt = $conn->prepare("INSERT INTO produk (nama, kategori, merk, stok, modal, harga_jual) VALUES (?, ?, ?, ?, ?, ?)");
        if(!$stmt) throw new Exception("Prepare insert gagal: ".$conn->error." - Cek kolom tabel produk!");

        $stmt->bind_param("sssiii", $nama, $kategori, $merk, $stok, $modal, $harga_jual);

        if($stmt->execute()){
            $stmt->close();
            echo "<script>alert('SUKSES! Produk baru berhasil ditambah!'); window.location='stok.php';</script>";
            exit;
        } else {
            throw new Exception("Execute gagal: ".$stmt->error);
        }

    } catch (Exception $e) {
        $pesan_error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8"><title>Tambah Produk V4.9.3 Fix 500</title>
<link href="bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
<style>body{background:#f4f4f4;padding:20px}.box{background:white;padding:25px;border-radius:12px;max-width:500px;margin:auto}</style>
</head>
<body>
<div class="box">
<h5 class="fw-bold">Tambah Produk Baru - V4.9.3 Fix 500</h5>

<?php if($pesan_error): ?>
<div class="alert alert-danger"><b>⚠️ GAGAL:</b><br><?= htmlspecialchars($pesan_error) ?></div>
<script>alert("GAGAL: <?= addslashes($pesan_error) ?>");</script>
<?php endif; ?>

<form method="POST">
<label>Nama Produk</label><input type="text" name="nama" class="form-control" placeholder="Nama Produk Yang Dijual">
<label>Kategori</label>
<select name="kategori" class="form-control" required>
<option value="">-- Pilih --</option>
<option value="PULSA">PULSA</option>
<option value="PERDANA">PERDANA</option>
<option value="VOUCHER">VOUCHER</option>
<option value="ACCESORRIES">ACCESORRIES</option>
</select>
<label>Merk</label><input type="text" name="merk" class="form-control" required >
<label>Stok</label><input type="number" name="stok" class="form-control"  placeholder="Minimal 10" required>
<label>Modal</label><input type="number" name="modal" class="form-control" placeholder="Sesuai dengan modal saat beli" required>
<label>Harga Jual</label><input type="number" name="harga_jual" class="form-control" placeholder="Sesuai dengan harga juaal yang telah dihitung" required>
<button type="submit" name="simpan" class="btn btn-success w-100 mt-3 fw-bold">SIMPAN PRODUK BARU - V4.9.3 Anti 500</button>
</form>
<a href="stok.php" class="btn btn-secondary w-100 mt-2">Batal</a>
</div>
</body>
</html>
