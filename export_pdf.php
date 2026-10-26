<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';
require_once 'auth.php';
requireLogin();
$isAdmin = strtolower($_SESSION['role'] ?? '') == 'admin';
if(!$isAdmin){
    http_response_code(403);
    die("🔒 403 - Hanya ADMIN bisa unduh laporan! Login kamu PRAMUNIAGA.");
}
mysqli_report(MYSQLI_REPORT_OFF);

function validTanggal($tgl){ return preg_match('/^\d{4}-\d{2}-\d{2}$/',$tgl)?$tgl:date('Y-m-d'); }
$tgl_awal=validTanggal($_GET['awal']??$_GET['tgl_awal']??date('Y-m-d'));
$tgl_akhir=validTanggal($_GET['akhir']??$_GET['tgl_akhir']??date('Y-m-d'));
if($tgl_awal>$tgl_akhir){ $tmp=$tgl_awal; $tgl_awal=$tgl_akhir; $tgl_akhir=$tmp; }

$rekap=['total_transaksi'=>0,'total_qty'=>0,'total_modal'=>0,'total_omzet'=>0,'total_laba'=>0];
$stmt=$conn->prepare("SELECT COUNT(*) as total_transaksi, COALESCE(SUM(qty),0) as total_qty, COALESCE(SUM(modal_total),0) as total_modal, COALESCE(SUM(jual_total),0) as total_omzet, COALESCE(SUM(laba),0) as total_laba FROM transaksi WHERE DATE(tanggal) BETWEEN ? AND ?");
$stmt->bind_param("ss",$tgl_awal,$tgl_akhir); $stmt->execute(); $res=$stmt->get_result()->fetch_assoc(); if($res) $rekap=$res; $stmt->close();

$tgl_kemarin=date('Y-m-d',strtotime($tgl_awal.' -1 day'));
$stmt_lag=$conn->prepare("SELECT COALESCE(SUM(laba),0) as laba_kemarin FROM transaksi WHERE DATE(tanggal)=?"); $stmt_lag->bind_param("s",$tgl_kemarin); $stmt_lag->execute(); $lag_total=(int)$stmt_lag->get_result()->fetch_assoc()['laba_kemarin']; $stmt_lag->close();
$tren_total=(int)$rekap['total_laba']-$lag_total; $kombinasi_total=$lag_total+$tren_total;

$det=['PULSA'=>0,'VOUCHER'=>0,'ACC'=>0,'PERDANA'=>0,'PULSA_TRX'=>0];
$stmt=$conn->prepare("SELECT p.kategori, COUNT(*) as jml_trx, COALESCE(SUM(t.qty),0) as qty FROM transaksi t JOIN produk p ON t.produk_id=p.id WHERE DATE(t.tanggal) BETWEEN ? AND ? GROUP BY p.kategori");
$stmt->bind_param("ss",$tgl_awal,$tgl_akhir); $stmt->execute(); $res=$stmt->get_result();
while($d=$res->fetch_assoc()){ $k=strtoupper($d['kategori']); if(strpos($k,'PULSA')!==false){ $det['PULSA']=$d['qty']; $det['PULSA_TRX']=$d['jml_trx']; } else if(strpos($k,'VOUCH')!==false){ $det['VOUCHER']=$d['qty']; } else if(strpos($k,'ACC')!==false || strpos($k,'AKSES')!==false){ $det['ACC']=$d['qty']; } else if(strpos($k,'PERDANA')!==false){ $det['PERDANA']=$d['qty']; } } $stmt->close();

ob_start();
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:Arial;font-size:11px;} table{border-collapse:collapse;width:100%;} th,td{border:1px solid #000;padding:5px;} th{background:#000;color:#fff;} .box{background:#198754;color:#fff;padding:10px;border-radius:8px;margin:10px 0;}</style></head><body>
<h3>Laporan Konter Alfin Shofiul Umam Kepanjen, Talangagung</h3>
<p>Periode: <?= htmlspecialchars($tgl_awal) ?> s/d <?= htmlspecialchars($tgl_akhir) ?> | Total: <?= $rekap['total_transaksi'] ?>x | <?= $rekap['total_qty'] ?> Pcs</p>
<p>Rincian: Pulsa <?= $det['PULSA_TRX'] ?> trx / <?= $det['PULSA'] ?> pcs | Voucher <?= $det['VOUCHER'] ?> | Acc <?= $det['ACC'] ?> | Perdana <?= $det['PERDANA'] ?></p>
<div class="box">
<b>Total Laba Bersih: Rp <?= number_format($rekap['total_laba']) ?></b><br>
📉 LAG (Kemarin <?= $tgl_kemarin ?>): Rp <?= number_format($lag_total) ?><br>
📈 TREN: <?= ($tren_total>=0?'+':'').'Rp '.number_format($tren_total) ?> vs kemarin<br>
🔗 KOMBINASI: Rp <?= number_format($kombinasi_total) ?> (LAG + TREN = Laba Hari Ini)
</div>
<table><tr><th>No</th><th>Waktu Lengkap</th><th>Produk</th><th>Kategori</th><th>Merk</th><th>Qty</th><th>Modal</th><th>Jual</th><th>Laba</th></tr>
<?php
$stmt=$conn->prepare("SELECT t.*, p.nama, p.kategori, p.merk FROM transaksi t JOIN produk p ON t.produk_id=p.id WHERE DATE(t.tanggal) BETWEEN ? AND ? ORDER BY t.tanggal DESC");
$stmt->bind_param("ss",$tgl_awal,$tgl_akhir); $stmt->execute(); $res=$stmt->get_result(); $no=1;
while($d=$res->fetch_assoc()){
    echo '<tr><td>'.$no++.'</td><td>'.$d['tanggal'].'</td><td>'.htmlspecialchars($d['nama'] ?? $d['merk']).'</td><td>'.$d['kategori'].'</td><td>'.$d['merk'].'</td><td>'.$d['qty'].'</td><td>Rp '.number_format($d['modal_total']).'</td><td>Rp '.number_format($d['jual_total']).'</td><td>Rp '.number_format($d['laba']).'</td></tr>';
}
?>
</table>
</body></html>
<?php
$html=ob_get_clean();
require_once 'vendor/autoload.php';
use Dompdf\Dompdf;
$dompdf=new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4','landscape');
$dompdf->render();
$dompdf->stream('Laporan_Konter_Alfin_'.$tgl_awal.'_sd_'.$tgl_akhir.'.pdf', ['Attachment'=>1]);
exit;
