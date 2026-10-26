<?php
session_start();
require_once 'config.php';
require_once 'auth.php';
if(isset($_SESSION['id'])){ header("Location: index.php"); exit; }

$error = "";
if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $konfirmasi = $_POST['konfirmasi'];
    $role_pilih = $_POST['role'];
    $kode_admin = trim($_POST['kode_admin'] ?? '');
    $KODE_RAHASIA_ADMIN = 'ALFIN_MALANG77';

    if(strlen($username) > 16){ $error = "Username maksimal 16 karakter! (CTO Standard)"; }
    elseif(strlen($username) < 3){ $error = "Username minimal 3 karakter!"; }
    elseif(strlen($password) > 10){ $error = "Password maksimal 10 karakter! (CTO Standard)"; }
    elseif(strlen($password) < 4){ $error = "Password minimal 4 karakter!"; }
    elseif($password !== $konfirmasi){ $error = "Password gak sama!"; }
    elseif(!preg_match("/^[a-zA-Z0-9_]+$/", $username)){ $error = "Username hanya huruf, angka, underscore!"; }
    else {
        $role_final = 'pramuniaga';
        if($role_pilih == 'admin'){
            if($kode_admin !== $KODE_RAHASIA_ADMIN){ $error = "Kode rahasia admin salah! Hubungi Alfin!"; }
            else { $role_final = 'admin'; }
        }
        if(!$error){
            $cek = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $cek->bind_param("s", $username); $cek->execute();
            if($cek->get_result()->num_rows > 0){ $error = "Username udah ada!"; }
            else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $username, $hash, $role_final); $stmt->execute();
                header("Location: login.php?msg=Berhasil daftar sebagai $role_final! Silakan login $username"); exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daftar - Konter Alfin</title>
<link href="bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex justify-content-center align-items-center" style="min-height:100vh;">
<div class="container"><div class="row justify-content-center"><div class="col-md-4">
<div class="card shadow rounded-4"><div class="card-body p-4">
<h4 class="text-center mb-3">📝 Daftar Akun</h4>
<?php if($error): ?><div class="alert alert-danger rounded-3"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="POST" id="formReg">
<!-- SAMA PERSIS KAYAK LOGIN YANG UDAH MAX! -->
<input type="text" name="username" id="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" class="form-control mb-1" placeholder="Username baru (max 16)" required maxlength="16" oninput="updateCount(this, 'userInfo', 16)">
<small id="userInfo" class="text-muted d-block mb-2"><?= strlen($_POST['username'] ?? '') ?>/16 karakter</small>

<input type="password" name="password" id="password" class="form-control mb-1" placeholder="Password (max 10)" required maxlength="10" oninput="updateCount(this, 'passInfo', 10)">
<small id="passInfo" class="text-muted d-block mb-2">0/10 karakter</small>

<input type="password" name="konfirmasi" id="konfirmasi" class="form-control mb-2" placeholder="Ulangi password (max 10)" required maxlength="10" oninput="updateCount(this, 'passInfo2', 10)">
<small id="passInfo2" class="text-muted d-block mb-2">0/10 karakter</small>

<select name="role" class="form-select mb-2" id="roleSelect" onchange="cekRole()">
<option value="pramuniaga">Daftar sebagai Pramuniaga</option>
<option value="admin">Daftar sebagai Admin (Butuh Kode)</option>
</select>
<div id="kodeBox" style="display:none;">
<input type="text" name="kode_admin" class="form-control mb-1" placeholder="Masukkan Kode Rahasia Admin">
<small class="text-muted">Kode: tanya ke Alfin</small>
</div>
<button class="btn btn-success w-100 fw-bold mt-3">Daftar</button>
</form>
<div class="text-center mt-3">
    <small>Udah punya akun? <a href="login.php" class="fw-bold">Login</a></small>
</div>
</div>
</div>
</div>
</div>
</div>
<script>
function cekRole(){ document.getElementById('kodeBox').style.display = document.getElementById('roleSelect').value == 'admin' ? 'block' : 'none'; }
// SAMA PERSIS KAYAK LOGIN - UDAH ADA MAX! MERAH
function updateCount(input, id, max){
    let len = input.value.length;
    let el = document.getElementById(id);
    el.innerText = len + "/" + max + " karakter";
    if(len >= max){
        el.innerHTML = len + "/" + max + " karakter - <b class='text-danger'>MAX!</b>";
    }
}
document.getElementById('formReg').addEventListener('submit', function(e){
    if(document.getElementById('username').value.length > 16 || document.getElementById('password').value.length > 10 || document.getElementById('konfirmasi').value.length > 10){
        e.preventDefault(); alert("Karakter melebihi batas CTO! Username max 16, Password max 10");
    }
});
</script>
</body>
</html>