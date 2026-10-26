<?php
require_once 'config.php';
require 'auth.php';
requireLogin();

// === V5.4 OPSI A - KUNCI ADMIN ONLY ===
$isAdmin = strtolower($_SESSION['role'] ?? '') == 'admin';
$roleText = $isAdmin ? 'ADMIN' : 'PRAMUNIAGA';

function badgeKategoriPreview($kategori){
    $kat = strtolower(trim($kategori));
    if($kat == 'pulsa') return '<span class="badge bg-success">PULSA</span>';
    if($kat == 'perdana') return '<span class="badge bg-primary">PERDANA</span>';
    if(strpos($kat,'acces') !== false || strpos($kat,'aksesor') !== false) return '<span class="badge bg-warning text-dark">ACCESORRIES</span>';
    if($kat == 'kuota' || $kat == 'data') return '<span class="badge bg-info text-dark">KUOTA</span>';
    return '<span class="badge bg-secondary">'.htmlspecialchars(strtoupper($kategori), ENT_QUOTES, 'UTF-8').'</span>';
}

// === FIX V4.9 FINAL - ANTI SQL INJECTION + ANTI XSS ===
function validTanggal($tgl) {
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl) ? $tgl : date('Y-m-d');
}
$tgl_awal = validTanggal($_GET['awal'] ?? date('Y-m-d'));
$tgl_akhir = validTanggal($_GET['akhir'] ?? date('Y-m-d'));

// Untuk tampil HTML aman
$tgl_awal_html = htmlspecialchars($tgl_awal, ENT_QUOTES, 'UTF-8');
$tgl_akhir_html = htmlspecialchars($tgl_akhir, ENT_QUOTES, 'UTF-8');

$stmt = $conn->prepare("SELECT COUNT(*) as trx, SUM(qty) as qty, SUM(modal_total) as modal, SUM(jual_total) as omzet, SUM(laba) as laba FROM transaksi WHERE DATE(tanggal) BETWEEN ? AND ?");
$stmt->bind_param("ss", $tgl_awal, $tgl_akhir);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();

$stmt2 = $conn->prepare("SELECT t.*, p.nama, p.kategori, p.merk FROM transaksi t JOIN produk p ON t.produk_id=p.id WHERE DATE(t.tanggal) BETWEEN ? AND ? ORDER BY t.tanggal DESC");
$stmt2->bind_param("ss", $tgl_awal, $tgl_akhir);
$stmt2->execute();
$res = $stmt2->get_result();
?>
<!DOCTYPE html>
<html><head>
<meta charset="UTF-8">
<title>Preview Laporan V5.4 - <?= $roleText ?></title>
<link href="bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
<style>
@media print { .no-print { display:none; } }
.preview-box { border:2px dashed #000; padding:15px; background:#fff; }
th { background:#000 !important; color:#fff !important; }
.laba { background:#C6EFCE !important; color:#006100; font-weight:bold; }
.laba-locked { background:#e9ecef !important; color:#6c757d !important; font-weight:bold; }
.btn-locked{border:2px dashed #adb5bd !important; background:#e9ecef !important; color:#6c757d !important; cursor:not-allowed !important;}
</style>
</head>
<body class="bg-light">
<div class="container mt-3">
<div class="no-print d-flex justify-content-between align-items-center mb-3">
<a href="laporan.php?awal=<?= $tgl_awal_html ?>&akhir=<?= $tgl_akhir_html ?>" class="btn btn-secondary">← Kembali Laporan</a>
<h4>👁️ PRATINJAU V5.4 - Login: <?= htmlspecialchars($_SESSION['username'] ?? '') ?> | <?= $roleText ?> | <?= $isAdmin ? 'Laba Modal Terlihat' : 'Laba Modal Dikunci' ?></h4>
</div>

<?php if(!$isAdmin): ?>
<div class="alert alert-warning no-print" style="border:2px dashed #ffc107;">
    <h5>🔒 Mode PRAMUNIAGA - Preview Terbatas</h5>
    <p class="mb-0">Kamu login sebagai <b>PRAMUNIAGA</b>, jadi kolom <b>Modal & Laba</b> disembunyikan.<br>Tombol unduh juga dikunci. Hubungi ADMIN untuk akses penuh.</p>
</div>
<?php endif; ?>

<ul class="nav nav-tabs no-print" role="tablist">
<li class="nav-item">
    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#excel">📊 Preview Excel .XLSX
    </button>
</li>
<li class="nav-item">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#word">📝 Preview Word .DOCX</button>
</li>
<li class="nav-item">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#pdf">📄 Preview PDF</button>
</li>
</ul>

<div class="tab-content preview-box">
<!-- TAB 1 EXCEL -->
<div id="excel" class="tab-pane fade show active">
<div class="alert <?= $isAdmin ? 'alert-success' : 'alert-warning' ?>">
    <?= $isAdmin ? '📊 Ini tampilan kalau di-download jadi <b>Excel .XLSX Modern</b> - Kolom auto-width, warna hitam header, laba hijau!' : '📊 🔒 Preview Excel untuk PRAMUNIAGA - Modal & Laba disembunyikan!' ?>
</div>
<table class="table table-bordered table-sm">
    <tr><th colspan="5">RINGKASAN - Periode <?= $tgl_awal_html ?> s/d <?= $tgl_akhir_html ?></th></tr>
    <tr><th>Total Transaksi</th><th>Total Qty</th><?php if($isAdmin): ?><th>Modal</th><?php else: ?><th>Modal 🔒</th><?php endif; ?><th>Omzet</th><?php if($isAdmin): ?><th>Laba Bersih</th><?php else: ?><th>Laba 🔒</th><?php endif; ?></tr>
    <tr>
        <td><?= htmlspecialchars($r['trx']) ?>x</td>
        <td><?= htmlspecialchars($r['qty']) ?> Pcs</td>
        <?php if($isAdmin): ?>
            <td>Rp <?= number_format($r['modal']) ?></td>
        <?php else: ?>
            <td class="laba-locked">🔒 Rp ••••</td>
        <?php endif; ?>
        <td>Rp <?= number_format($r['omzet']) ?></td>
        <?php if($isAdmin): ?>
            <td class="laba">Rp <?= number_format($r['laba']) ?></td>
        <?php else: ?>
            <td class="laba-locked">🔒 Rp ••••</td>
        <?php endif; ?>
    </tr>
</table>

<table class="table table-bordered table-striped table-sm">
<tr><th>No</th><th>Waktu Lengkap Y-m-d H:i:s</th><th>Produk</th><th>Kategori</th><th>Merk</th><th>Qty</th><?php if($isAdmin): ?><th>Modal</th><?php else: ?><th>Modal 🔒</th><?php endif; ?><th>Jual</th><?php if($isAdmin): ?><th>Laba</th><?php else: ?><th>Laba 🔒</th><?php endif; ?></tr>
<?php $no=1; $res->data_seek(0); while($d=$res->fetch_assoc()){ $waktu=date('Y-m-d H:i:s', strtotime($d['tanggal'])); ?>
<tr>
<td><?= $no ?></td>
<td><b><?= htmlspecialchars($waktu) ?></b></td>
<td><?= htmlspecialchars($d['nama']) ?></td>
<td><?= badgeKategoriPreview($d['kategori']) ?></td>
<td><b><?= htmlspecialchars($d['merk']) ?></b></td>
<td><?= htmlspecialchars($d['qty']) ?></td>
<?php if($isAdmin): ?>
<td>Rp <?= number_format($d['modal_total']) ?></td>
<?php else: ?>
<td class="laba-locked">🔒</td>
<?php endif; ?>
<td>Rp <?= number_format($d['jual_total']) ?></td>
<?php if($isAdmin): ?>
<td class="laba">Rp <?= number_format($d['laba']) ?></td>
<?php else: ?>
<td class="laba-locked">🔒 ••••</td>
<?php endif; ?>
</tr>
<?php $no++; } ?>
</table>
<?php if($isAdmin): ?>
<a href="export_excel_xlsx.php?awal=<?= $tgl_awal_html ?>&akhir=<?= $tgl_akhir_html ?>" class="btn btn-success no-print">📥 Download Excel .XLSX Asli</a>
<?php else: ?>
<button disabled class="btn btn-locked no-print">📊 🔒 Download Excel (Hanya ADMIN)</button>
<?php endif; ?>
</div>

<!-- TAB 2 WORD -->
<div id="word" class="tab-pane fade">
<div class="alert <?= $isAdmin ? 'alert-primary' : 'alert-warning' ?>"><?= $isAdmin ? '📝 Ini tampilan kalau di-download jadi <b>Word .DOCX Modern</b>' : '📝 🔒 Preview Word untuk PRAMUNIAGA - Laba disembunyikan!' ?></div>
<table class="table table-bordered table-sm"><tr><th>No</th><th>Waktu Lengkap</th><th>Produk</th><th>Kategori</th><?php if($isAdmin): ?><th>Laba</th><?php else: ?><th>Laba 🔒</th><?php endif; ?></tr>
<?php $no=1; $res->data_seek(0); while($d=$res->fetch_assoc()){ ?>
<tr>
<td><?= $no ?></td>
<td><?= htmlspecialchars(date('Y-m-d H:i:s', strtotime($d['tanggal']))) ?></td>
<td><?= htmlspecialchars($d['nama']) ?> (<?= htmlspecialchars($d['merk']) ?>)</td>
<td><?= badgeKategoriPreview($d['kategori']) ?></td>
<?php if($isAdmin): ?>
<td class="laba">Rp <?= number_format($d['laba']) ?></td>
<?php else: ?>
<td class="laba-locked">🔒 ••••</td>
<?php endif; ?>
</tr>
<?php $no++; if($no>10) break; } ?>
</table>
<?php if($isAdmin): ?>
<a href="export_word.php?awal=<?= $tgl_awal_html ?>&akhir=<?= $tgl_akhir_html ?>" class="btn btn-primary no-print">📥 Download Word</a>
<?php else: ?>
<button disabled class="btn btn-locked no-print">📝 🔒 Download Word (Hanya ADMIN)</button>
<?php endif; ?>
</div>

<!-- TAB 3 PDF -->
<div id="pdf" class="tab-pane fade">
<div class="alert <?= $isAdmin ? 'alert-danger' : 'alert-warning' ?>"><?= $isAdmin ? '📄 Ini tampilan kalau di-download jadi <b>PDF Modern</b> - Landscape A4!' : '📄 🔒 Preview PDF untuk PRAMUNIAGA - Laba Modal disembunyikan!' ?></div>
<?php if($isAdmin): ?>
<div class="mb-2 no-print">
<a href="export_pdf.php?awal=<?= $tgl_awal_html ?>&akhir=<?= $tgl_akhir_html ?>&preview=1" target="_blank" class="btn btn-dark">👁️ Buka PDF di Tab Baru</a>
</div>
<object data="export_pdf.php?awal=<?= $tgl_awal_html ?>&akhir=<?= $tgl_akhir_html ?>&preview=1" type="application/pdf" style="width:100%; height:700px; border:2px solid #000;"></object>
<div class="mt-3 no-print">
<a href="export_pdf.php?awal=<?= $tgl_awal_html ?>&akhir=<?= $tgl_akhir_html ?>" class="btn btn-danger">📥 Download PDF</a>
</div>
<?php else: ?>
<div class="p-4 text-center" style="background:#e9ecef; border:2px dashed #adb5bd; border-radius:12px;">
    <h5>🔒 Preview PDF Dikunci untuk Pramuniaga</h5>
    <p class="text-muted">File PDF asli berisi Laba & Modal, hanya ADMIN yang bisa buka.<br>Login kamu PRAMUNIAGA.</p>
    <button disabled class="btn btn-locked">📄 🔒 Buka PDF (Hanya ADMIN)</button>
</div>
<?php endif; ?>
</div>
</div>
</div>

<script src="bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>