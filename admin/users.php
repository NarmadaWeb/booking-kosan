<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../admin_login.php");
    exit();
}
require_once '../config/database.php';

$success_msg = '';
$error_msg = '';

// Handle Delete Action
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    // Prevent deleting self
    if ($id == $_SESSION['user_id']) {
        $error_msg = "Tidak dapat menghapus akun sendiri.";
    } else {
        $check = $conn->prepare("SELECT peran FROM pengguna WHERE id_pengguna = ?");
        $check->bind_param("i", $id);
        $check->execute();
        $res = $check->get_result();
        if($res->num_rows > 0) {
            $user = $res->fetch_assoc();
            if($user['peran'] == 'admin') {
                 $error_msg = "Tidak dapat menghapus akun Admin.";
            } else {
                $stmt = $conn->prepare("DELETE FROM pengguna WHERE id_pengguna = ?");
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    header("Location: users.php?msg=deleted");
                    exit();
                }
            }
        }
    }
}

// Handle Add/Edit Action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_pengguna = isset($_POST['id_pengguna']) ? (int)$_POST['id_pengguna'] : 0;
    $username     = trim($_POST['username']);
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $email        = trim($_POST['email']);
    $no_hp        = trim($_POST['no_hp']);
    $peran        = $_POST['peran'];
    $password     = $_POST['password'];

    if ($id_pengguna > 0) {
        // Edit Mode
        if (!empty($password)) {
            $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $upd = $conn->prepare("UPDATE pengguna SET username=?, nama_lengkap=?, email=?, no_hp=?, kata_sandi=?, peran=? WHERE id_pengguna=?");
            $upd->bind_param("ssssssi", $username, $nama_lengkap, $email, $no_hp, $hashed, $peran, $id_pengguna);
        } else {
            $upd = $conn->prepare("UPDATE pengguna SET username=?, nama_lengkap=?, email=?, no_hp=?, peran=? WHERE id_pengguna=?");
            $upd->bind_param("sssssi", $username, $nama_lengkap, $email, $no_hp, $peran, $id_pengguna);
        }
        
        if ($upd->execute()) {
            $success_msg = "Data pengguna berhasil diperbarui!";
        } else {
            $error_msg = "Gagal memperbarui data: " . $conn->error;
        }
    } else {
        // Add Mode
        $cek = $conn->prepare("SELECT id_pengguna FROM pengguna WHERE username = ? OR email = ?");
        $cek->bind_param("ss", $username, $email);
        $cek->execute();
        if ($cek->get_result()->num_rows > 0) {
            $error_msg = "Username atau email sudah terdaftar.";
        } else {
            $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $ins = $conn->prepare("INSERT INTO pengguna (username, nama_lengkap, email, no_hp, kata_sandi, peran) VALUES (?, ?, ?, ?, ?, ?)");
            $ins->bind_param("ssssss", $username, $nama_lengkap, $email, $no_hp, $hashed, $peran);
            if ($ins->execute()) {
                $success_msg = "Pengguna baru berhasil ditambahkan!";
            } else {
                $error_msg = "Gagal menambahkan pengguna: " . $conn->error;
            }
        }
    }
}

$msg = $_GET['msg'] ?? '';
if($msg == 'deleted') $success_msg = "Pengguna berhasil dihapus.";

// Get users (kata_sandi TIDAK diambil agar tidak terekspos ke browser)
$sql = "SELECT id_pengguna, username, nama_lengkap, email, no_hp, peran, dibuat_pada FROM pengguna ORDER BY dibuat_pada DESC";
$users_list = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pengguna - Admin Berkah Malika</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Poppins', sans-serif; }
        body { background: #f4f6fb; }
        .sidebar { width: 250px; position: fixed; top: 0; left: 0; height: 100vh; background: linear-gradient(180deg, #1e293b, #0f172a); z-index: 100; overflow-y: auto; }
        .sidebar-brand { padding: 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .nav-link-sidebar { color: rgba(255,255,255,0.7); padding: 0.75rem 1.5rem; display: flex; align-items: center; gap: 0.75rem; border-radius: 0; transition: all 0.2s; text-decoration: none; font-size: 0.9rem; }
        .nav-link-sidebar:hover, .nav-link-sidebar.active { background: rgba(255,255,255,0.1); color: white; }
        .nav-link-sidebar i { width: 20px; text-align: center; }
        .main-content { margin-left: 250px; padding: 2rem; }
        .page-header { background: linear-gradient(135deg, #4f46e5, #3730a3); border-radius: 16px; padding: 1.5rem; color: white; margin-bottom: 2rem; }
        .table-card { background: white; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.06); overflow: hidden; }
        .table th { background: #f8f9fa; font-weight: 600; font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; border: 0; padding: 1rem; }
        .table td { padding: 1rem; vertical-align: middle; border-color: #f0f0f0; font-size: 0.88rem; }
        .avatar-sm { width: 38px; height: 38px; border-radius: 50%; background: #eef2ff; color: #4f46e5; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem; }
        .badge-admin { background: #fee2e2; color: #dc2626; border-radius: 50px; padding: 0.3rem 0.8rem; font-size: 0.75rem; font-weight: 600; }
        .badge-penyewa { background: #d1fae5; color: #059669; border-radius: 50px; padding: 0.3rem 0.8rem; font-size: 0.75rem; font-weight: 600; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <div class="text-white fw-bold"><i class="fas fa-home me-2 text-indigo-400"></i>Berkah Malika</div>
            <small class="text-white-50 small">Panel Admin</small>
        </div>
        <nav class="mt-2">
            <a href="index.php" class="nav-link-sidebar"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="rooms.php" class="nav-link-sidebar"><i class="fas fa-bed"></i> Kelola Kamar</a>
            <a href="bookings.php" class="nav-link-sidebar"><i class="fas fa-calendar-check"></i> Reservasi</a>
            <a href="penyewa.php" class="nav-link-sidebar"><i class="fas fa-users"></i> Data Penyewa</a>
            <a href="pembayaran.php" class="nav-link-sidebar"><i class="fas fa-credit-card"></i> Pembayaran</a>
            <a href="notifikasi.php" class="nav-link-sidebar"><i class="fas fa-bell"></i> Notifikasi</a>
            <a href="laporan.php" class="nav-link-sidebar"><i class="fas fa-chart-bar"></i> Laporan</a>
            <a href="users.php" class="nav-link-sidebar active"><i class="fas fa-user-cog"></i> Pengguna</a>
            <hr style="border-color:rgba(255,255,255,0.1)">
            <a href="../logout.php" class="nav-link-sidebar text-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="page-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-1"><i class="fas fa-user-cog me-2"></i>Manajemen Pengguna</h4>
                <p class="opacity-75 mb-0 small">Kelola akun admin dan penyewa</p>
            </div>
            <button class="btn btn-white bg-white text-indigo-600 fw-600 rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#userModal" onclick="resetForm()">
                <i class="fas fa-plus me-2"></i>Tambah User
            </button>
        </div>

        <?php if ($success_msg): ?>
            <div class="alert alert-success rounded-3 border-0 shadow-sm mb-4"><i class="fas fa-check-circle me-2"></i><?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
            <div class="alert alert-danger rounded-3 border-0 shadow-sm mb-4"><i class="fas fa-exclamation-circle me-2"></i><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <div class="table-card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Pengguna</th>
                            <th>Kontak & Email</th>
                            <th>Peran</th>
                            <th>Terdaftar</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users_list->num_rows > 0): while($row = $users_list->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="avatar-sm"><?php echo strtoupper(substr($row['username'], 0, 1)); ?></div>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($row['nama_lengkap']); ?></div>
                                        <div class="text-muted small">@<?php echo htmlspecialchars($row['username']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="small fw-bold text-dark"><i class="fas fa-envelope me-1 text-muted"></i> <?php echo htmlspecialchars($row['email']); ?></div>
                                <div class="small text-muted"><i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($row['no_hp'] ?: '-'); ?></div>
                            </td>
                            <td>
                                <span class="badge <?php echo $row['peran'] == 'admin' ? 'badge-admin' : 'badge-penyewa'; ?>">
                                    <?php echo ucfirst($row['peran']); ?>
                                </span>
                            </td>
                            <td><small class="text-muted"><?php echo date('d M Y', strtotime($row['dibuat_pada'])); ?></small></td>
                            <td class="text-end">
                                <button class="btn btn-light btn-sm rounded-pill px-3 me-1" onclick='editUser(<?php echo json_encode([
                                        "id_pengguna" => $row["id_pengguna"],
                                        "username"    => $row["username"],
                                        "nama_lengkap"=> $row["nama_lengkap"],
                                        "email"       => $row["email"],
                                        "no_hp"       => $row["no_hp"],
                                        "peran"       => $row["peran"]
                                    ]); ?>)'>
                                    <i class="fas fa-edit me-1"></i> Edit
                                </button>
                                <?php if ($row['id_pengguna'] != $_SESSION['user_id'] && $row['peran'] != 'admin'): ?>
                                <a href="users.php?action=delete&id=<?php echo $row['id_pengguna']; ?>" class="btn btn-outline-danger btn-sm rounded-pill px-3" onclick="return confirm('Yakin ingin menghapus user ini?')">
                                    <i class="fas fa-trash me-1"></i> Hapus
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="5" class="text-center py-5">Belum ada data pengguna.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Add/Edit User -->
    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow" style="border-radius:20px overflow:hidden">
                <div class="modal-header bg-light border-0 px-4 py-3">
                    <h5 class="modal-title fw-bold" id="modalTitle">Tambah Pengguna Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="id_pengguna" id="uid">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Username</label>
                                <input type="text" name="username" id="u_username" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Peran</label>
                                <select name="peran" id="u_peran" class="form-select form-select-sm" required>
                                    <option value="penyewa">Penyewa</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">Nama Lengkap</label>
                                <input type="text" name="nama_lengkap" id="u_nama" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Email</label>
                                <input type="email" name="email" id="u_email" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">No HP</label>
                                <input type="text" name="no_hp" id="u_nohp" class="form-control form-control-sm">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">Password</label>
                                <input type="password" name="password" id="u_pass" class="form-control form-control-sm" placeholder="Kosongkan jika tidak ingin diubah (Edit)">
                                <small class="text-muted" id="passNote">* Wajib diisi untuk tambah user baru.</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function resetForm() {
            document.getElementById('modalTitle').innerText = 'Tambah Pengguna Baru';
            document.getElementById('uid').value = '';
            document.getElementById('u_username').value = '';
            document.getElementById('u_nama').value = '';
            document.getElementById('u_email').value = '';
            document.getElementById('u_nohp').value = '';
            document.getElementById('u_peran').value = 'penyewa';
            document.getElementById('u_pass').required = true;
            document.getElementById('passNote').innerText = '* Wajib diisi untuk tambah user baru.';
        }

        function editUser(data) {
            document.getElementById('modalTitle').innerText = 'Edit Pengguna';
            document.getElementById('uid').value = data.id_pengguna;
            document.getElementById('u_username').value = data.username;
            document.getElementById('u_nama').value = data.nama_lengkap;
            document.getElementById('u_email').value = data.email;
            document.getElementById('u_nohp').value = data.no_hp;
            document.getElementById('u_peran').value = data.peran;
            document.getElementById('u_pass').required = false;
            document.getElementById('passNote').innerText = '* Kosongkan jika tidak ingin mengubah password.';
            
            var modal = new bootstrap.Modal(document.getElementById('userModal'));
            modal.show();
        }
    </script>
</body>
</html>
