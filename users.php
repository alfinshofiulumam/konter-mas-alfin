<?php
require_once 'config.php';
require_once 'auth.php';
requireRole(['admin']);

$my_id = $_SESSION['id'] ?? $_SESSION['id'] ?? 0;
$msg = '';

// Tambah user
if (isset($_POST['tambah'])) {
    $u = trim($_POST['username']);
    $p = $_POST['password'];
    $r = strtolower(trim($_POST['role']));
    $hash = password_hash($p, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $u, $hash, $r);
    if ($stmt->execute()) {
        $msg = "<div class='alert alert-success'>User <b>".htmlspecialchars($u)."</b> ditambah!</div>";
    } else {
        $msg = "<div class='alert alert-danger'>Gagal, username udah ada!</div>";
    }
}

// HAPUS USER - DENGAN PENGAMAN GANDA
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];

    // 1. Cegah hapus diri sendiri
    if ($id == $my_id) {
        $msg = "<div class='alert alert-danger fw-bold'>🚫 GAK BOLEH HAPUS AKUN SENDIRI, MAS ALFIN!</div>";
    }
    // 2. Cegah hapus admin terakhir
    else {
        $cek = $conn->prepare("SELECT role FROM users WHERE id = ?");
        $cek->bind_param("i", $id);
        $cek->execute();
        $target = $cek->get_result()->fetch_assoc();

        if ($target && $target['role'] == 'admin') {
            $countAdmin = $conn->query("SELECT COUNT(*) as jml FROM users WHERE role='admin'")->fetch_assoc()['jml'];
            if ($countAdmin <= 1) {
                $msg = "<div class='alert alert-danger fw-bold'>🚫 Gak boleh hapus admin terakhir! Minimal harus ada 1 admin!</div>";
            } else {
                $conn->query("DELETE FROM users WHERE id = $id");
                $msg = "<div class='alert alert-success'>Admin dengan ID $id dihapus!</div>";
            }
        } else {
            $conn->query("DELETE FROM users WHERE id = $id");
            $msg = "<div class='alert alert-success'>User dihapus!</div>";
        }
    }
}

$users = $conn->query("SELECT id, username, role, created_at FROM users ORDER BY id DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Kelola User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">

    <a href="index.php" class="btn btn-light btn-sm mb-3">⬅️ Dashboard</a>
    <h3 class="fw-bold">👥 Kelola User</h3>
    <?= $msg ?>
    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body">
                    <h6 class="fw-bold">➕ Tambah User</h6>
                    <form method="POST">
                        <input name="username" class="form-control mb-2" required placeholder="Username">
                        <input name="password" type="password" class="form-control mb-2" required placeholder="Password min 6">
                        <select name="role" class="form-select mb-3">
                            <option value="pramuniaga">Pramuniaga</option>
                            <option value="admin">Admin</option>
                        </select>
                        <button name="tambah" class="btn btn-primary w-100 fw-bold">Simpan</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body">
                    <table class="table">
                        <tr><th>ID</th><th>Username</th><th>Role</th><th>Aksi</th></tr>
                        <?php while($u = $users->fetch_assoc()): ?>
                        <tr>
                            <td><?= $u['id'] ?></td>
                            <td><?= htmlspecialchars($u['username']) ?> <?= ($u['id']==$my_id)?'<span class="badge bg-dark">KAMU</span>':'' ?></td>
                            <td><span class="badge bg-<?= $u['role']=='admin'?'danger':'primary' ?>"><?= $u['role'] ?></span></td>
                            <td>
                                <?php if($u['id'] == $my_id): ?>
                                    <button class="btn btn-sm btn-secondary" disabled>Ini kamu</button>
                                <?php else: ?>
                                    <a href="?hapus=<?= $u['id'] ?>" onclick="return confirm('Yakin hapus <?= htmlspecialchars($u['username']) ?>?')" class="btn btn-sm btn-outline-danger">Hapus</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
