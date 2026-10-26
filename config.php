<?php
// === KONFIGURASI DATABASE SULTAN V4.8 ANTI INJECTION ===

// Matikan tampilan error ke user (biar hacker gak liat struktur db)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
ini_set('display_errors', 0);
error_reporting(0);

$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_konter";

try {
    $conn = new mysqli($host, $user, $pass, $db);
    
    // SET CHARSET PALING AMAN - Anti injection via encoding
    $conn->set_charset("utf8mb4");
    
    // SET TIMEZONE
    date_default_timezone_set('Asia/Jakarta');

    // SET MODE KETAT - Biar query ngawur langsung error
    $conn->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");

} catch (mysqli_sql_exception $e) {
    // JANGAN TAMPILKAN ERROR ASLI KE USER - Bahaya!
    error_log("DB Error: " . $e->getMessage()); // Simpan di log server aja
    die("Koneksi Database Gagal. Hubungi Admin!"); 
}

function query_aman($conn, $sql, $types = "", $params = []) {
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result() ?? $stmt; // biar bisa SELECT & INSERT
}
?>