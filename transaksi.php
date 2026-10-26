<?php
require_once 'config.php';
require_once 'auth.php'; // WAJIB ADA INI BIAR FUNCTION KE-BACA!
requireLogin();

$isAdmin = strtolower($_SESSION['role'] ?? '') == 'admin';
$roleText = $isAdmin ? 'ADMIN' : 'PRAMUNIAGA';

$pesan_sukses ="";
$pesan_gagal ="";

if(isset($_POST['jual'])){

    $produk_id = (int)$_POST['produk_id'];
    $qty = (int)$_POST['qty'];
    $tgl = date('Y-m-d H:i:s');
    $jam = $tgl;

    // === 1. CEK PRODUK - 100% AMAN ===
    $stmt = $conn->prepare("SELECT * FROM produk WHERE id=?");
    $stmt->bind_param("i", $produk_id);
    $stmt->execute();
    $p = $stmt->get_result()->fetch_assoc();

    if(!$p){
        $pesan_gagal = "<div class='alert alert-danger'>❌ Produk tidak ditemukan!</div>";
    } else {
        // === CEK STOK DULU SEBELUM JUAL - ANTI MINUS ===
        if(strtoupper($p['kategori']) == 'PULSA'){
            $butuh_saldo = $p['modal'] * $qty;
            if($p['stok'] < $butuh_saldo){
                $pesan_gagal = "<div class='alert alert-danger'>❌ SALDO TIDAK CUKUP! Sisa Saldo: Rp ".number_format($p['stok'])." | Butuh: Rp ".number_format($butuh_saldo)."</div>";
            }
        } else {
            if($p['stok'] < $qty){
                $pesan_gagal = "<div class='alert alert-danger'>❌ STOK FISIK TIDAK CUKUP! Sisa: {$p['stok']} Pcs | Mau jual: $qty Pcs</div>";
            }
        }

        // === 2. KALAU LOLOS CEK, BARU JUAL ===
        if(empty($pesan_gagal)){
            $modal_total = $p['modal'] * $qty;
            $jual_total = $p['harga_jual'] * $qty;
            $laba = $jual_total - $modal_total;

            $stmt2 = $conn->prepare("INSERT INTO transaksi (tanggal, produk_id, qty, modal_total, jual_total, laba) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt2->bind_param("siiiii", $tgl, $produk_id, $qty, $modal_total, $jual_total, $laba);
            $stmt2->execute();

            if(strtoupper($p['kategori']) == 'PULSA'){
                $stmt3 = $conn->prepare("UPDATE produk SET stok = stok - ? WHERE id=?");
                $stmt3->bind_param("ii", $modal_total, $produk_id);
            } else {
                $stmt3 = $conn->prepare("UPDATE produk SET stok = stok - ? WHERE id=?");
                $stmt3->bind_param("ii", $qty, $produk_id);
            }
            $stmt3->execute();

            $sisa_baru = $p['stok'] - (strtoupper($p['kategori'])=='PULSA' ? $modal_total : $qty);
            $label_sisa = strtoupper($p['kategori'])=='PULSA' ? "Sisa Saldo: Rp ".number_format($sisa_baru) : "Sisa Stok: $sisa_baru Pcs";

            // Pesan sukses - laba hanya detail untuk admin, pramuniaga cuma tau jual sukses
            if($isAdmin){
                $info_laba = "Jual: Rp ".number_format($jual_total)." | Laba: <b>Rp ".number_format($laba)."</b>";
            } else {
                $info_laba = "Jual: Rp ".number_format($jual_total)." | <span class='badge bg-secondary'>Laba disembunyikan</span>";
            }

            $pesan_sukses = "
            <div class='alert alert-success border-2 shadow'>
            <h5>✅ Transaksi BERHASIL!</h5>
            <b>{$p['nama']} ({$p['merk']}) x $qty</b><br>
            $info_laba<br>
            Jam: $jam<br>
            <span class='badge bg-dark mt-1'>$label_sisa</span>
            <hr class='my-2'>
            <a href='laporan.php' class='btn btn-sm btn-dark'>Lihat Laporan</a>
            <a href='index.php' class='btn btn-sm btn-outline-dark'>Dashboard</a>
            <a href='transaksi.php' class='btn btn-sm btn-success'>Jual Lagi</a>
            </div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Transaksi V5.3 - Modal Admin Only</title>
    <link href="bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    body { background: #f5f6fa; }
    .card-custom { border: none; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-4">
        <div class="card card-custom p-4">
            <h4>Input Transaksi V5.3 - Modal Admin Only</h4>
            <p class="text-muted">Login: <b><?= htmlspecialchars($_SESSION['username'] ?? '') ?></b> (<?= $roleText ?>) | <?= $isAdmin ? 'Mode: Bisa liat Modal & Laba' : 'Mode: Modal & Laba Disembunyikan' ?></p>

            <?php
            if(!empty($pesan_sukses)) echo $pesan_sukses;
            if(!empty($pesan_gagal)) echo $pesan_gagal;
            ?>

            <form method="POST" class="needs-validation" novalidate>
            <div class="mb-3">
                <label class="form-label">Pilih Produk</label>
                <select name="produk_id" class="form-control mb-2" required>
                    <option value="">-- Pilih Produk --</option>
                    <?php
                    $pr = $conn->prepare("SELECT * FROM produk ORDER BY nama ASC");
                    $pr->execute();
                    $res_pr = $pr->get_result();
                    while($r=$res_pr->fetch_assoc()){
                        if(strtoupper($r['kategori'])=='PULSA'){
                            $info_stok = "Saldo Rp ".number_format($r['stok']);
                        } else {
                            $info_stok = "Stok {$r['stok']} Pcs";
                        }

                        // === INI KUNCINYA: MODAL CUMA ADMIN ===
                        if($isAdmin){
                            $text_option = "{$r['nama']} | {$r['merk']} | {$r['kategori']} | Modal ".number_format($r['modal'])." -> Jual ".number_format($r['harga_jual'])." | $info_stok";
                        } else {
                            $text_option = "{$r['nama']} | {$r['merk']} | {$r['kategori']} | Jual ".number_format($r['harga_jual'])." | $info_stok";
                        }

                        echo "<option value='{$r['id']}'>".htmlspecialchars($text_option)."</option>";
                    }
                    ?>
                </select>
                <?php if(!$isAdmin): ?>
                <small class="text-muted" style="font-size:11px;">🔒 Mode Pramuniaga: Modal disembunyikan, hanya Harga Jual yang terlihat</small>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label class="form-label">Qty <span class="text-danger">*</span></label>
                <input type="number" name="qty" class="form-control" placeholder="Qty (misal 1)" required min="1" value="1">
                <div class="invalid-feedback">⚠️ Qty tidak boleh kosong!</div>
                <small class="text-muted">Untuk PULSA, qty 1 = 1x transaksi. Saldo akan berkurang otomatis sebesar modal.</small>
            </div>
            <div class="text-center mt-4">
              <button type="submit" name="jual" class="btn btn-success" style="border-radius:10px; font-weight:600; min-width:160px; padding:10px 24px;">
                ✅ Jual Sekarang
              </button>
            </div>
            </form>

            <hr class="my-4">
            <div class="d-flex justify-content-center gap-3 flex-wrap">
                <a href="index.php" class="btn btn-secondary" style="border-radius:10px; min-width:160px; padding:10px 20px;">⬅️ Dashboard</a>
                <a href="stok.php" class="btn btn-warning" style="border-radius:10px; min-width:160px; padding:10px 20px;">📦 Kelola Stok</a>
            </div>
        </div>
    </div>
    <script src="bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>