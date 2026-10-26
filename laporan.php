<?php
require_once 'config.php';
require 'auth.php';
requireLogin();

$isAdmin = strtolower($_SESSION['role'] ?? '') == 'admin';
$roleText = $isAdmin ? 'ADMIN' : 'PRAMUNIAGA';

// === V4.10 FIX - SYNC INDEX + LAPORAN + ANTI BUG FILTER ===
function validTanggal($tgl) {
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl) ? $tgl : date('Y-m-d');
}
$tgl_awal = validTanggal($_GET['awal'] ?? date('Y-m-d'));
$tgl_akhir = validTanggal($_GET['akhir'] ?? date('Y-m-d'));

if ($tgl_awal > $tgl_akhir) {
    $tmp = $tgl_awal;
    $tgl_awal = $tgl_akhir;
    $tgl_akhir = $tmp;
}

$tgl_awal_html = htmlspecialchars($tgl_awal, ENT_QUOTES, 'UTF-8');
$tgl_akhir_html = htmlspecialchars($tgl_akhir, ENT_QUOTES, 'UTF-8');

// === REKAP TOTAL ===
$stmt = $conn->prepare("SELECT COUNT(*) as total_transaksi, COALESCE(SUM(qty),0) as total_qty, COALESCE(SUM(modal_total),0) as total_modal, COALESCE(SUM(jual_total),0) as total_omzet, COALESCE(SUM(laba),0) as total_laba FROM transaksi WHERE DATE(tanggal) BETWEEN ? AND ?");
$stmt->bind_param("ss", $tgl_awal, $tgl_akhir);
$stmt->execute();
$rekap = $stmt->get_result()->fetch_assoc();

// === LAG TREN KOMBINASI ===
$tgl_kemarin = date('Y-m-d', strtotime($tgl_awal . ' -1 day'));
$stmt_lag = $conn->prepare("SELECT COALESCE(SUM(laba),0) as laba_kemarin FROM transaksi WHERE DATE(tanggal) = ?");
$stmt_lag->bind_param("s", $tgl_kemarin);
$stmt_lag->execute();
$lag_laba = (int)$stmt_lag->get_result()->fetch_assoc()['laba_kemarin'];
$tren_laba = (int)$rekap['total_laba'] - $lag_laba;
$kombinasi_laba = $lag_laba + $tren_laba;

$stmt_rinci = $conn->prepare("SELECT p.kategori, COUNT(*) as jml_trx, COALESCE(SUM(t.qty),0) as qty FROM transaksi t JOIN produk p ON t.produk_id=p.id WHERE DATE(t.tanggal) BETWEEN ? AND ? GROUP BY p.kategori");
$stmt_rinci->bind_param("ss", $tgl_awal, $tgl_akhir);
$stmt_rinci->execute();
$res_rinci = $stmt_rinci->get_result();
$det = ['PULSA'=>0,'VOUCHER'=>0,'ACC'=>0,'PERDANA'=>0, 'PULSA_TRX'=>0];
while($d=$res_rinci->fetch_assoc()){
    $k = strtoupper($d['kategori']);
    if(strpos($k,'PULSA')!==false){ $det['PULSA']=$d['qty']; $det['PULSA_TRX']=$d['jml_trx']; }
    else if(strpos($k,'VOUCH')!==false){ $det['VOUCHER']=$d['qty']; }
    else if(strpos($k,'ACC')!==false || strpos($k,'AKSES')!==false){ $det['ACC']=$d['qty']; }
    else if(strpos($k,'PERDANA')!==false){ $det['PERDANA']=$d['qty']; }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan V5.4 - Opsi A Unduh Admin Only</title>
    <link href="bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    .card-custom{border:none;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.08);}
    body { background: #f5f6fa; }
    .btn-sultan{border:none;border-radius:12px;padding:11px 22px;font-weight:700;font-size:14px;box-shadow:0 4px 12px rgba(0,0,0,0.15);transition:all 0.25s ease;}
    .btn-sultan:hover{transform:translateY(-3px);box-shadow:0 8px 20px rgba(0,0,0,0.25);}
    .btn-preview-sultan{background:linear-gradient(135deg,#ffc107 0%,#ff9800 100%);color:#000 !important;padding:12px 28px;font-size:16px;width:100%;}
    .btn-excel-sultan{background:linear-gradient(135deg,#198754 0%,#0f5132 100%);color:#fff !important;}
    .btn-word-sultan{background:linear-gradient(135deg,#0d6efd 0%,#084298 100%);color:#fff !important;}
    .btn-pdf-sultan{background:linear-gradient(135deg,#dc3545 0%,#a71d2a 100%);color:#fff !important;}
    .btn-filter-sultan{background:linear-gradient(135deg,#212529 0%,#000 100%);color:#fff !important;border-radius:10px;font-weight:600;}
    .btn-locked{border:2px dashed #adb5bd !important; background:#e9ecef !important; color:#6c757d !important; cursor:not-allowed !important; box-shadow:none !important;}
    </style>
</head>
<body class="bg-light">
<div class="container mt-4">
    <h3>Laporan Konter Alfin - V5.4 Opsi A Unduh Admin Only</h3>
    <p class="text-muted">Login: <?= htmlspecialchars($_SESSION['username'] ?? '') ?> | <?= $roleText ?> | Filter: <?= $tgl_awal_html ?> s/d <?= $tgl_akhir_html ?> | <?= $isAdmin ? 'Bisa Unduh' : 'Unduh Dikunci' ?></p>

    <div class="card card-custom p-3 mb-4">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-bold">Tanggal Awal</label>
                <input type="date" name="awal" value="<?= $tgl_awal_html ?>" class="form-control" required style="border-radius:10px;">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Tanggal Akhir</label><input type="date" name="akhir" value="<?= $tgl_akhir_html ?>" class="form-control" required style="border-radius:10px;">
            </div>
            <div class="col-md-6">
                <div class="d-flex gap-2 flex-wrap">
                    <button class="btn btn-sultan btn-filter-sultan">🔍 Tampilkan Laporan</button>
                    <a href="laporan.php?awal=<?= date('Y-m-d') ?>&akhir=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary" style="border-radius:10px;font-weight:600;">Hari Ini</a>
                    <a href="laporan.php?awal=<?= date('Y-m-01') ?>&akhir=<?= date('Y-m-d') ?>" class="btn btn-outline-primary" style="border-radius:10px;font-weight:600;">Bulan Ini</a>
                    <a href="index.php?awal=<?= $tgl_awal ?>&akhir=<?= $tgl_akhir ?>" class="btn btn-secondary ms-auto" style="border-radius:10px;font-weight:600;">⬅️ Dashboard</a>
                </div>
            </div>
        </form>
    </div>

    <div class="row mb-4 g-3">
        <div class="col-md-3">
            <div class="card bg-primary text-white shadow h-60 p-3" style="border:none;border-radius:12px;">
                <small style="opacity:0.9;">Total Transaksi</small>
                <h4 class="fw-bold text-white"><?= $rekap['total_transaksi'] ?>x</h4>
                <small style="opacity:0.9;"><?= $rekap['total_qty'] ?> Pcs terjual</small>
                <hr style="margin:6px 0; opacity:0.3">
                <div style="font-size:11px; line-height:1.4; opacity:0.95;">
                    📱 Pulsa: <?= $det['PULSA_TRX'] ?> trx / <?= $det['PULSA'] ?> pcs<br>
                    💳 Voucher: <?= $det['VOUCHER'] ?> pcs<br>
                    🎧 Acc: <?= $det['ACC'] ?> pcs<br>
                    📶 Perdana: <?= $det['PERDANA'] ?> pcs
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning shadow h-60 p-3" style="border:none;border-radius:12px;">
                <small class="text-muted">Total Omzet Penjualan</small>
                <h4 class="fw-bold">Rp <?= number_format($rekap['total_omzet']) ?></h4>
                <small style="font-size:10px;">Periode <?= $tgl_awal_html ?> s/d <?= $tgl_akhir_html ?></small>
            </div>
        </div>

        <?php if($isAdmin): ?>
        <div class="col-md-3">
            <div class="card bg-success text-white shadow h-60 p-3" style="border:none;border-radius:12px;">
                <small style="opacity:0.9;">Total Laba Bersih</small>
                <h4 class="fw-bold text-white">Rp <?= number_format($rekap['total_laba']) ?></h4>
                <span class="badge rounded-pill mb-2" style="background: rgba(255,255,255,0.25); font-size:11px;">
                    <?= ($tren_laba >= 0 ? '↑ +' : '↓ ') ?>Rp <?= number_format($tren_laba) ?> vs kemarin
                </span>
                <div style="font-size:11px; line-height:1.4; background: rgba(0,0,0,0.15); border-radius:8px; padding:6px 8px;">
                    <div class="d-flex justify-content-between"><span style="opacity:0.8;">📉 LAG:</span> <b>Rp <?= number_format($lag_laba) ?></b>
                    </div>
                    <div class="d-flex justify-content-between"><span style="opacity:0.8;">📈 TREN:</span> <b><?= ($tren_laba>=0?'+':'') ?>Rp <?= number_format($tren_laba) ?></b>
                    </div>
                    <div class="d-flex justify-content-between" style="border-top:1px dashed rgba(255,255,255,0.3); margin-top:4px; padding-top:4px;"><span>🔗 KOMBINASI:</span> <b>Rp <?= number_format($kombinasi_laba) ?></b>
                    </div>
                </div>
                <small style="font-size:9px; opacity:0.8;">Omzet - Modal (HPP)</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white shadow h-60 p-3" style="border:none;border-radius:12px;">
                <small style="opacity:0.9;">Total Modal Keluar (HPP)</small>
                <h4 class="fw-bold text-white">Rp <?= number_format($rekap['total_modal']) ?></h4>
                <small style="font-size:10px; opacity:0.9;">Modal periode <?= $tgl_awal_html ?> s/d <?= $tgl_akhir_html ?></small>
            </div>
        </div>
        <?php else: ?>
        <div class="col-md-3">
            <div class="card shadow h-60 p-3" style="border:none;border-radius:12px; background:#e9ecef; border:2px dashed #adb5bd;">
                <small class="text-muted">🔒 Total Laba Bersih</small>
                <h4 class="fw-bold text-muted">Rp ••••</h4>
                <span class="badge bg-secondary" style="font-size:10px;">Hanya ADMIN</span>
                <div class="mt-2" style="font-size:11px; background:rgba(0,0,0,0.05); border-radius:8px; padding:6px 8px;"><small class="text-muted">Laba disembunyikan untuk pramuniaga</small></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow h-60 p-3" style="border:none;border-radius:12px; background:#e9ecef; border:2px dashed #adb5bd;">
                <small class="text-muted">🔒 Total Modal (HPP)</small>
                <h4 class="fw-bold text-muted">Rp ••••</h4>
                <span class="badge bg-secondary" style="font-size:10px;">Hanya ADMIN</span>
                <div class="mt-2" style="font-size:11px;"><small class="text-muted">Modal disembunyikan</small></div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3"><div class="card card-custom p-3"><h5>Rekap Per Merk (Paling Laku)</h5><table class="table table-sm table-bordered mb-0"><tr class="table-dark"><th>Merk</th><th>Qty</th><th>Omzet</th><?php if($isAdmin): ?><th>Laba</th><?php else: ?><th>Laba 🔒</th><?php endif; ?></tr><?php $qmerk=$conn->prepare("SELECT p.merk,SUM(t.qty) as qty,SUM(t.jual_total) as omzet,SUM(t.laba) as laba FROM transaksi t JOIN produk p ON t.produk_id=p.id WHERE DATE(t.tanggal) BETWEEN ? AND ? GROUP BY p.merk ORDER BY laba DESC");$qmerk->bind_param("ss",$tgl_awal,$tgl_akhir);$qmerk->execute();$rmerk=$qmerk->get_result();while($m=$rmerk->fetch_assoc()){echo "<tr><td><b>".htmlspecialchars($m['merk'])."</b></td><td>{$m['qty']}</td><td>Rp ".number_format($m['omzet'])."</td>"; if($isAdmin){ echo "<td class='text-success fw-bold'>Rp ".number_format($m['laba'])."</td>"; } else { echo "<td class='text-muted'>🔒 ••••</td>"; } echo "</tr>";} ?></table></div></div>
        <div class="col-md-6 mb-3"><div class="card card-custom p-3"><h5>Rekap Per Kategori</h5><table class="table table-sm table-bordered mb-0"><tr class="table-dark"><th>Kategori</th><th>Qty</th><th>Omzet</th><?php if($isAdmin): ?><th>Laba</th><?php else: ?><th>Laba 🔒</th><?php endif; ?></tr><?php $qkat=$conn->prepare("SELECT p.kategori,SUM(t.qty) as qty,SUM(t.jual_total) as omzet,SUM(t.laba) as laba FROM transaksi t JOIN produk p ON t.produk_id=p.id WHERE DATE(t.tanggal) BETWEEN ? AND ? GROUP BY p.kategori ORDER BY omzet DESC");$qkat->bind_param("ss",$tgl_awal,$tgl_akhir);$qkat->execute();$rkat=$qkat->get_result();while($k=$rkat->fetch_assoc()){echo "<tr><td><span class='badge bg-secondary'>".htmlspecialchars($k['kategori'])."</span></td><td>{$k['qty']}</td><td>Rp ".number_format($k['omzet'])."</td>"; if($isAdmin){ echo "<td class='text-success fw-bold'>Rp ".number_format($k['laba'])."</td>"; } else { echo "<td class='text-muted'>🔒 ••••</td>"; } echo "</tr>";} ?></table></div></div>
    </div>

    <div class="card card-custom p-3"><h5>Detail Transaksi Lengkap (<?= $tgl_awal_html ?> s/d <?= $tgl_akhir_html ?>)</h5><div class="table-responsive"><table class="table table-bordered table-hover align-middle bg-white"><thead class="table-dark"><tr><th>No</th><th>Waktu Lengkap</th><th>Produk</th><th>Kategori</th><th>Merk</th><th>Qty</th><?php if($isAdmin): ?><th>Modal Total</th><?php endif; ?><th>Jual Total</th><?php if($isAdmin): ?><th>Laba</th><?php else: ?><th>Laba 🔒</th><?php endif; ?></tr></thead><tbody><?php $stmt2=$conn->prepare("SELECT t.*,p.nama,p.kategori,p.merk FROM transaksi t JOIN produk p ON t.produk_id=p.id WHERE DATE(t.tanggal) BETWEEN ? AND ? ORDER BY t.tanggal DESC");$stmt2->bind_param("ss",$tgl_awal,$tgl_akhir);$stmt2->execute();$res=$stmt2->get_result();$no=1;if($res->num_rows==0){echo "<tr><td colspan='".($isAdmin?9:7)."' class='text-center p-4 text-muted'>Tidak ada transaksi di periode ini</td></tr>";}while($row=$res->fetch_assoc()){$waktu_lengkap=date('Y-m-d H:i:s',strtotime($row['tanggal']));echo "<tr><td>$no</td><td><b>$waktu_lengkap</b></td><td>".htmlspecialchars($row['nama'])."</td><td><span class='badge bg-secondary'>".htmlspecialchars($row['kategori'])."</span></td><td><b>".htmlspecialchars($row['merk'])."</b></td><td>{$row['qty']}</td>"; if($isAdmin){ echo "<td>Rp ".number_format($row['modal_total'])."</td>"; } echo "<td>Rp ".number_format($row['jual_total'])."</td>"; if($isAdmin){ echo "<td class='text-success fw-bold'>Rp ".number_format($row['laba'])."</td>"; } else { echo "<td class='text-muted'>🔒</td>"; } echo "</tr>";$no++;} ?></tbody></table></div></div>

    <!-- === OPSI A - TOMBOL UNDUH ADMIN ONLY === -->
    <div class="text-center mt-4 mb-5">
        <div class="card card-custom p-4">
            <?php if($isAdmin): ?>
                <a href="preview_laporan.php?awal=<?= $tgl_awal ?>&akhir=<?= $tgl_akhir ?>" class="btn btn-sultan btn-preview-sultan mb-3">👁️ PRATINJAU DULU 3 FILE (Excel Word PDF)</a>
                <hr>
                <div class="d-flex justify-content-center gap-3 flex-wrap">
                    <a href="export_excel_xlsx.php?awal=<?= $tgl_awal ?>&akhir=<?= $tgl_akhir ?>" class="btn btn-sultan btn-excel-sultan">📊 Download Excel</a>
                    <a href="export_word.php?awal=<?= $tgl_awal ?>&akhir=<?= $tgl_akhir ?>" class="btn btn-sultan btn-word-sultan">📝 Download Word</a>
                    <a href="export_pdf.php?awal=<?= $tgl_awal ?>&akhir=<?= $tgl_akhir ?>" class="btn btn-sultan btn-pdf-sultan">📄 Download PDF</a>
                </div>
            <?php else: ?>
                <div class="p-3 mb-3" style="background:#fff3cd; border:2px dashed #ffc107; border-radius:12px;">
                    <h5 class="text-warning">🔒 Fitur Unduh Dikunci</h5>
                    <p class="text-muted mb-0" style="font-size:13px;">Tombol unduh laporan hanya untuk ADMIN.<br>Login kamu: <b><?= htmlspecialchars($_SESSION['username']) ?> (PRAMUNIAGA)</b> - Hubungi admin untuk unduh laporan.</p>
                </div>
                <a href="#" class="btn btn-sultan btn-locked mb-3 w-100" style="pointer-events:none;">👁️ 🔒 PRATINJAU (Hanya ADMIN)</a>
                <hr>
                <div class="d-flex justify-content-center gap-3 flex-wrap">
                    <button disabled class="btn btn-sultan btn-locked">📊 🔒 Download Excel</button>
                    <button disabled class="btn btn-sultan btn-locked">📝 🔒 Download Word</button>
                    <button disabled class="btn btn-sultan btn-locked">📄 🔒 Download PDF</button>
                </div>
            <?php endif; ?>
            <hr class="mt-4">
            <a href="index.php?awal=<?= $tgl_awal ?>&akhir=<?= $tgl_akhir ?>" class="btn btn-secondary" style="border-radius:10px;min-width:100%;padding:12px 20px;font-weight:600;">⬅️ Kembali Dashboard</a>
        </div>
    </div>
</div>
<script src="bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>