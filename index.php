<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';
require_once 'auth.php';
requireRole(['admin', 'pramuniaga']);

$isAdmin = strtolower($_SESSION['role'] ?? '') == 'admin';
$username = $_SESSION['username'] ?? 'User';
$usernameEsc = htmlspecialchars($username);
$initial = strtoupper(substr($username, 0, 1));
$roleText = $isAdmin ? 'admin' : 'pramuniaga';

$tgl_awal = $_GET['tgl_awal'] ?? date('Y-m-d');
$tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-d');
$filterAwalInput = $tgl_awal;
$filterAkhirInput = $tgl_akhir;
$isSwapped = false;
if ($tgl_awal > $tgl_akhir) { $tmp = $tgl_awal; $tgl_awal = $tgl_akhir; $tgl_akhir = $tmp; $isSwapped = true; }
$isFiltered = isset($_GET['tgl_awal']) && isset($_GET['tgl_akhir']);

$totalTransaksi = $qtyFisik = $qtyPulsa = $totalOmzet = $totalLaba = $totalModal = $saldoPulsa = $totalProduk = 0;
$qtyPerdana = $qtyVoucher = $qtyAksesoris = 0;
$alertList = [];
$trenLabaText = '';

try {
  $stmt = $conn->prepare("SELECT COUNT(*) as total FROM produk");
  $stmt->execute(); $res = $stmt->get_result()->fetch_assoc(); $totalProduk = $res['total'] ?? 0; $stmt->close();

  $stmt = $conn->prepare("SELECT SUM(stok) as total FROM produk WHERE UPPER(kategori)=?");
  $katPulsa = 'PULSA'; $stmt->bind_param("s", $katPulsa); $stmt->execute();
  $res = $stmt->get_result()->fetch_assoc(); $saldoPulsa = $res['total'] ?? 0; $stmt->close();

  if ($isFiltered) {
    $stmt = $conn->prepare("SELECT COUNT(*) as jml_transaksi, SUM(modal_total) as total_modal, SUM(jual_total) as total_omzet, SUM(laba) as total_laba FROM transaksi WHERE DATE(tanggal) BETWEEN ? AND ?");
    $stmt->bind_param("ss", $tgl_awal, $tgl_akhir);
    $stmt->execute(); $row = $stmt->get_result()->fetch_assoc();
    $totalTransaksi = $row['jml_transaksi'] ?? 0; $totalModal = $row['total_modal'] ?? 0; $totalOmzet = $row['total_omzet'] ?? 0; $totalLaba = $row['total_laba'] ?? 0; $stmt->close();

    $stmt = $conn->prepare("SELECT UPPER(p.kategori) as kat, SUM(t.qty) as total_qty FROM transaksi t JOIN produk p ON t.produk_id = p.id WHERE DATE(t.tanggal) BETWEEN ? AND ? GROUP BY UPPER(p.kategori)");
    $stmt->bind_param("ss", $tgl_awal, $tgl_akhir); $stmt->execute(); $res = $stmt->get_result();
    while($r = $res->fetch_assoc()){ $kat=$r['kat']; $qty=$r['total_qty']; if($kat=='PULSA') $qtyPulsa+=$qty; else if($kat=='PERDANA') $qtyPerdana+=$qty; else if(strpos($kat,'VOUC')!==false) $qtyVoucher+=$qty; else $qtyAksesoris+=$qty; }
    $qtyFisik = $qtyPerdana + $qtyVoucher + $qtyAksesoris; $stmt->close();
  } else {
    $stmt = $conn->prepare("SELECT UPPER(p.kategori) as kat, SUM(t.qty) as total_qty FROM transaksi t JOIN produk p ON t.produk_id = p.id WHERE DATE(t.tanggal)=CURDATE() GROUP BY UPPER(p.kategori)");
    $stmt->execute(); $res = $stmt->get_result();
    while($r = $res->fetch_assoc()){ $kat=$r['kat']; $qty=$r['total_qty']; if($kat=='PULSA') $qtyPulsa+=$qty; else if($kat=='PERDANA') $qtyPerdana+=$qty; else if(strpos($kat,'VOUC')!==false) $qtyVoucher+=$qty; else $qtyAksesoris+=$qty; }
    $qtyFisik = $qtyPerdana + $qtyVoucher + $qtyAksesoris; $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) as jml_transaksi, SUM(modal_total) as total_modal, SUM(jual_total) as total_omzet, SUM(laba) as total_laba FROM transaksi WHERE DATE(tanggal)=CURDATE()");
    $stmt->execute(); $row = $stmt->get_result()->fetch_assoc();
    $totalTransaksi = $row['jml_transaksi'] ?? 0; $totalModal = $row['total_modal'] ?? 0; $totalOmzet = $row['total_omzet'] ?? 0; $totalLaba = $row['total_laba'] ?? 0; $stmt->close();
  }

  $stmt = $conn->prepare("SELECT SUM(laba) as laba FROM transaksi WHERE DATE(tanggal)=CURDATE() - INTERVAL 1 DAY");
  $stmt->execute(); $kemarin = $stmt->get_result()->fetch_assoc()['laba'] ?? 0; $stmt->close();
  if ($totalLaba > 0 && $kemarin !== null) {
    $sel = $totalLaba - $kemarin;
    if ($sel > 0) $trenLabaText = "<span style='font-size:11px; color:#fff; background:rgba(255,255,255,0.25); padding:2px 6px; border-radius:10px;'>↑ +Rp ".number_format($sel)." vs kemarin</span>";
    else if ($sel < 0) $trenLabaText = "<span style='font-size:11px; color:#fff; background:rgba(0,0,0,0.2); padding:2px 6px; border-radius:10px;'>↓ Rp ".number_format($sel)." vs kemarin</span>";
    else $trenLabaText = "<span style='font-size:11px;'>→ Sama kayak kemarin</span>";
  }

  $alertList = [];
  try {
    // SATU QUERY SAKTI - TANGKEP SEMUA STOK KRITIS
    $qAlert = "
    SELECT * FROM produk 
    WHERE 
    (UPPER(TRIM(kategori)) = 'PULSA' AND stok <= 100000)
    OR 
    (UPPER(TRIM(kategori)) LIKE '%PULSA%' AND UPPER(TRIM(kategori)) != 'PULSA' AND stok <= 100000)
    OR
    (UPPER(TRIM(kategori)) IN ('PERDANA','VOUCHER','AKSESORIS','AKSESORIES','ACC','ACCESSORIES','AKS','VOUC') AND stok <= 5)
    OR
    (UPPER(TRIM(kategori)) LIKE '%PERDANA%' AND stok <= 5)
    OR
    (UPPER(TRIM(kategori)) LIKE '%VOUC%' AND stok <= 5)
    OR
    (UPPER(TRIM(kategori)) LIKE '%AKSES%' AND stok <= 5)
    OR
    (UPPER(TRIM(kategori)) LIKE '%ACC%' AND stok <= 5)
    OR
    (UPPER(TRIM(kategori)) LIKE '%KABEL%' AND stok <= 5)
    ORDER BY 
    CASE WHEN stok = 0 THEN 0 ELSE 1 END ASC, 
    stok ASC
    LIMIT 20
    ";
    $stmt = $conn->prepare($qAlert);
    $stmt->execute();
    $res = $stmt->get_result();
    while($r = $res->fetch_assoc()){
      $alertList[] = $r;
    }
    $stmt->close();
  } catch(Exception $e){
    // debug kalau perlu: $alertList = []; error_log($e->getMessage());
  }

} catch(Exception $e){}
$roleBadge = $isAdmin ? "<span class='badge' style='background:#198754; padding:6px 12px; border-radius:20px;'>👑 ADMIN</span>" : "<span class='badge' style='background:#6c757d; padding:6px 12px; border-radius:20px;'>👀 PRAMUNIAGA</span>";
$judulPeriode = $isFiltered ? "$tgl_awal s/d $tgl_akhir" : "Hari ini (".date('Y-m-d').")";
?>
<!DOCTYPE html>
<html>
<head>
  <title>Dashboard V5.4 - Laba Modal Admin Only</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body{background:#f5f6fa; font-family:'Segoe UI',sans-serif;}
    .card-stat{border:none; border-radius:15px; box-shadow:0 4px 15px rgba(0,0,0,0.08);}
    .card-biru{background:#0d6efd; color:white; border-radius:12px;}
    .card-kuning{background:#ffc107; color:#000; border-radius:12px;}
    .card-hijau{background:#198754; color:white; border-radius:12px;}
    .card-merah{background:#dc3545; color:white; border-radius:12px;}
    .card-ungu{background:#6f42c1; color:white; border-radius:12px;}
    .menu-card{border-radius:12px; border:1px solid #eee; padding:15px 20px; background:white; text-decoration:none; color:#333; display:flex; gap:12px; margin-bottom:8px;}
    .menu-card:hover{background:#f8f9ff; border-color:#0d6efd; color:#0d6efd;}
  </style>
</head>
<body>
  <nav class="navbar bg-white px-4 py-2 d-flex justify-content-between shadow-sm">
    <div><b>📱 Konter Alfin</b> <small class="text-muted">Kepanjen - V5.4</small></div>
    <div class="dropdown">
      <button class="btn btn-light dropdown-toggle d-flex align-items-center gap-2 border-0 shadow-sm" data-bs-toggle="dropdown" style="border-radius:12px; padding:6px 12px;">
        <span class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width:36px;height:36px;font-weight:bold;"><?= $initial ?></span>
        <div class="text-start" style="line-height:1.1;"><b style="font-size:14px;"><?= $usernameEsc ?></b>
          <br>
          <small class="text-muted" style="font-size:11px;"><?= $roleText ?></small>
        </div>
      </button>
      <ul class="dropdown-menu dropdown-menu-end shadow" style="border-radius:12px; border:none; min-width:220px; padding:8px;">
        <li class="px-3 py-2">
          <div class="d-flex align-items-center gap-2">
            <span class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width:40px;height:40px;"><?= $initial ?></span>
            <div>
              <b style="font-size:14px;"><?= $usernameEsc ?></b><br><?= $roleBadge ?>
            </div>
          </div>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="ganti_password.php">🔑 Ganti Password</a></li>
        <?php if($isAdmin): ?><li><a class="dropdown-item" href="users.php">👥 Kelola User</a></li><?php endif; ?>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" href="logout.php">🚪 Logout</a></li>
      </ul>
    </div>
  </nav>

  <div class="container-fluid mt-3">
    <div class="card border-0 shadow-sm p-3 mb-3" style="border-radius:12px;">
      <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label fw-bold" style="font-size:11px;">Filter Dashboard - Tgl Awal</label><input type="date" name="tgl_awal" value="<?= htmlspecialchars($filterAwalInput) ?>" class="form-control form-control-sm">
        </div>
        <div class="col-md-3"><label class="form-label fw-bold" style="font-size:11px;">Tgl Akhir</label><input type="date" name="tgl_akhir" value="<?= htmlspecialchars($filterAkhirInput) ?>" class="form-control form-control-sm">
        </div>
        <div class="col-md-2"><button class="btn btn-dark btn-sm w-100">🔍 Tampilkan</button>
        </div>
        <div class="col-md-4 text-end">
          <small class="text-muted">Periode: <b><?= $judulPeriode ?></b></small>
          <br>
          <?php if($isSwapped): ?><span class="badge bg-warning text-dark" style="font-size:10px;">⚠️ Tgl otomatis diurutkan</span><?php endif; ?>
          <?php if($isFiltered): ?><a href="index.php" class="badge bg-secondary text-decoration-none">Reset ke Hari Ini</a><?php endif; ?>
        </div>
      </form>
    </div>

    <div class="row g-3">
      <div class="col-md-3">
        <div class="card card-stat p-2">
          <a href="stok.php" class="menu-card">📦 Kelola Stok</a>
          <a href="transaksi.php" class="menu-card">💰 Input Transaksi</a>
          <a href="laporan.php?tgl_awal=<?= $tgl_awal ?>&tgl_akhir=<?= $tgl_akhir ?>" class="menu-card">📊 Laporan</a>
          <?php if($isAdmin): ?><a href="users.php" class="menu-card">👥 Kelola User</a><?php endif; ?>
          <a href="data_transaksi.php?tgl_awal=<?= $tgl_awal ?>&tgl_akhir=<?= $tgl_akhir ?>" class="menu-card">📋 Data Transaksi</a>
        </div>
      </div>
      <div class="col-md-9">
        <!-- SATU BARIS, 4 CARD, GAK DOUBLE -->
        <div class="row g-3 mb-4">
          <div class="col-md-3 col-6">
            <div class="card card-biru p-3 h-100">
              <small>Total Transaksi</small>
              <h3 class="fw-bold"><?= $totalTransaksi ?>x</h3>
              <small style="font-size:10px;"><?= $judulPeriode ?></small>
              <div class="mt-1"><span class="badge bg-white text-primary" style="font-size:11px;">📦 <?= $qtyFisik ?> Pcs</span> 
                <span class="badge bg-light text-dark" style="font-size:11px;">💳 <?= $qtyPulsa ?></span>
              </div>
            </div>
          </div>
          <div class="col-md-3 col-6">
            <div class="card card-kuning p-3 h-100">
              <small>Total Omzet</small>
              <h5 class="fw-bold">Rp <?= number_format($totalOmzet) ?></h5>
              <small style="font-size:11px;"><?= $judulPeriode ?></small>
            </div>
          </div>
          
          <?php if($isAdmin): ?>
            <div class="col-md-3 col-6"><div class="card card-hijau p-3 h-100"><small>Total Laba Bersih</small><h5 class="fw-bold">Rp <?= number_format($totalLaba) ?></h5><div><?= $trenLabaText ?></div><small style="font-size:11px;">Omzet - Modal</small></div></div>
            <div class="col-md-3 col-6"><div class="card card-merah p-3 h-100"><small>Total Modal (HPP)</small><h5 class="fw-bold">Rp <?= number_format($totalModal) ?></h5><small style="font-size:11px;"><?= $judulPeriode ?></small></div></div>
          <?php else: ?>
            <div class="col-md-3 col-6">
              <div class="card p-3 h-100" style="background:#e9ecef; border-radius:12px; border:2px dashed #adb5bd;">
                <small class="text-muted">🔒 Laba Bersih</small>
                <h5 class="text-muted fw-bold">Rp ••••</h5>
                <small class="text-muted">Hanya ADMIN</small>
              </div>
            </div>
            <div class="col-md-3 col-6"><div class="card p-3 h-100" style="background:#e9ecef; border-radius:12px; border:2px dashed #adb5bd;">
              <small class="text-muted">🔒 Modal (HPP)</small>
              <h5 class="text-muted fw-bold">Rp ••••</h5>
              <small class="text-muted">Hanya ADMIN</small>
            </div>
          </div>
          <?php endif; ?>
        </div>

        <div class="col-md-8">
          <?php if(count($alertList) > 0): ?>
            <div class="card card-stat">
              <div class="card-header bg-danger text-white py-2"><small class="fw-bold">🚨 Alert Stok Menipis (<?= count($alertList) ?> item)</small>
              </div>
              <div class="table-responsive">
                <table class="table table-sm mb-0" style="font-size:13px;">
                  <thead>
                    <tr>
                      <th>Nama</th>
                      <th>Kategori</th>
                      <th>Stok</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody><?php foreach($alertList as $a): $isPulsa = strtoupper($a['kategori'])=='PULSA'; ?>
                  <tr>
                    <td><?= htmlspecialchars($a['nama']) ?> <small class="text-muted"><?= htmlspecialchars($a['merk'] ?? '') ?></small></td>
                    <td><span class="badge bg-secondary"><?= htmlspecialchars($a['kategori']) ?></span></td>
                    <td><b><?= $isPulsa ? 'Rp '.number_format($a['stok']) : $a['stok'].' Pcs' ?></b></td>
                    <td><span class="badge bg-danger">⚠️ Menipis</span></td></tr><?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          <?php else: ?>
            <div class="card p-3 h-100 border-0 shadow-sm" style="background:linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border-radius:12px; border-left:5px solid #2e7d32 !important;">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <div class="fw-bold text-success" style="font-size:16px;">✅ Semua stok aman terkendali!</div>
                  <small class="text-muted">Tidak ada produk yang menipis di bawah batas minimal</small>
                  <hr style="margin:8px 0; opacity:0.2;">
                  <div class="d-flex gap-3 flex-wrap" style="font-size:12px;">
                    <span class="badge bg-white text-success shadow-sm" style="padding:6px 10px;">📦 Total Produk: <?= $totalProduk ?> item</span>
                    <span class="badge bg-white text-primary shadow-sm" style="padding:6px 10px;">💳 Saldo Pulsa: Rp <?= number_format($saldoPulsa) ?></span>
                    <span class="badge bg-white text-dark shadow-sm" style="padding:6px 10px;">📶 Fisik: <?= $qtyFisik + $qtyPerdana + $qtyVoucher + $qtyAksesoris ?> Pcs Aman</span>
                  </div>
                  <div class="mt-2" style="font-size:11px; line-height:1.4; color:#2e7d32;">
                    • Batas alert pulsa: < Rp 100.000<br>
                    • Batas alert fisik (perdana/voucher/acc): ≤ 5 Pcs<br>
                    • Periode cek: <?= $judulPeriode ?> - Update otomatis setiap transaksi
                  </div>
                </div>
                <div style="font-size:40px; opacity:0.2;">🛡️</div>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>