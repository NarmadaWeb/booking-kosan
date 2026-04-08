<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../admin_login.php");
    exit();
}
require_once '../config/database.php';

$message = '';
$success = '';

// Send notification to user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kirim_notifikasi'])) {
    $id_pengguna = (int)$_POST['id_pengguna'];
    $judul       = trim($_POST['judul']);
    $pesan       = trim($_POST['pesan']);
    $jenis       = $_POST['jenis'];
    
    $stmt = $conn->prepare("INSERT INTO notifikasi (id_pengguna, judul, pesan, jenis, status_baca) VALUES (?, ?, ?, ?, 'belum dibaca')");
    $stmt->bind_param("isss", $id_pengguna, $judul, $pesan, $jenis);
    if ($stmt->execute()) {
        $success = "Notifikasi berhasil dikirim!";
    } else {
        $message = "Gagal mengirim notifikasi.";
    }
}

// Send to all
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kirim_semua'])) {
    $judul = trim($_POST['judul_all']);
    $pesan = trim($_POST['pesan_all']);
    $jenis = $_POST['jenis_all'];
    
    $users_all = $conn->query("SELECT id_pengguna FROM pengguna WHERE peran = 'penyewa'");
    $count = 0;
    while ($u = $users_all->fetch_assoc()) {
        $stmt = $conn->prepare("INSERT INTO notifikasi (id_pengguna, judul, pesan, jenis, status_baca) VALUES (?, ?, ?, ?, 'belum dibaca')");
        $stmt->bind_param("isss", $u['id_pengguna'], $judul, $pesan, $jenis);
        $stmt->execute();
        $count++;
    }
    $success = "Notifikasi berhasil dikirim ke $count pengguna.";
}

// Delete
if (isset($_GET['delete'])) {
    $nid = (int)$_GET['delete'];
    $conn->query("DELETE FROM notifikasi WHERE id_notifikasi = $nid");
    header("Location: notifikasi.php");
    exit();
}

// Get all notifications
$notifikasi_list = $conn->query("SELECT n.*, p.username, p.nama_lengkap FROM notifikasi n JOIN pengguna p ON n.id_pengguna = p.id_pengguna ORDER BY n.dibuat_pada DESC LIMIT 100");
$pengguna_list   = $conn->query("SELECT id_pengguna, username, nama_lengkap FROM pengguna WHERE peran = 'penyewa' ORDER BY nama_lengkap");

$total_notif   = $conn->query("SELECT COUNT(*) as c FROM notifikasi")->fetch_assoc()['c'];
$total_unread  = $conn->query("SELECT COUNT(*) as c FROM notifikasi WHERE status_baca = 'belum dibaca'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifikasi - Admin Berkah Malika</title>
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
        .page-header { background: linear-gradient(135deg, #f59e0b, #d97706); border-radius: 16px; padding: 1.5rem; color: white; margin-bottom: 2rem; }
        .card-custom { background: white; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.06); overflow: hidden; }
        .form-control, .form-select, .form-control { border-radius: 10px; border: 2px solid #e9ecef; padding: 0.65rem 1rem; transition: border-color 0.2s; }
        .form-control:focus, .form-select:focus { border-color: #f59e0b; box-shadow: 0 0 0 4px rgba(245,158,11,0.15); }
        .form-label { font-weight: 500; font-size: 0.85rem; color: #555; }
        .table th { background: #f8f9fa; font-weight: 600; font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; border: 0; padding: 1rem; }
        .table td { padding: 0.85rem 1rem; vertical-align: middle; border-color: #f0f0f0; font-size: 0.85rem; }
        .badge-baru   { background: #dbeafe; color: #1d4ed8; border-radius: 50px; padding: 0.25rem 0.75rem; font-size: 0.75rem; font-weight: 600; }
        .badge-dibaca { background: #f0f0f0; color: #6c757d; border-radius: 50px; padding: 0.25rem 0.75rem; font-size: 0.75rem; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <div class="text-white fw-bold"><i class="fas fa-home me-2 text-yellow-400"></i>Berkah Malika</div>
            <small class="text-white-50 small">Panel Admin</small>
        </div>
        <nav class="mt-2">
            <a href="index.php" class="nav-link-sidebar"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="rooms.php" class="nav-link-sidebar"><i class="fas fa-bed"></i> Kelola Kamar</a>
            <a href="bookings.php" class="nav-link-sidebar"><i class="fas fa-calendar-check"></i> Reservasi</a>
            <a href="penyewa.php" class="nav-link-sidebar"><i class="fas fa-users"></i> Data Penyewa</a>
            <a href="pembayaran.php" class="nav-link-sidebar"><i class="fas fa-credit-card"></i> Pembayaran</a>
            <a href="notifikasi.php" class="nav-link-sidebar active"><i class="fas fa-bell"></i> Notifikasi</a>
            <a href="laporan.php" class="nav-link-sidebar"><i class="fas fa-chart-bar"></i> Laporan</a>
            <a href="users.php" class="nav-link-sidebar"><i class="fas fa-user-cog"></i> Pengguna</a>
            <hr style="border-color:rgba(255,255,255,0.1)">
            <a href="../logout.php" class="nav-link-sidebar text-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="page-header">
            <h4 class="fw-bold mb-1"><i class="fas fa-bell me-2"></i>Manajemen Notifikasi</h4>
            <p class="opacity-75 mb-0 small"><?php echo $total_notif; ?> total &bull; <?php echo $total_unread; ?> belum dibaca</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success rounded-3 border-0"><i class="fas fa-check-circle me-2"></i><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($message): ?>
            <div class="alert alert-danger rounded-3 border-0"><i class="fas fa-exclamation-circle me-2"></i><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Send Form -->
            <div class="col-lg-4">
                <div class="card-custom">
                    <div class="p-3 border-bottom bg-light">
                        <h6 class="fw-bold mb-0"><i class="fas fa-paper-plane me-2 text-warning"></i>Kirim Notifikasi</h6>
                    </div>
                    <div class="p-4">
                        <!-- Personal -->
                        <h6 class="small fw-bold text-muted text-uppercase mb-2">Ke Pengguna Tertentu</h6>
                        <form method="POST" class="mb-4">
                            <input type="hidden" name="kirim_notifikasi" value="1">
                            <div class="mb-2">
                                <label class="form-label">Pilih Pengguna</label>
                                <select name="id_pengguna" class="form-select form-select-sm" required>
                                    <option value="">-- Pilih --</option>
                                    <?php while ($u = $pengguna_list->fetch_assoc()): ?>
                                    <option value="<?php echo $u['id_pengguna']; ?>"><?php echo htmlspecialchars($u['nama_lengkap']); ?> (@<?php echo $u['username']; ?>)</option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Jenis</label>
                                <select name="jenis" class="form-select form-select-sm">
                                    <option value="info">Info</option>
                                    <option value="reservasi">Reservasi</option>
                                    <option value="pembayaran">Pembayaran</option>
                                    <option value="sistem">Sistem</option>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Judul</label>
                                <input type="text" name="judul" class="form-control form-control-sm" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Pesan</label>
                                <textarea name="pesan" class="form-control" rows="3" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-warning text-white w-100 rounded-pill fw-600 btn-sm">
                                <i class="fas fa-paper-plane me-2"></i>Kirim Notifikasi
                            </button>
                        </form>

                        <hr>

                        <!-- Broadcast -->
                        <h6 class="small fw-bold text-muted text-uppercase mb-2">Broadcast Semua Penyewa</h6>
                        <form method="POST">
                            <input type="hidden" name="kirim_semua" value="1">
                            <div class="mb-2">
                                <label class="form-label">Jenis</label>
                                <select name="jenis_all" class="form-select form-select-sm">
                                    <option value="info">Info</option>
                                    <option value="sistem">Sistem</option>
                                    <option value="pembayaran">Pembayaran</option>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Judul</label>
                                <input type="text" name="judul_all" class="form-control form-control-sm" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Pesan</label>
                                <textarea name="pesan_all" class="form-control" rows="3" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 rounded-pill btn-sm" onclick="return confirm('Kirim ke semua penyewa?')">
                                <i class="fas fa-broadcast-tower me-2"></i>Broadcast
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Notification List -->
            <div class="col-lg-8">
                <div class="card-custom">
                    <div class="p-3 border-bottom">
                        <h6 class="fw-bold mb-0">Riwayat Notifikasi</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Pengguna</th>
                                    <th>Notifikasi</th>
                                    <th>Jenis</th>
                                    <th>Status</th>
                                    <th>Waktu</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($notifikasi_list && $notifikasi_list->num_rows > 0): while ($n = $notifikasi_list->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold small"><?php echo htmlspecialchars($n['nama_lengkap']); ?></div>
                                        <small class="text-muted">@<?php echo $n['username']; ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-bold small"><?php echo htmlspecialchars($n['judul']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars(substr($n['pesan'], 0, 60)); ?>...</small>
                                    </td>
                                    <td><span class="badge bg-light text-dark border"><?php echo $n['jenis']; ?></span></td>
                                    <td>
                                        <?php if ($n['status_baca'] === 'belum dibaca'): ?>
                                        <span class="badge-baru">Belum Dibaca</span>
                                        <?php else: ?>
                                        <span class="badge-dibaca">Dibaca</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($n['dibuat_pada'])); ?></small></td>
                                    <td>
                                        <a href="notifikasi.php?delete=<?php echo $n['id_notifikasi']; ?>" class="btn btn-outline-danger btn-sm rounded-pill px-2" onclick="return confirm('Hapus notifikasi?')"><i class="fas fa-trash"></i></a>
                                    </td>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">Belum ada notifikasi</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
