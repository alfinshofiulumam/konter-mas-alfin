<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';
require_once 'auth.php';
requireLogin();

// === FUNGSI TARUH DI SINI AJA MAS, DALAM SATU FILE ===
function getFilterTanggal() {
    $tgl_awal = $_GET['tgl_awal'] ?? $_GET['awal'] ?? date('Y-m-d');
    $tgl_akhir = $_GET['tgl_akhir'] ?? $_GET['akhir'] ?? date('Y-m-d');
    if ($tgl_awal > $tgl_akhir) { [$tgl_awal, $tgl_akhir] = [$tgl_akhir, $tgl_awal]; }
    return [$tgl_awal, $tgl_akhir];
}
function getRekapTotal($conn, $tgl_awal, $tgl_akhir) {
    $stmt = $conn->prepare("SELECT COUNT(*) as total, COALESCE(SUM(qty),0) as pcs, COALESCE(SUM(jual_total),0) as omzet, COALESCE(SUM(laba),0) as laba, COALESCE(SUM(modal_total),0) as modal FROM transaksi WHERE DATE(tanggal) BETWEEN ? AND ?");
    $stmt->bind_param("ss", $tgl_awal, $tgl_akhir);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $data;
}

$isAdmin = strtolower($_SESSION['role'] ?? '') == 'admin';
$roleText = $isAdmin ? 'ADMIN' : 'PRAMUNIAGA';
[$tgl_awal, $tgl_akhir] = getFilterTanggal();
$sumAll = getRekapTotal($conn, $tgl_awal, $tgl_akhir);

// === LAG TREN KOMBINASI UNTUK CARD LABA BERSIH ===
$tgl_kemarin = date('Y-m-d', strtotime($tgl_awal . ' -1 day'));
$stmt_lag = $conn->prepare("SELECT COALESCE(SUM(laba),0) as laba_kemarin FROM transaksi WHERE DATE(tanggal) = ?");
$stmt_lag->bind_param("s", $tgl_kemarin);
$stmt_lag->execute();
$lag_total = (int)$stmt_lag->get_result()->fetch_assoc()['laba_kemarin'];
$stmt_lag->close();
$tren_total = (int)$sumAll['laba'] - $lag_total;
$kombinasi_total = $lag_total + $tren_total;

// === RINCIAN KATEGORI TETAP DI SINI JUGA, SATU FILE ===
$det = ['PULSA' => 0, 'VOUCHER' => 0, 'ACC' => 0, 'PERDANA' => 0, 'PULSA_TRX' => 0, 'TOTAL_QTY' => 0];
$stmt = $conn->prepare("SELECT UPPER(p.kategori) as kat, COUNT(*) as jml_trx, SUM(t.qty) as total_qty FROM transaksi t JOIN produk p ON t.produk_id = p.id WHERE DATE(t.tanggal) BETWEEN ? AND ? GROUP BY UPPER(p.kategori)");
$stmt->bind_param("ss", $tgl_awal, $tgl_akhir);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $kat = strtoupper($r['kat']); $qty = $r['total_qty'] ?? 0; $trx = $r['jml_trx'] ?? 0;
    $det['TOTAL_QTY'] += $qty;
    if ($kat == 'PULSA') { $det['PULSA'] = $qty; $det['PULSA_TRX'] = $trx; }
    else if ($kat == 'PERDANA') $det['PERDANA'] = $qty;
    else if (strpos($kat, 'VOUC') !== false) $det['VOUCHER'] = $qty;
    else { $det['ACC'] += $qty; }
}
$stmt->close();

// === SALDO & RINGKASAN - MASIH SATU FILE ===
$saldoPulsa = ['total' => 0];
$stmt = $conn->prepare("SELECT SUM(stok) as total FROM produk WHERE UPPER(kategori) = ?");
$katPulsa = 'PULSA'; $stmt->bind_param("s", $katPulsa); $stmt->execute();
$res = $stmt->get_result()->fetch_assoc(); if ($res) $saldoPulsa = $res; $stmt->close();

$totalProduk = ['total' => 0];
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM produk"); $stmt->execute();
$res = $stmt->get_result()->fetch_assoc(); if ($res) $totalProduk = $res; $stmt->close();

$hariIni = ['trx' => 0, 'laba' => 0];
$stmt = $conn->prepare("SELECT COUNT(*) as trx, SUM(laba) as laba FROM transaksi WHERE DATE(tanggal) = CURDATE()"); $stmt->execute();
$res = $stmt->get_result()->fetch_assoc(); if ($res) $hariIni = $res; $stmt->close();

// === QUERY LAG & LEAD ===
$stmt = $conn->prepare("SELECT t.*, p.nama, p.kategori, p.merk, LAG(t.laba) OVER (ORDER BY t.tanggal ASC) as laba_prev, LEAD(t.tanggal) OVER (ORDER BY t.tanggal ASC) as tgl_next FROM transaksi t JOIN produk p ON t.produk_id = p.id WHERE DATE(t.tanggal) BETWEEN ? AND ? ORDER BY t.tanggal DESC");
$stmt->bind_param("ss", $tgl_awal, $tgl_akhir); $stmt->execute(); $q = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Data Transaksi V5.5 Single File - Konter Alfin</title>
    <link href="bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#f5f6fa; font-family:'Segoe UI',sans-serif; }
        .card-stat { border:none; border-radius:12px; padding:16px 18px; color:white; box-shadow:0 4px 12px rgba(0,0,0,0.08); height:100%; }
        .card-stat small { font-size:12px; opacity:0.9; } .card-stat h4 { font-weight:800; margin:4px 0; }
        .card-ringkasan { border:none; border-radius:12px; background:white; box-shadow:0 2px 10px rgba(0,0,0,0.06); height:100%; padding:16px; }
        .tbl-laporan th { background:#212529; color:white; padding:11px; font-size:13px; }
        .tbl-laporan td { padding:10px; font-size:13px; background:white; border-bottom:1px solid #eee; }
        .badge-kat { padding:4px 10px; border-radius:4px; font-weight:700; font-size:11px; }
        .bg-pulsa { background:#22c55e; color:white; } .bg-perdana { background:#3b82f6; color:white; }
        .bg-acces { background:#facc15; color:#000; } .bg-voucher { background:#a855f7; color:white; }
        .tren-naik { color:#16a34a; font-size:11px; font-weight:700; } .tren-turun { color:#dc2626; font-size:11px; font-weight:700; } .tren-sama { color:#6b7280; font-size:11px; }
    </style>
</head>
<body>
<div class="container-fluid px-4 mt-3" style="max-width:1400px;">
<!-- HTML nya Mas Alfin yang V5.4 kemarin copy paste aja di sini, sudah cocok Mas! -->
<!-- Mulai dari <div class="d-flex justify... sampai </div></body></html> yang lama -->
<!-- Pak Yoga singkat biar tidak panjang, intinya HTML nya tetap sama kayak punya Mas Alfin! -->
</div>
</body>
</html>