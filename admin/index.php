<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
require_once '../config/database.php';

// Stats logic
$today = date('Y-m-d');
$rooms_count = $conn->query("SELECT COUNT(*) as c FROM kamar")->fetch_assoc()['c'] ?? 0;
$active_tenants = $conn->query("SELECT COUNT(*) as c FROM reservasi WHERE status_reservasi='Dikonfirmasi' AND tanggal_masuk <= '$today' AND tanggal_keluar > '$today'")->fetch_assoc()['c'] ?? 0;
$pending_bookings = $conn->query("SELECT COUNT(*) as c FROM reservasi WHERE status_reservasi='Menunggu'")->fetch_assoc()['c'] ?? 0;
$total_income = $conn->query("SELECT SUM(total_harga) as t FROM reservasi WHERE status_reservasi='Dikonfirmasi'")->fetch_assoc()['t'] ?? 0;

// Recent Bookings
$recent_bookings = $conn->query("SELECT r.*, k.nama_kamar FROM reservasi r JOIN kamar k ON r.id_kamar = k.id_kamar ORDER BY r.id_reservasi DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Berkah Malika</title>
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
        .page-header { background: linear-gradient(135deg, #4f46e5, #3730a3); border-radius: 16px; padding: 2rem; color: white; margin-bottom: 2rem; position: relative; overflow: hidden; }
        .header-circles { position: absolute; right: -20px; top: -20px; width: 150px; height: 150px; background: rgba(255,255,255,0.1); border-radius: 50%; }
        .stat-card { background: white; border-radius: 16px; padding: 1.5rem; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 0; transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .icon-box { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 1rem; }
        .table-card { background: white; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); overflow: hidden; }
        .table thead th { background: #f8f9fa; font-weight: 600; font-size: 0.8rem; text-transform: uppercase; padding: 1rem; border: 0; }
        .table tbody td { padding: 1rem; border-color: #f0f0f0; vertical-align: middle; font-size: 0.85rem; }
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
            <a href="index.php" class="nav-link-sidebar active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="rooms.php" class="nav-link-sidebar"><i class="fas fa-bed"></i> Kelola Kamar</a>
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
        <div class="page-header shadow">
            <div class="header-circles"></div>
            <h3 class="fw-bold mb-1 font-poppins">Halo, Admin Berkah Malika! 👋</h3>
            <p class="opacity-75 mb-0">Berikut adalah ringkasan sistem hari ini, <?php echo date('d F Y'); ?>.</p>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon-box bg-indigo-100 text-indigo-600"><i class="fas fa-bed"></i></div>
                    <div class="text-muted small fw-bold text-uppercase">Total Kamar</div>
                    <div class="h3 fw-bold mb-0"><?php echo $rooms_count; ?></div>
                    <small class="text-success fw-bold">Unit Terdaftar</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon-box bg-green-100 text-green-600"><i class="fas fa-user-check"></i></div>
                    <div class="text-muted small fw-bold text-uppercase">Penghuni Aktif</div>
                    <div class="h3 fw-bold mb-0"><?php echo $active_tenants; ?></div>
                    <small class="text-muted">Penyewa Berjalan</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon-box bg-orange-100 text-orange-600"><i class="fas fa-clock"></i></div>
                    <div class="text-muted small fw-bold text-uppercase">Pesanan Pending</div>
                    <div class="h3 fw-bold mb-0"><?php echo $pending_bookings; ?></div>
                    <small class="text-orange-600 fw-bold">Perlu Konfirmasi</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon-box bg-purple-100 text-purple-600"><i class="fas fa-wallet"></i></div>
                    <div class="text-muted small fw-bold text-uppercase">Total Pendapatan</div>
                    <div class="h4 fw-bold mb-0">Rp <?php echo number_format($total_income, 0, ',', '.'); ?></div>
                    <small class="text-muted">Dari Booking Selesai</small>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-8">
                <div class="table-card">
                    <div class="p-4 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0"><i class="fas fa-history me-2 text-indigo-600"></i>Reservasi Terbaru</h6>
                        <a href="bookings.php" class="btn btn-sm btn-link text-decoration-none">Lihat Semua</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Unit</th>
                                    <th>Penyewa</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($recent_bookings && $recent_bookings->num_rows > 0): 
                                    while($rb = $recent_bookings->fetch_assoc()): 
                                        $s = strtolower($rb['status_reservasi']);
                                        $badge = "bg-warning";
                                        if($s == 'dikonfirmasi') $badge = "bg-success";
                                        if($s == 'dibatalkan') $badge = "bg-danger";
                                        if($s == 'selesai') $badge = "bg-secondary";
                                ?>
                                <tr>
                                    <td class="fw-bold"><?php echo $rb['nama_kamar']; ?></td>
                                    <td><?php echo $rb['nama_pemesan']; ?></td>
                                    <td class="text-indigo-600 fw-bold">Rp <?php echo number_format($rb['total_harga']); ?></td>
                                    <td><span class="badge <?php echo $badge; ?> rounded-pill px-3"><?php echo $rb['status_reservasi']; ?></span></td>
                                    <td class="text-end">
                                        <a href="edit_booking.php?id=<?php echo $rb['id_reservasi']; ?>" class="btn btn-sm btn-light rounded-pill"><i class="fas fa-eye"></i></a>
                                    </td>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr><td colspan="5" class="text-center py-4">Belum ada pesanan terbaru.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card h-100 d-flex flex-column justify-content-center align-items-center text-center p-5">
                    <div class="bg-indigo-600 text-white rounded-circle mb-4 d-flex align-items-center justify-content-center" style="width:80px; height:80px; font-size:2rem">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h5 class="fw-bold">Keamanan Sistem</h5>
                    <p class="text-muted small">Panel admin menggunakan enkripsi password BCRYPT v12 dan session-based authentication.</p>
                    <a href="users.php" class="btn btn-outline-indigo rounded-pill px-4 btn-sm">Kelola Akses</a>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .bg-indigo-100 { background-color: #e0e7ff; }
        .text-indigo-600 { color: #4f46e5; }
        .bg-green-100 { background-color: #dcfce7; }
        .text-green-600 { color: #16a34a; }
        .bg-orange-100 { background-color: #ffedd5; }
        .text-orange-600 { color: #ea580c; }
        .bg-purple-100 { background-color: #f3e8ff; }
        .text-purple-600 { color: #9333ea; }
        .btn-outline-indigo { color: #4f46e5; border-color: #4f46e5; }
        .btn-outline-indigo:hover { background-color: #4f46e5; color: white; }
    </style>
</body>
</html>
