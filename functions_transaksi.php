<?php
// functions_transaksi.php - V5.5 Clean Code by Pak Yoga untuk Mas Alfin

function getFilterTanggal() {
    $tgl_awal = $_GET['tgl_awal'] ?? $_GET['awal'] ?? date('Y-m-d');
    $tgl_akhir = $_GET['tgl_akhir'] ?? $_GET['akhir'] ?? date('Y-m-d');
    if ($tgl_awal > $tgl_akhir) {
        [$tgl_awal, $tgl_akhir] = [$tgl_akhir, $tgl_awal]; // swap canggih Mas
    }
    return [$tgl_awal, $tgl_akhir];
}

function getRekapTotal($conn, $tgl_awal, $tgl_akhir) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total, COALESCE(SUM(qty),0) as pcs, 
               COALESCE(SUM(jual_total),0) as omzet,
               COALESCE(SUM(laba),0) as laba, 
               COALESCE(SUM(modal_total),0) as modal
        FROM transaksi WHERE DATE(tanggal) BETWEEN ? AND ?
    ");
    $stmt->bind_param("ss", $tgl_awal, $tgl_akhir);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $data;
}

function getLabaKemarin($conn, $tgl_awal) {
    $tgl_kemarin = date('Y-m-d', strtotime($tgl_awal . ' -1 day'));
    $stmt = $conn->prepare("SELECT COALESCE(SUM(laba),0) as laba_kemarin FROM transaksi WHERE DATE(tanggal) = ?");
    $stmt->bind_param("s", $tgl_kemarin);
    $stmt->execute();
    $laba = (int)$stmt->get_result()->fetch_assoc()['laba_kemarin'];
    $stmt->close();
    return $laba;
}

function getRincianKategori($conn, $tgl_awal, $tgl_akhir) {
    $det = ['PULSA' => 0, 'VOUCHER' => 0, 'ACC' => 0, 'PERDANA' => 0, 'PULSA_TRX' => 0, 'TOTAL_QTY' => 0];
    $stmt = $conn->prepare("
        SELECT UPPER(p.kategori) as kat, COUNT(*) as jml_trx, SUM(t.qty) as total_qty
        FROM transaksi t JOIN produk p ON t.produk_id = p.id
        WHERE DATE(t.tanggal) BETWEEN ? AND ? GROUP BY UPPER(p.kategori)
    ");
    $stmt->bind_param("ss", $tgl_awal, $tgl_akhir);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $kat = strtoupper($r['kat']); $qty = $r['total_qty'] ?? 0; $trx = $r['jml_trx'] ?? 0;
        $det['TOTAL_QTY'] += $qty;
        if ($kat == 'PULSA') { $det['PULSA'] = $qty; $det['PULSA_TRX'] = $trx; }
        else if ($kat == 'PERDANA') $det['PERDANA'] = $qty;
        else if (str_contains($kat, 'VOUC')) $det['VOUCHER'] = $qty;
        else if (str_contains($kat, 'ACC') || str_contains($kat, 'AKSESORIS')) $det['ACC'] += $qty;
    }
    $stmt->close();
    return $det;
}
?>