<?php
require_once 'config.php';
require_once 'auth.php';
requireRole(['admin', 'pramuniaga']);

// FIX 1: $csrf HARUS SETELAH requireRole, baru session jalan!
$csrf = buatCsrfToken();

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    cekCsrfToken(); // BARIS PERTAMA! WAJIB!

    // FIX 2: HAPUS BLOK GENERIC DELETE YANG ATAS, BIARIN YANG BAWAH YANG JALAN
    // Jadi bagian ini Pak Yoga kosongin, biar gak tabrakan

    // KALAU ADA YANG AKSES LEWAT GET / LINK WA, TOLAK! (pindah ke dalam POST check biar aman)
}

// KALAU ADA YANG AKSES LEWAT GET / LINK WA, TOLAK!
if(isset($_GET['id'])){
    die("<h1>❌ Gak boleh hapus lewat link! Harus lewat tombol Hapus di website!</h1>");
}

$isAdmin = strtolower($_SESSION['role'] ?? '') == 'admin';
$roleBadge = $isAdmin
    ? "<span class='badge' style='background:#198754; padding:6px 12px; border-radius:20px;'>👑 ADMIN</span>"
    : "<span class='badge' style='background:#000000; padding:6px 12px; border-radius:20px;'>👀 PRAMUNIAGA - View Only</span>";
$username = $_SESSION['username'] ?? '';

// === KUNCI SERVER: TAMBAH & HAPUS CUMA ADMIN ===
if(!$isAdmin && (isset($_POST['tambah']) || isset($_POST['hapus_produk']))){
    die("<script>alert('🚫 AKSES DITOLAK! Hanya ADMIN yang boleh tambah stok & hapus produk!'); window.location='stok.php';</script>");
}

$saldo_tipis_batas = 100000;

if(isset($_POST['tambah'])){
  // cekCsrfToken udah di atas, gak perlu lagi
  $id = (int)$_POST['id'];
  $tambah = (int)$_POST['tambah_stok'];
  if($tambah != 0){
    $stmt = $conn->prepare("UPDATE produk SET stok = stok + ? WHERE id=?");
    $stmt->bind_param("ii", $tambah, $id);
    $stmt->execute();
    $stmt->close();
  }
  header("Location: stok.php");
  exit;
}

if(isset($_POST['hapus_produk'])){
  // cekCsrfToken udah di atas
  $id = (int)$_POST['id_hapus'];
  if($id > 0){
    $cek = $conn->prepare("SELECT COUNT(*) as total FROM transaksi WHERE produk_id=?");
    $cek->bind_param("i", $id);
    $cek->execute();
    $hasil_cek = $cek->get_result()->fetch_assoc();
    $cek->close();
    if($hasil_cek['total'] > 0){
      echo "<script>alert('GAGAL HAPUS! Produk sudah terjual ".$hasil_cek['total']."x. Tidak boleh dihapus!'); window.location='stok.php';</script>";
      exit;
    } else {
      $stmt = $conn->prepare("DELETE FROM produk WHERE id=?");
      $stmt->bind_param("i", $id);
      $stmt->execute();
      $stmt->close();
      echo "<script>alert('HAPUS PRODUK ID $id BERHASIL!');window.location='stok.php';</script>";
      exit;
    }
  }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Kelola Stok V5.3 - Modal Admin Only</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background: #f5f6fa; }
.card-custom { border: none; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
.table thead th { white-space: nowrap; vertical-align: middle; text-align: center; font-size: 14px; padding: 12px 8px !important; }
.table tbody td { vertical-align: middle; font-size: 14px; padding: 10px 8px !important; }
.btn-edit-sultan { background: linear-gradient(135deg, #0dcaf0 0%, #0d6efd 100%); border: none; color: #fff !important; border-radius: 8px; padding: 6px 12px; font-weight: 700; font-size: 13px; }
.btn-hapus-sultan { background: linear-gradient(135deg, #dc3545 0%, #a71d2a 100%); border: none; color: #fff !important; border-radius: 8px; padding: 6px 12px; font-weight: 700; font-size: 13px; }
.btn-tambah-stok-sultan { background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%); border: none; color: #000 !important; border-radius: 8px; font-weight: 800; }
</style>
</head>
<body>
<div class="container mt-4">
  <div class="card card-custom p-4 mb-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div>
        <h4 class="mb-1">Kelola Stok - V5.3 Modal Admin Only</h4>
        <small class="text-muted"><?php echo $isAdmin ? 'Mode: Full Access - Bisa liat Modal' : 'Mode: View Only - Modal disembunyikan'; ?></small>
      </div>
      <div class="text-end">
        <small class="text-muted">Login sebagai:</small><br>
        <b><?= htmlspecialchars($username) ?></b> <?= $roleBadge ?>
      </div>
    </div>
    <div class="d-flex gap-2 flex-wrap mt-3">
      <?php if($isAdmin): ?>
          <a href="tambah_produk.php" class="btn" name="tambah" style="background: linear-gradient(90deg, #0d6efd, #6f42c1); color:white; border-radius:10px; padding:10px 20px; font-weight:bold;">✨ + Tambah Produk Baru</a>
      <?php endif; ?>
      <a href="index.php" class="btn btn-secondary" style="border-radius:12px; padding:10px 20px;">⬅️ Dashboard</a>
    </div>
  </div>
  <div class="card card-custom p-3">
    <div class="table-responsive">
      <table class="table table-hover table-bordered align-middle bg-white mb-0">
        <thead class="table-dark">
          <tr>
            <th>ID</th>
            <th>Nama</th>
            <th>Kategori</th>
            <th>Merk</th>
            <?php if($isAdmin): ?><th>Modal</th><?php endif; ?>
            <th>Jual</th>
            <th>Stok/Saldo</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
<?php
$stmt_tampil = $conn->prepare("SELECT * FROM produk ORDER BY id DESC");
$stmt_tampil->execute();
$res = $stmt_tampil->get_result();
while($r=$res->fetch_assoc()){
    $kategori_upper = strtoupper(trim($r['kategori']));

    // === WARNA KATEGORI ===
    if($kategori_upper == 'PULSA'){
        $warnaKategori = "background:#0d6efd; color:white;";
    } elseif($kategori_upper == 'PERDANA' || $kategori_upper == 'VOUCHER'){
        $warnaKategori = "background:#ffc107; color:black;";
    } elseif(in_array($kategori_upper, ['ACCESSORIES','ACC','AKSESORIS','ACCESSORY','ACCESSORRIES'])){
        $warnaKategori = "background:#198754; color:white;";
    } else {
        $warnaKategori = "background:#198754; color:white;";
    }

    if ($kategori_upper == 'PULSA') {
        $tampil_stok = $r['stok'] <= $saldo_tipis_batas ? "<div class='text-center'><span class='fw-bold text-danger'>Rp ".number_format($r['stok'])."</span><br><small class='badge bg-danger'>Saldo Menipis!</small></div>" : "<div class='text-center'><span class='fw-bold text-success'>Rp ".number_format($r['stok'])."</span></div>";
    } else {
        $tampil_stok = $r['stok'] <= 5 ? "<div class='text-center'><span class='fw-bold text-danger'>{$r['stok']} Pcs</span><br><small class='badge bg-danger'>Menipis!</small></div>" : "<div class='text-center'><span class='fw-bold'>{$r['stok']} Pcs</span></div>";
    }
    $nama_esc = htmlspecialchars($r['nama'], ENT_QUOTES);
    $nama_js = addslashes($r['nama']);

    if($isAdmin){
        // FIX 3: TAMBAHIN INPUT csrf_token DI FORM HAPUS!
        $kolomAksi = "<a href='edit_produk.php?id={$r['id']}' class='btn btn-sm btn-edit-sultan'>✏️ Edit</a>
              <form method='POST' style='display:inline;' onsubmit=\"return confirm('Yakin hapus \\\"$nama_js\\\"?')\">
                <input type='hidden' name='id_hapus' value='{$r['id']}'>
                <input type='hidden' name='csrf_token' value='{$csrf}'>
                <button type='submit' name='hapus_produk' class='btn btn-sm btn-hapus-sultan'>🗑️ Hapus</button>
              </form>";
    } else {
        $kolomAksi = "<button class='btn btn-sm btn-secondary' disabled>View Only</button>";
    }

    echo "<tr>
            <td class='text-center'>{$r['id']}</td>
            <td><b>$nama_esc</b></td>
            <td class='text-center'><span class='badge' style='$warnaKategori padding:7px 12px; border-radius:20px; font-weight:700;'>".htmlspecialchars($r['kategori'])."</span></td>
            <td class='text-center'><b>".htmlspecialchars($r['merk'])."</b></td>";
            if($isAdmin){
                echo "<td class='text-center'>Rp ".number_format($r['modal'])."</td>";
            }
    echo "    <td class='text-center'>Rp ".number_format($r['harga_jual'])."</td>
            <td>$tampil_stok</td>
            <td class='text-center' style='white-space:nowrap;'>$kolomAksi</td>
          </tr>";
}
$stmt_tampil->close();
?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>