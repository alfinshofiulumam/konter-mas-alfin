<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';
require_once 'auth.php';
require_once 'functions_transaksi.php'; // INI KUNCINYA MAS
requireLogin();

$isAdmin = strtolower($_SESSION['role'] ?? '') == 'admin';
$roleText = $isAdmin ? 'ADMIN' : 'PRAMUNIAGA';

[$tgl_awal, $tgl_akhir] = getFilterTanggal();
$sumAll = getRekapTotal($conn, $tgl_awal, $tgl_akhir);
$lag_total = getLabaKemarin($conn, $tgl_awal);
$tren_total = (int)$sumAll['laba'] - $lag_total;
$kombinasi_total = $lag_total + $tren_total;
$det = getRincianKategori($conn, $tgl_awal, $tgl_akhir);

// === SALDO & RINGKASAN - biarin aja punya Mas Alfin yang lama, sudah bagus ===
$saldoPulsa = ['total' => 0];
$stmt = $conn->prepare("SELECT SUM(stok) as total FROM produk WHERE UPPER(kategori) = ?");
$katPulsa = 'PULSA';
$stmt->bind_param("s", $katPulsa);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
if ($res) $saldoPulsa = $res;
$stmt->close();
// ... lanjut punya Mas Alfin yang lama sampai bawah ...

$totalProduk = ['total' => 0];
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM produk");
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
if ($res) $totalProduk = $res;
$stmt->close();

$hariIni = ['trx' => 0, 'laba' => 0];
$stmt = $conn->prepare("SELECT COUNT(*) as trx, SUM(laba) as laba FROM transaksi WHERE DATE(tanggal) = CURDATE()");
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
if ($res) $hariIni = $res;
$stmt->close();

// === QUERY LAG & LEAD V9 ===
$stmt = $conn->prepare("
    SELECT t.*, p.nama, p.kategori, p.merk,
           LAG(t.laba) OVER (ORDER BY t.tanggal ASC) as laba_prev,
           LEAD(t.tanggal) OVER (ORDER BY t.tanggal ASC) as tgl_next
    FROM transaksi t
    JOIN produk p ON t.produk_id = p.id
    WHERE DATE(t.tanggal) BETWEEN ? AND ?
    ORDER BY t.tanggal DESC
");
$stmt->bind_param("ss", $tgl_awal, $tgl_akhir);
$stmt->execute();
$q = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Data Transaksi V5.4 - Laba Modal Admin Only</title>
    <link href="bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#f5f6fa; font-family:'Segoe UI',sans-serif; }
        .card-stat { border:none; border-radius:12px; padding:16px 18px; color:white; box-shadow:0 4px 12px rgba(0,0,0,0.08); height:100%; }
        .card-stat small { font-size:12px; opacity:0.9; }
        .card-stat h4 { font-weight:800; margin:4px 0; }
        .card-ringkasan { border:none; border-radius:12px; background:white; box-shadow:0 2px 10px rgba(0,0,0,0.06); height:100%; padding:16px; }
        .tbl-laporan th { background:#212529; color:white; padding:11px; font-size:13px; }
        .tbl-laporan td { padding:10px; font-size:13px; background:white; border-bottom:1px solid #eee; }
        .badge-kat { padding:4px 10px; border-radius:4px; font-weight:700; font-size:11px; }
        .bg-pulsa { background:#22c55e; color:white; }
        .bg-perdana { background:#3b82f6; color:white; }
        .bg-acces { background:#facc15; color:#000; }
        .bg-voucher { background:#a855f7; color:white; }
        .tren-naik { color:#16a34a; font-size:11px; font-weight:700; }
        .tren-turun { color:#dc2626; font-size:11px; font-weight:700; }
        .tren-sama { color:#6b7280; font-size:11px; }
    </style>
</head>
<body>
<div class="container-fluid px-4 mt-3" style="max-width:1400px;">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-0">📋 Data Transaksi V5.4 - Konter Alfin Kepanjen</h4>
            <small class="text-muted">Login: <?= htmlspecialchars($_SESSION['username'] ?? '') ?> | <?= $roleText ?> | <?= $isAdmin ? 'Mode: Laba & Modal Terlihat' : 'Mode: Laba & Modal Disembunyikan' ?></small>
        </div>
        <div>
            <a href="index.php" class="btn btn-secondary btn-sm" style="border-radius:8px;">⬅️ Dashboard</a>
            <a href="laporan.php" class="btn btn-dark btn-sm" style="border-radius:8px;">📑 Laporan</a>
        </div>
    </div>

    <!-- 4 CARD UTAMA - V5.4 LABA MODAL ADMIN ONLY -->
    <div class="row g-3 mb-3">
        <!-- CARD 1: TOTAL TRANSAKSI -->
        <div class="col-md-3">
            <div class="card-stat" style="background:#0d6efd;">
                <small>Total Transaksi (<?= htmlspecialchars($tgl_awal) ?> s/d <?= htmlspecialchars($tgl_akhir) ?>)</small>
                <h4><?= $sumAll['total'] ?? 0 ?>x</h4>
                <?php if (($sumAll['total'] ?? 0) > 0): ?>
                    <small style="opacity:0.9;"><?= $det['TOTAL_QTY'] ?> Pcs terjual</small>
                    <hr style="margin:6px 0; opacity:0.3">
                    <div style="font-size:11px; line-height:1.4; opacity:0.95;">
                        📱 Pulsa: <?= $det['PULSA_TRX'] ?> trx / <?= $det['PULSA'] ?> pcs<br>
                        💳 Voucher: <?= $det['VOUCHER'] ?> pcs<br>
                        🎧 Acc: <?= $det['ACC'] ?> pcs<br>
                        📶 Perdana: <?= $det['PERDANA'] ?> pcs
                    </div>
                <?php else: ?>
                    <div style="font-size:11px; background:rgba(255,255,255,0.2); border-radius:8px; padding:8px; text-align:center;">😴 Tidak ada transaksi</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- CARD 2: OMZET - SEMUA BOLEH LIAT -->
        <div class="col-md-3">
            <div class="card bg-warning shadow h-60 p-3" style="border:none;border-radius:12px;">
                <small>Total Omzet</small>
                <h4 style="color:#000;">Rp <?= number_format($sumAll['omzet'] ?? 0, 0, ',', '.') ?></h4>
                <small>Sesuai filter tanggal</small>
            </div>
        </div>

        <?php if($isAdmin): ?>
        <!-- CARD 3: LABA BERSIH - CUMA ADMIN -->
        <div class="col-md-3">
            <div class="card-stat" style="background:#198754;">
                <small>Total Laba Bersih</small>
                <h4>Rp <?= number_format($sumAll['laba'] ?? 0, 0, ',', '.') ?></h4>
                <span class="badge rounded-pill mb-2" style="background:rgba(255,255,255,0.25); font-size:11px;">
                    <?= ($tren_total >= 0 ? '↑ +' : '↓ ') . 'Rp ' . number_format($tren_total) ?> vs kemarin
                </span>
                <div style="font-size:11px; line-height:1.4; background:rgba(0,0,0,0.18); border-radius:8px; padding:6px 8px;">
                    <div class="d-flex justify-content-between"><span style="opacity:0.8;">📉 LAG:</span> <b>Rp <?= number_format($lag_total) ?></b></div>
                    <div class="d-flex justify-content-between"><span style="opacity:0.8;">📈 TREN:</span> <b><?= ($tren_total >= 0 ? '+' : '') . 'Rp ' . number_format($tren_total) ?></b></div>
                    <div class="d-flex justify-content-between" style="border-top:1px dashed rgba(255,255,255,0.3); margin-top:4px; padding-top:4px;"><span>🔗 KOMBINASI:</span> <b>Rp <?= number_format($kombinasi_total) ?></b></div>
                </div>
            </div>
        </div>
        <!-- CARD 4: MODAL - CUMA ADMIN -->
        <div class="col-md-3">
            <div class="card bg-danger text-white shadow h-60 p-3" style="border:none;border-radius:12px;">
                <small>Total Modal (HPP)</small>
                <h4>Rp <?= number_format($sumAll['modal'] ?? 0, 0, ',', '.') ?></h4>
                <small>Sesuai filter tanggal</small><br><small>Total modal / HPP</small>
            </div>
        </div>
        <?php else: ?>
        <!-- CARD 3 & 4 PRAMUNIAGA - DIKUNCI -->
        <div class="col-md-3">
            <div class="card shadow h-60 p-3" style="border:none;border-radius:12px; background:#e9ecef; border:2px dashed #adb5bd;">
                <small class="text-muted">🔒 Total Laba Bersih</small>
                <h4 class="text-muted">Rp ••••</h4>
                <span class="badge bg-secondary" style="font-size:10px;">Hanya ADMIN</span>
                <div class="mt-2" style="font-size:11px; background:rgba(0,0,0,0.05); border-radius:8px; padding:6px 8px;"><small class="text-muted">Laba disembunyikan</small></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow h-60 p-3" style="border:none;border-radius:12px; background:#e9ecef; border:2px dashed #adb5bd;">
                <small class="text-muted">🔒 Total Modal (HPP)</small>
                <h4 class="text-muted">Rp ••••</h4>
                <small class="text-muted">Hanya ADMIN</small><br><small class="text-muted">Hubungi admin untuk info HPP</small>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- SALDO & RINGKASAN -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card-stat" style="background:#6f42c1;">
                <small>💰 Saldo Pulsa</small>
                <h4>Rp <?= number_format($saldoPulsa['total'] ?? 0, 0, ',', '.') ?></h4>
                <small>Total sisa saldo Pulsa (tidak ikut filter)</small>
            </div>
        </div>
        <div class="col-md-9">
            <div class="card-ringkasan">
                <div class="fw-bold mb-2">📊 Ringkasan Hari Ini</div>
                <div class="row text-center">
                    <div class="col-4 border-end"><small class="text-muted">Total Produk</small><div class="fw-bold"><?= $totalProduk['total'] ?? 0 ?> item</div></div>
                    <div class="col-4 border-end"><small class="text-muted">Transaksi</small><div class="fw-bold"><?= $hariIni['trx'] ?? 0 ?>x hari ini</div></div>
                    <div class="col-4">
                        <small class="text-muted">Laba Bersih</small>
                        <?php if($isAdmin): ?>
                        <div class="fw-bold text-success">Rp <?= number_format($hariIni['laba'] ?? 0, 0, ',', '.') ?></div>
                        <?php else: ?>
                        <div class="fw-bold text-muted">🔒 Rp ••••</div><small style="font-size:10px;">Hanya ADMIN</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- FILTER -->
    <div class="card border-0 shadow-sm p-3 mb-3" style="border-radius:12px;">
        <form method="GET" class="row g-2 align-items-end justify-content-center">
            <div class="col-md-4">
                <label class="form-label fw-bold" style="font-size:12px;">Tanggal Awal</label>
                <input type="date" name="tgl_awal" value="<?= htmlspecialchars($tgl_awal) ?>" class="form-control form-control-sm">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold" style="font-size:12px;">Tanggal Akhir</label>
                <input type="date" name="tgl_akhir" value="<?= htmlspecialchars($tgl_akhir) ?>" class="form-control form-control-sm">
            </div>
            <div class="col-md-2"><button class="btn btn-dark btn-sm w-100" style="border-radius:8px;">🔍 Tampilkan</button>
            </div>
        </form>
    </div>

    <!-- TABEL LAG & LEAD - V5.4 LABA MODAL ADMIN ONLY -->
    <div class="card border-0 shadow-sm p-3" style="border-radius:12px;">
        <div class="fw-bold mb-2">Detail Transaksi Lengkap - <?= $isAdmin ? 'LAG & LEAD Aktif + Laba Modal Terlihat' : 'Laba & Modal Disembunyikan (Pramuniaga)' ?></div>
        <div class="table-responsive">
            <table class="table table-bordered mb-0 tbl-laporan">
                <thead>
                    <tr>
                        <th>No</th><th>Waktu</th><th>Produk</th><th>Kategori</th><th>Merk</th><th>Qty</th>
                        <?php if($isAdmin): ?><th>Modal</th><?php endif; ?>
                        <th>Jual</th>
                        <?php if($isAdmin): ?><th>Laba + Tren (LAG)</th><?php else: ?><th>Laba 🔒</th><?php endif; ?>
                        <th>Next (LEAD)</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($q && $q->num_rows > 0) {
                    $no = 1;
                    while ($d = $q->fetch_assoc()) {
                        $kat = strtoupper($d['kategori']);
                        $badge = 'bg-acces';
                        if ($kat == 'PULSA') $badge = 'bg-pulsa';
                        else if ($kat == 'PERDANA') $badge = 'bg-perdana';
                        else if (strpos($kat, 'VOUC') !== false) $badge = 'bg-voucher';

                        $labaPrev = $d['laba_prev'];
                        $selisih = ($labaPrev !== null) ? $d['laba'] - $labaPrev : 0;

                        if ($labaPrev === null) $tren = '<span class="tren-sama">• Awal</span>';
                        else if ($selisih > 0) $tren = '<span class="tren-naik">↑ +Rp ' . number_format($selisih) . '</span>';
                        else if ($selisih < 0) $tren = '<span class="tren-turun">↓ Rp ' . number_format($selisih) . '</span>';
                        else $tren = '<span class="tren-sama">→ Sama</span>';

                        $next = $d['tgl_next'] ? date('H:i', strtotime($d['tgl_next'])) : '- Selesai -';
                ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td style="font-weight:700; white-space:nowrap;"><?= $d['tanggal'] ?></td>
                        <td><?= htmlspecialchars($d['nama']) ?></td>
                        <td><span class="badge-kat <?= $badge ?>"><?= $d['kategori'] ?></span></td>
                        <td style="font-weight:700;"><?= $d['merk'] ?></td>
                        <td><?= $d['qty'] ?></td>
                        <?php if($isAdmin): ?><td>Rp <?= number_format($d['modal_total']) ?></td><?php endif; ?>
                        <td>Rp <?= number_format($d['jual_total']) ?></td>
                        <?php if($isAdmin): ?>
                        <td style="font-weight:700; color:#15803d;">Rp <?= number_format($d['laba']) ?><br><?= $tren ?></td>
                        <?php else: ?>
                        <td class="text-muted">🔒 ••••</td>
                        <?php endif; ?>
                        <td style="font-size:11px; color:#6b7280;"><?= $next ?></td>
                    </tr>
                <?php } } else { ?>
                    <tr>
                        <td colspan="<?= $isAdmin ? '10' : '8' ?>" class="text-center py-4 text-muted">
                            😴 Tidak ada transaksi pada tanggal <?= htmlspecialchars($tgl_awal) ?> s/d <?= htmlspecialchars($tgl_akhir) ?>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>