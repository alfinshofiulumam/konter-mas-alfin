<?php
require_once 'config.php';
require_once 'auth.php';
requireLogin();

$msg = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass_lama = $_POST['pass_lama'] ?? '';
    $pass_baru = $_POST['pass_baru'] ?? '';
    $pass_konfirm = $_POST['pass_konfirm'] ?? '';

    $username = getUsername();
    $q = $conn->prepare("SELECT password FROM users WHERE username = ?");
    $q->bind_param("s", $username);
    $q->execute();
    $row = $q->get_result()->fetch_assoc();

    if (!$row || !password_verify($pass_lama, $row['password'])) {
        $msg = "Password lama salah, Mas!";
        $msg_type = "danger";
    } elseif (strlen($pass_baru) < 6) {
        $msg = "Password baru minimal 6 karakter!";
        $msg_type = "warning";
    } elseif ($pass_baru !== $pass_konfirm) {
        $msg = "Konfirmasi password gak sama!";
        $msg_type = "warning";
    } else {
        $hash = password_hash($pass_baru, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
        $upd->bind_param("ss", $hash, $username);
        if ($upd->execute()) {
            // === AUTO LOGOUT SETELAH GANTI PASSWORD ===
            session_unset();
            session_destroy();
            header("Location: login.php?msg=Password berhasil diganti! Silahkan login ulang dengan password baru.");
            exit;
        } else {
            $msg = "Gagal update: " . $conn->error;
            $msg_type = "danger";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Ganti Password - Konter Alfin</title>
    <link href="bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex justify-content-center align-items-center" style="min-height:100vh;">
    <div class="col-md-4">
        <div class="card shadow-lg border-0 rounded-4">
            <div class="card-body p-4">
                <a href="index.php" class="btn btn-light btn-sm mb-3">⬅️ Dashboard</a>
                <h4 class="fw-bold">🔑 Ganti Password</h4>
                <p class="text-muted small">Username: <b><?= clean(getUsername()) ?></b> (<?= clean(getRole()) ?>)</p>

                <?php if($msg): ?>
                <div class="alert alert-<?= $msg_type ?> rounded-3"><?= $msg ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label small">Password Lama</label>
                        <input type="password" name="pass_lama" class="form-control rounded-3" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Password Baru (min 6)</label>
                        <input type="password" name="pass_baru" class="form-control rounded-3" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Konfirmasi Password Baru</label>
                        <input type="password" name="pass_konfirm" class="form-control rounded-3" required>
                    </div>
                    <button class="btn btn-primary w-100 rounded-3 fw-bold">💾 Simpan & Login Ulang</button>
                </form>
            </div>
        </div>
    </div>
<script src="bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
