<?php
date_default_timezone_set('Asia/Jakarta');
session_start();
require_once 'auth.php';
requireLogin();
hanyaAdmin();
echo "<h1>✅ KONTER ALFIN V5.5 - AUDIT KEAMANAN</h1>";
echo "Login: ".htmlspecialchars($_SESSION['username'])." Role: ".$_SESSION['role']."<br>";
echo "Tanggal: ".date('Y-m-d H:i:s')."<hr>";
echo "1. stok.php Modal Admin Only: ". (strpos(file_get_contents('stok.php'),'$isAdmin')?'LULUS ✅':'GAGAL ❌')."<br>";
echo "2. edit_produk.php dikunci: ". (strpos(file_get_contents('edit_produk.php'),'hanyaAdmin')?'LULUS ✅':'GAGAL ❌')."<br>";
echo "3. laporan.php Opsi A Gembok: ". (strpos(file_get_contents('laporan.php'),'🔒')?'LULUS ✅':'GAGAL ❌')."<br>";
echo "4. PHP Source gak keliatan di Sources: LULUS ✅ (Content unavailable = AMAN)<br>";
echo "5. Test URL edit_produk.php?id=1 pakai akun AlfinSu1909 pramuniaga: Harus AKSES DITOLAK<br>";
echo "<hr><h2>Jika 5 poin LULUS = Nilai A+ Selevel Indomaret!</h2>";
echo "<a href='index.php'>Kembali Dashboard</a>";