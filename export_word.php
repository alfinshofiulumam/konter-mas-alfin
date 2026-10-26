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

require_once 'vendor/autoload.php';
use PhpOffice\PhpWord\PhpWord;

function validTanggal($tgl) {
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl) ? $tgl : date('Y-m-d');
}
$tgl_awal = validTanggal($_GET['awal'] ?? $_GET['tgl_awal'] ?? date('Y-m-d'));
$tgl_akhir = validTanggal($_GET['akhir'] ?? $_GET['tgl_akhir'] ?? date('Y-m-d'));
if ($tgl_awal > $tgl_akhir) { $tmp=$tgl_awal; $tgl_awal=$tgl_akhir; $tgl_akhir=$tmp; }

$rekap = ['total_transaksi'=>0,'total_qty'=>0,'total_modal'=>0,'total_omzet'=>0,'total_laba'=>0];
$stmt = $conn->prepare("SELECT COUNT(*) as total_transaksi, COALESCE(SUM(qty),0) as total_qty, COALESCE(SUM(modal_total),0) as total_modal, COALESCE(SUM(jual_total),0) as total_omzet, COALESCE(SUM(laba),0) as total_laba FROM transaksi WHERE DATE(tanggal) BETWEEN ? AND ?");
$stmt->bind_param("ss",$tgl_awal,$tgl_akhir); $stmt->execute(); $res=$stmt->get_result()->fetch_assoc(); if($res) $rekap=$res; $stmt->close();

$tgl_kemarin = date('Y-m-d', strtotime($tgl_awal.' -1 day'));
$stmt_lag = $conn->prepare("SELECT COALESCE(SUM(laba),0) as laba_kemarin FROM transaksi WHERE DATE(tanggal)=?"); $stmt_lag->bind_param("s",$tgl_kemarin); $stmt_lag->execute(); $lag_total=(int)$stmt_lag->get_result()->fetch_assoc()['laba_kemarin']; $stmt_lag->close();
$tren_total=(int)$rekap['total_laba']-$lag_total; $kombinasi_total=$lag_total+$tren_total;

$det = ['PULSA'=>0,'VOUCHER'=>0,'ACC'=>0,'PERDANA'=>0,'PULSA_TRX'=>0];
$stmt=$conn->prepare("SELECT p.kategori, COUNT(*) as jml_trx, COALESCE(SUM(t.qty),0) as qty FROM transaksi t JOIN produk p ON t.produk_id=p.id WHERE DATE(t.tanggal) BETWEEN ? AND ? GROUP BY p.kategori");
$stmt->bind_param("ss",$tgl_awal,$tgl_akhir); $stmt->execute(); $res=$stmt->get_result();
while($d=$res->fetch_assoc()){ $k=strtoupper($d['kategori']); if(strpos($k,'PULSA')!==false){ $det['PULSA']=$d['qty']; $det['PULSA_TRX']=$d['jml_trx']; } else if(strpos($k,'VOUCH')!==false){ $det['VOUCHER']=$d['qty']; } else if(strpos($k,'ACC')!==false || strpos($k,'AKSES')!==false){ $det['ACC']=$d['qty']; } else if(strpos($k,'PERDANA')!==false){ $det['PERDANA']=$d['qty']; } } $stmt->close();

$phpWord = new PhpWord();
$section = $phpWord->addSection();
$section->addText('Laporan Konter Alfin Shofiul Umam Kepanjen, Talangagung', ['bold'=>true,'size'=>14]);
$section->addText('Periode: '.$tgl_awal.' s/d '.$tgl_akhir);
$section->addText('Total Transaksi: '.$rekap['total_transaksi'].'x | '.$rekap['total_qty'].' Pcs terjual');
$section->addText('Rincian: Pulsa '.$det['PULSA_TRX'].' trx / '.$det['PULSA'].' pcs | Voucher '.$det['VOUCHER'].' | Acc '.$det['ACC'].' | Perdana '.$det['PERDANA']);
$section->addText('Total Modal: Rp '.number_format($rekap['total_modal']));
$section->addText('Total Omzet: Rp '.number_format($rekap['total_omzet']));
$section->addText('Total Laba Bersih: Rp '.number_format($rekap['total_laba']), ['bold'=>true]);
$section->addText('LAG (Kemarin): Rp '.number_format($lag_total), ['bold'=>true]);
$section->addText('TREN: '.($tren_total>=0?'+':'').'Rp '.number_format($tren_total).' vs kemarin', ['bold'=>true]);
$section->addText('KOMBINASI (LAG+TREN): Rp '.number_format($kombinasi_total).' = Laba Hari Ini', ['bold'=>true]);

$table = $section->addTable(['borderSize'=>6,'borderColor'=>'999999']);
$table->addRow(); $table->addCell(800)->addText('No',['bold'=>true]); $table->addCell(2000)->addText('Waktu',['bold'=>true]); $table->addCell(3000)->addText('Produk',['bold'=>true]); $table->addCell(1500)->addText('Kategori',['bold'=>true]); $table->addCell(1500)->addText('Merk',['bold'=>true]); $table->addCell(800)->addText('Qty',['bold'=>true]); $table->addCell(1500)->addText('Modal',['bold'=>true]); $table->addCell(1500)->addText('Jual',['bold'=>true]); $table->addCell(1500)->addText('Laba',['bold'=>true]);

$stmt=$conn->prepare("SELECT t.*, p.nama, p.kategori, p.merk FROM transaksi t JOIN produk p ON t.produk_id=p.id WHERE DATE(t.tanggal) BETWEEN ? AND ? ORDER BY t.tanggal DESC"); $stmt->bind_param("ss",$tgl_awal,$tgl_akhir); $stmt->execute(); $res=$stmt->get_result(); $no=1;
while($d=$res->fetch_assoc()){
    $table->addRow();
    $table->addCell()->addText($no++); $table->addCell()->addText($d['tanggal']); $table->addCell()->addText($d['nama'] ?? $d['merk']); $table->addCell()->addText($d['kategori']); $table->addCell()->addText($d['merk']); $table->addCell()->addText($d['qty']); $table->addCell()->addText('Rp '.number_format($d['modal_total'])); $table->addCell()->addText('Rp '.number_format($d['jual_total'])); $table->addCell()->addText('Rp '.number_format($d['laba']));
}

$filename='Laporan_Konter_Alfin_'.$tgl_awal.'_sd_'.$tgl_akhir.'.docx';
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document'); header('Content-Disposition: attachment; filename="'.$filename.'"');
$objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007'); $objWriter->save('php://output'); exit;
