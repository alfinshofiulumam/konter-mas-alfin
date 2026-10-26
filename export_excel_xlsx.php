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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function validTanggal($tgl) {
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl) ? $tgl : date('Y-m-d');
}
$tgl_awal = validTanggal($_GET['awal'] ?? $_GET['tgl_awal'] ?? date('Y-m-d'));
$tgl_akhir = validTanggal($_GET['akhir'] ?? $_GET['tgl_akhir'] ?? date('Y-m-d'));
if ($tgl_awal > $tgl_akhir) {
    $tmp = $tgl_awal;
    $tgl_awal = $tgl_akhir;
    $tgl_akhir = $tmp;
}

// REKAP - SAMA KAYAK LAPORAN.PHP
$rekap = ['total_transaksi'=>0,'total_qty'=>0,'total_modal'=>0,'total_omzet'=>0,'total_laba'=>0];
$stmt = $conn->prepare("SELECT COUNT(*) as total_transaksi, COALESCE(SUM(qty),0) as total_qty, COALESCE(SUM(modal_total),0) as total_modal, COALESCE(SUM(jual_total),0) as total_omzet, COALESCE(SUM(laba),0) as total_laba FROM transaksi WHERE DATE(tanggal) BETWEEN ? AND ?");
$stmt->bind_param("ss", $tgl_awal, $tgl_akhir);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
if ($res) $rekap = $res;
$stmt->close();

// LAG TREN KOMBINASI - SAMA KAYAK DATA_TRANSAKSI.PHP
$tgl_kemarin = date('Y-m-d', strtotime($tgl_awal.' -1 day'));
$stmt_lag = $conn->prepare("SELECT COALESCE(SUM(laba),0) as laba_kemarin FROM transaksi WHERE DATE(tanggal)=?");
$stmt_lag->bind_param("s", $tgl_kemarin);
$stmt_lag->execute();
$lag_total = (int)$stmt_lag->get_result()->fetch_assoc()['laba_kemarin'];
$stmt_lag->close();
$tren_total = (int)$rekap['total_laba'] - $lag_total;
$kombinasi_total = $lag_total + $tren_total;

// RINCIAN PISAH KATEGORI - SAMA KAYAK LAPORAN.PHP
$det = ['PULSA'=>0,'VOUCHER'=>0,'ACC'=>0,'PERDANA'=>0,'PULSA_TRX'=>0];
$stmt = $conn->prepare("SELECT p.kategori, COUNT(*) as jml_trx, COALESCE(SUM(t.qty),0) as qty FROM transaksi t JOIN produk p ON t.produk_id=p.id WHERE DATE(t.tanggal) BETWEEN ? AND ? GROUP BY p.kategori");
$stmt->bind_param("ss", $tgl_awal, $tgl_akhir);
$stmt->execute();
$res = $stmt->get_result();
while($d=$res->fetch_assoc()){
    $k=strtoupper($d['kategori']);
    if(strpos($k,'PULSA')!==false){ $det['PULSA']=$d['qty']; $det['PULSA_TRX']=$d['jml_trx']; }
    else if(strpos($k,'VOUCH')!==false){ $det['VOUCHER']=$d['qty']; }
    else if(strpos($k,'ACC')!==false || strpos($k,'AKSES')!==false){ $det['ACC']=$d['qty']; }
    else if(strpos($k,'PERDANA')!==false){ $det['PERDANA']=$d['qty']; }
}
$stmt->close();

// BUILD EXCEL
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Laporan Alfin');

$sheet->setCellValue('A1', 'Laporan Konter Alfin Shofiul Umam Kepanjen, Talangagung');
$sheet->setCellValue('A2', 'Periode: '.$tgl_awal.' s/d '.$tgl_akhir);
$sheet->setCellValue('A3', 'Total Transaksi: '.$rekap['total_transaksi'].'x | '.$rekap['total_qty'].' Pcs');
$sheet->setCellValue('A4', 'Rincian: Pulsa '.$det['PULSA_TRX'].' trx / '.$det['PULSA'].' pcs | Voucher '.$det['VOUCHER'].' pcs | Acc '.$det['ACC'].' pcs | Perdana '.$det['PERDANA'].' pcs');
$sheet->setCellValue('A5', 'Total Modal: Rp '.number_format($rekap['total_modal']));
$sheet->setCellValue('A6', 'Total Omzet: Rp '.number_format($rekap['total_omzet']));
$sheet->setCellValue('A7', 'Total Laba Bersih: Rp '.number_format($rekap['total_laba']));
$sheet->setCellValue('A8', 'LAG (Kemarin): Rp '.number_format($lag_total));
$sheet->setCellValue('A9', 'TREN: '.($tren_total>=0?'+':'').'Rp '.number_format($tren_total).' vs kemarin');
$sheet->setCellValue('A10', 'KOMBINASI (LAG+TREN): Rp '.number_format($kombinasi_total));
$sheet->getStyle('A7:A10')->getFont()->setBold(true);

$sheet->setCellValue('A12', 'No');
$sheet->setCellValue('B12', 'Waktu Lengkap');
$sheet->setCellValue('C12', 'Produk');
$sheet->setCellValue('D12', 'Kategori');
$sheet->setCellValue('E12', 'Merk');
$sheet->setCellValue('F12', 'Qty');
$sheet->setCellValue('G12', 'Modal Total');
$sheet->setCellValue('H12', 'Jual Total');
$sheet->setCellValue('I12', 'Laba');
$sheet->getStyle('A12:I12')->getFont()->setBold(true);

$stmt = $conn->prepare("SELECT t.*, p.nama, p.kategori, p.merk FROM transaksi t JOIN produk p ON t.produk_id=p.id WHERE DATE(t.tanggal) BETWEEN ? AND ? ORDER BY t.tanggal DESC");
$stmt->bind_param("ss", $tgl_awal, $tgl_akhir);
$stmt->execute();
$res = $stmt->get_result();
$row = 13;
$no = 1;
while($d=$res->fetch_assoc()){
    $sheet->setCellValue('A'.$row, $no++);
    $sheet->setCellValue('B'.$row, $d['tanggal']);
    $sheet->setCellValue('C'.$row, $d['nama'] ?? $d['merk']);
    $sheet->setCellValue('D'.$row, $d['kategori']);
    $sheet->setCellValue('E'.$row, $d['merk']);
    $sheet->setCellValue('F'.$row, $d['qty']);
    $sheet->setCellValue('G'.$row, $d['modal_total']);
    $sheet->setCellValue('H'.$row, $d['jual_total']);
    $sheet->setCellValue('I'.$row, $d['laba']);
    $row++;
}

foreach(range('A','I') as $col){ $sheet->getColumnDimension($col)->setAutoSize(true); }

$filename = 'Laporan_Konter_Alfin_'.$tgl_awal.'_sd_'.$tgl_akhir.'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'"');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
