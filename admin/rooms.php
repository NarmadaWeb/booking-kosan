<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
require_once '../config/database.php';

// Handle Deletion
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM kamar WHERE id_kamar = $id");
    header("Location: rooms.php?msg=deleted");
    exit();
}

$success_msg = '';
if (isset($_GET['msg']) && $_GET['msg'] == 'deleted') $success_msg = "Kamar berhasil dihapus.";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kamar - Admin Berkah Malika</title>
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
        .room-img { width: 70px; height: 70px; border-radius: 12px; object-fit: cover; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .status-badge { border-radius: 50px; padding: 0.3rem 0.8rem; font-size: 0.75rem; font-weight: 600; }
        .status-tersedia { background: #d1fae5; color: #059669; }
        .status-terisi { background: #fee2e2; color: #dc2626; }
        .status-perbaikan { background: #fef3c7; color: #d97706; }
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
            <a href="rooms.php" class="nav-link-sidebar active"><i class="fas fa-bed"></i> Kelola Kamar</a>
            <a href="bookings.php" class="nav-link-sidebar"><i class="fas fa-calendar-check"></i> Reservasi</a>
            <a href="penyewa.php" class="nav-link-sidebar"><i class="fas fa-users"></i> Data Penyewa</a>
            <a href="pembayaran.php" class="nav-link-sidebar"><i class="fas fa-credit-card"></i> Pembayaran</a>
            <a href="notifikasi.php" class="nav-link-sidebar"><i class="fas fa-bell"></i> Notifikasi</a>
            <a href="laporan.php" class="nav-link-sidebar"><i class="fas fa-chart-bar"></i> Laporan</a>
            <a href="users.php" class="nav-link-sidebar"><i class="fas fa-user-cog"></i> Pengguna</a>
            <hr style="border-color:rgba(255,255,255,0.1)">
            <a href="../logout.php" class="nav-link-sidebar text-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="page-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-1"><i class="fas fa-bed me-2"></i>Kelola Kamar Kost</h4>
                <p class="opacity-75 mb-0 small">Daftar unit kamar dan status ketersediaan</p>
            </div>
            <a href="add_room.php" class="btn btn-white bg-white text-indigo-600 fw-600 rounded-pill px-4 shadow-sm">
                <i class="fas fa-plus me-2"></i>Tambah Kamar
            </a>
        </div>

        <?php if ($success_msg): ?>
            <div class="alert alert-success rounded-3 border-0 shadow-sm mb-4"><i class="fas fa-check-circle me-2"></i><?php echo $success_msg; ?></div>
        <?php endif; ?>

        <div class="table-card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Kamar</th>
                            <th>Lantai</th>
                            <th>Harga (Bulan)</th>
                            <th>Harga (Tahun)</th>
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $result = $conn->query("SELECT * FROM kamar ORDER BY id_kamar DESC");
                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                $status_text = 'tersedia';
                                $statusCls = 'status-tersedia';
                                
                                if ($row['status_kamar'] == 'perbaikan') {
                                    $status_text = 'perbaikan';
                                    $statusCls = 'status-perbaikan';
                                } else {
                                    // Check for active confirmed reservation
                                    $stmt_res = $conn->prepare("SELECT id_reservasi FROM reservasi WHERE id_kamar = ? AND status_reservasi = 'Dikonfirmasi' AND CURDATE() BETWEEN tanggal_masuk AND tanggal_keluar LIMIT 1");
                                    $stmt_res->bind_param("i", $row['id_kamar']);
                                    $stmt_res->execute();
                                    if ($stmt_res->get_result()->num_rows > 0) {
                                        $status_text = 'terisi';
                                        $statusCls = 'status-terisi';
                                    }
                                }
                                
                                echo "<tr>";
                                echo "<td>
                                        <div class='d-flex align-items-center gap-3'>
                                            <img src='../public/{$row['foto_utama']}' class='room-img' onerror='this.src=\"https://via.placeholder.com/70\"'>
                                            <div>
                                                <div class='fw-bold'>{$row['nama_kamar']}</div>
                                                <div class='text-muted small'><i class='fas fa-map-marker-alt me-1'></i>{$row['lokasi']}</div>
                                            </div>
                                        </div>
                                      </td>";
                                echo "<td><span class='badge bg-light text-dark border'>Lantai {$row['lantai']}</span></td>";
                                echo "<td><div class='fw-bold text-indigo-600'>Rp " . number_format($row['harga_per_bulan']) . "</div></td>";
                                echo "<td><div class='text-muted'>Rp " . number_format($row['harga_per_tahun']) . "</div></td>";
                                echo "<td><span class='status-badge {$statusCls}'>" . ucfirst($status_text) . "</span></td>";
                                echo "<td class='text-end'>
                                        <div class='dropdown'>
                                            <button class='btn btn-light btn-sm rounded-pill' data-bs-toggle='dropdown'><i class='fas fa-ellipsis-v'></i></button>
                                            <ul class='dropdown-menu dropdown-menu-end border-0 shadow'>
                                                <li><a class='dropdown-item' href='edit_room.php?id={$row['id_kamar']}'><i class='fas fa-edit me-2 text-warning'></i>Edit Kamar</a></li>
                                                <li><hr class='dropdown-divider'></li>
                                                <li><a class='dropdown-item text-danger' href='rooms.php?delete={$row['id_kamar']}' onclick='return confirm(\"Apakah Anda yakin ingin menghapus kamar ini?\")'><i class='fas fa-trash me-2'></i>Hapus</a></li>
                                            </ul>
                                        </div>
                                      </td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='6' class='text-center py-5 text-muted'><i class='fas fa-box-open fa-3x mb-3 d-block opacity-25'></i>Belum ada kamar kost.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
