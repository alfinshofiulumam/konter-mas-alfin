<?php
// auth.php V5.4 - LENGKAP ANTI 500
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireLogin() {
    if (empty($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
    // auto logout 8 jam
    if (isset($_SESSION['LAST_ACTIVITY']) && time() - $_SESSION['LAST_ACTIVITY'] > 28800) {
        session_unset();
        session_destroy();
        header("Location: login.php?msg=expired");
        exit;
    }
    $_SESSION['LAST_ACTIVITY'] = time();
}

function requireRole($roles = []) {
    requireLogin();
    $role = strtolower($_SESSION['role'] ?? '');
    $roles = array_map('strtolower', $roles);
    if (!in_array($role, $roles)) {
        die("<h1>🚫 AKSES DITOLAK! Role $role tidak boleh akses!</h1><a href='index.php'>Dashboard</a>");
    }
}

function hanyaAdmin() {
    requireRole(['admin']);
}

function buatCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function cekCsrfToken() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $token_post = $_POST['csrf_token'] ?? '';
    $token_sess = $_SESSION['csrf_token'] ?? '';
    if (empty($token_post) || empty($token_sess) || !hash_equals($token_sess, $token_post)) {
        http_response_code(403);
        die("
        <div style='font-family:Arial; text-align:center; padding:50px;'>
            <h1>🚫 403 CSRF DITOLAK!</h1>
            <p>Token tidak valid! Terdeteksi jebakan link WA!</p>
            <p>Jangan klik link hapus dari WA! Hapus harus lewat tombol di website!</p>
            <br><a href='index.php' style='background:#000; color:#fff; padding:10px 20px; border-radius:10px; text-decoration:none;'>Kembali Dashboard</a>
        </div>
        ");
    }
}
?>