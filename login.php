<?php
session_start();
require_once 'config.php';
require_once 'auth.php';

if(isset($_SESSION['id'])){
    header("Location: index.php");
    exit;
}

// === CTO: PROTEKSI BRUTE FORCE SIMPLE ===
if(!isset($_SESSION['login_attempts'])) $_SESSION['login_attempts'] = 0;
if(!isset($_SESSION['last_attempt'])) $_SESSION['last_attempt'] = 0;

$error = "";
$success = $_GET['msg'] ?? '';

// Blok 2 menit kalau salah 5x
if($_SESSION['login_attempts'] >= 5 && (time() - $_SESSION['last_attempt']) < 120){
    $sisa = 120 - (time() - $_SESSION['last_attempt']);
    $error = "Kebanyakan salah! Tunggu {$sisa} detik lagi! (CTO Anti Brute Force)";
} else {
    if($_SERVER['REQUEST_METHOD'] == 'POST'){
        $username = trim($_POST['username']);
        $password = $_POST['password'];

        // === STANDAR CTO: VALIDASI PANJANG SAMA KAYAK REGISTER ===
        if(strlen($username) > 16 || strlen($username) < 3){
            $error = "Username harus 3-16 karakter!";
        } elseif(strlen($password) > 10 || strlen($password) < 4){
            $error = "Password harus 4-10 karakter!";
        } elseif(!preg_match("/^[a-zA-Z0-9_]+$/", $username)){
            $error = "Username tidak valid!";
        } else {
            $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if($result->num_rows === 1){
                $user = $result->fetch_assoc();
                if(password_verify($password, $user['password'])){
                    // SUKSES - RESET ATTEMPT
                    $_SESSION['login_attempts'] = 0;
                    session_regenerate_id(true);
                    $_SESSION['id'] = $user['id'];
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = strtolower(trim($user['role']));
                    $_SESSION['LAST_ACTIVITY'] = time();
                    $_SESSION['CREATED'] = time();
                    header("Location: index.php");
                    exit;
                }
            }
            // GAGAL
            $_SESSION['login_attempts']++;
            $_SESSION['last_attempt'] = time();
            $error = "Username atau password salah! (Percobaan ke-".$_SESSION['login_attempts'].")";
        }
    }
    // Reset otomatis kalau udah lewat 2 menit
    if((time() - $_SESSION['last_attempt']) > 120){
        $_SESSION['login_attempts'] = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Konter Alfin</title>
    <link href="bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex justify-content-center align-items-center" style="min-height:100vh; margin:0;">
<div class="container">
<div class="row justify-content-center">
<div class="col-md-4">
<div class="card shadow rounded-4">
<div class="card-body p-4">
<h4 class="text-center mb-3">🔐 Konter Alfin - Login</h4>

<?php if($success): ?>
<div class="alert alert-success rounded-3"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if(isset($_GET['timeout'])): ?>
<div class="alert alert-warning">Sesi habis 8 jam, login ulang!</div>
<?php endif; ?>

<?php if($error): ?>
<div class="alert alert-danger rounded-3"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST" id="formLogin">
<!-- CTO: MAXLENGTH SINKRON SAMA REGISTER -->
<input type="text" name="username" id="username" 
value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" 
class="form-control mb-1" placeholder="Username (max 16)" 
required maxlength="16" oninput="updateCount(this, 'uInfo', 16)">
<small id="uInfo" class="text-muted d-block mb-2"><?= strlen($_POST['username'] ?? '') ?>/16 karakter</small>

<input type="password" name="password" id="password" 
class="form-control mb-1" placeholder="Password (max 10)" 
required maxlength="10" oninput="updateCount(this, 'pInfo', 10)">
<small id="pInfo" class="text-muted d-block mb-2">0/10 karakter</small>

<button class="btn btn-primary w-100 fw-bold mt-2">Login</button>
<div class="text-center mt-3">
    <small>Belum punya akun? <a href="register.php" class="fw-bold text-success">Daftar di sini</a></small>
</div>
</form>
<p class="text-center text-muted small mt-3">Talangagung - Kepanjen, Malang</p>
</div>
</div>
</div>
</div>
</div>
<script>
function updateCount(input, id, max){
    let len = input.value.length;
    document.getElementById(id).innerText = len + "/" + max + " karakter";
    if(len >= max){
        document.getElementById(id).innerHTML = len + "/" + max + " karakter - <b class='text-danger'>MAX!</b>";
    }
}
</script>
</body>
</html>