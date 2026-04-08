<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
require_once '../config/database.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: penyewa.php");
    exit();
}

// Get tenant detail with all related info
$sql = "SELECT dp.*, p.username, p.nama_lengkap, p.email, p.no_hp, p.foto_profil,
               k.nama_kamar, k.lantai, k.lokasi, k.harga_per_bulan,
               r.tanggal_masuk as res_masuk, r.tanggal_keluar as res_keluar, r.total_harga, r.metode_pembayaran, r.durasi_sewa
        FROM data_penyewa dp
        JOIN pengguna p ON dp.id_pengguna = p.id_pengguna
        JOIN kamar k ON dp.id_kamar = k.id_kamar
        JOIN reservasi r ON dp.id_reservasi = r.id_reservasi
        WHERE dp.id_penyewa = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$tenant = $stmt->get_result()->fetch_assoc();

if (!$tenant) {
    header("Location: penyewa.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Penyewa - Admin Berkah Malika</title>
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
        .page-header { background: linear-gradient(135deg, #10b981, #059669); border-radius: 16px; padding: 1.5rem; color: white; margin-bottom: 2rem; }
        .card-custom { background: white; border-radius: 16px; border: 0; box-shadow: 0 4px 15px rgba(0,0,0,0.06); padding: 2rem; margin-bottom: 1.5rem; }
        .detail-label { font-size: 0.75rem; text-transform: uppercase; color: #9ca3af; font-weight: 600; letter-spacing: 0.5px; margin-bottom: 0.25rem; }
        .detail-value { font-weight: 600; color: #1f2937; margin-bottom: 1.25rem; }
        .badge-status { border-radius: 50px; padding: 0.4rem 1.2rem; font-weight: 600; font-size: 0.85rem; }
        .profile-section { text-align: center; padding-bottom: 1.5rem; border-bottom: 1px solid #f0f0f0; margin-bottom: 1.5rem; }
        .profile-img { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 4px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.1); margin-bottom: 1rem; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <div class="text-white fw-bold"><i class="fas fa-home me-2 text-success"></i>Berkah Malika</div>
            <small class="text-white-50 small">Panel Admin</small>
        </div>
        <nav class="mt-2">
            <a href="index.php" class="nav-link-sidebar"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="rooms.php" class="nav-link-sidebar"><i class="fas fa-bed"></i> Kelola Kamar</a>
            <a href="bookings.php" class="nav-link-sidebar"><i class="fas fa-calendar-check"></i> Reservasi</a>
            <a href="penyewa.php" class="nav-link-sidebar active"><i class="fas fa-users"></i> Data Penyewa</a>
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
                <h4 class="fw-bold mb-1"><i class="fas fa-user-shield me-2"></i>Detail Penyewa</h4>
                <p class="opacity-75 mb-0 small">Informasi lengkap penghuni kos</p>
            </div>
            <a href="penyewa.php" class="btn btn-white bg-white text-success fw-600 rounded-pill px-4 shadow-sm">
                <i class="fas fa-arrow-left me-2"></i>Kembali
            </a>
        </div>

        <div class="row">
            <!-- Info Personal -->
            <div class="col-md-4">
                <div class="card-custom">
                    <div class="profile-section">
                        <?php 
                        $avatar = !empty($tenant['foto_profil']) ? '../public/' . $tenant['foto_profil'] : 'https://ui-avatars.com/api/?name=' . urlencode($tenant['nama_lengkap']) . '&size=120&background=10b981&color=fff&bold=true';
                        ?>
                        <img src="<?php echo $avatar; ?>" class="profile-img" alt="<?php echo $tenant['nama_lengkap']; ?>">
                        <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($tenant['nama_lengkap']); ?></h5>
                        <p class="text-muted small mb-3">@<?php echo htmlspecialchars($tenant['username']); ?></p>
                        <?php if($tenant['status_huni'] == 'aktif'): ?>
                            <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2 border border-success border-opacity-25">Penghuni Aktif</span>
                        <?php else: ?>
                            <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3 py-2 border border-danger border-opacity-25">Sudah Keluar</span>
                        <?php endif; ?>
                    </div>

                    <div class="detail-label">NIK / No. KTP</div>
                    <div class="detail-value"><?php echo htmlspecialchars($tenant['no_ktp'] ?: '-'); ?></div>

                    <div class="detail-label">Email</div>
                    <div class="detail-value text-primary"><?php echo htmlspecialchars($tenant['email']); ?></div>

                    <div class="detail-label">No. WhatsApp</div>
                    <div class="detail-value"><?php echo htmlspecialchars($tenant['no_hp'] ?: '-'); ?></div>

                    <div class="detail-label">Pekerjaan</div>
                    <div class="detail-value"><?php echo htmlspecialchars($tenant['pekerjaan'] ?: '-'); ?></div>

                    <div class="detail-label">Alamat Asal</div>
                    <div class="detail-value small"><?php echo nl2br(htmlspecialchars($tenant['alamat_asal'] ?: '-')); ?></div>
                </div>
            </div>

            <!-- Info Kamar & Reservasi -->
            <div class="col-md-8">
                <div class="card-custom">
                    <h6 class="fw-bold mb-4 border-bottom pb-2"><i class="fas fa-bed me-2 text-success"></i>Informasi Hunian</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="detail-label">Kamar</div>
                            <div class="detail-value text-success"><?php echo htmlspecialchars($tenant['nama_kamar']); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-label">Lantai</div>
                            <div class="detail-value"><?php echo $tenant['lantai']; ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-label">Lokasi</div>
                            <div class="detail-value small"><?php echo htmlspecialchars($tenant['lokasi']); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-label">Harga per Bulan</div>
                            <div class="detail-value">Rp <?php echo number_format($tenant['harga_per_bulan'], 0, ',', '.'); ?></div>
                        </div>
                    </div>
                </div>

                <div class="card-custom">
                    <h6 class="fw-bold mb-4 border-bottom pb-2"><i class="fas fa-calendar-alt me-2 text-primary"></i>Informasi Kontrak / Reservasi</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="detail-label">Tanggal Masuk</div>
                            <div class="detail-value"><?php echo date('d F Y', strtotime($tenant['tanggal_masuk'])); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-label">Tanggal Keluar (Estimasi)</div>
                            <div class="detail-value"><?php echo date('d F Y', strtotime($tenant['tanggal_keluar'])); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-label">Durasi Sewa</div>
                            <div class="detail-value text-primary"><?php echo $tenant['durasi_sewa']; ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-label">Metode Pembayaran</div>
                            <div class="detail-value"><span class="badge bg-light text-dark border"><?php echo $tenant['metode_pembayaran']; ?></span></div>
                        </div>
                        <div class="col-md-12">
                            <div class="detail-label">Total Pembayaran (Booking)</div>
                            <div class="detail-value fw-bold text-success" style="font-size: 1.2rem;">Rp <?php echo number_format($tenant['total_harga'], 0, ',', '.'); ?></div>
                        </div>
                        <?php if(!empty($tenant['catatan'])): ?>
                        <div class="col-md-12">
                            <div class="detail-label">Catatan Tambahan</div>
                            <div class="detail-value p-3 bg-light rounded-4 border-start border-4 border-success">
                                <?php echo nl2br(htmlspecialchars($tenant['catatan'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="alert alert-info border-0 rounded-4">
                    <i class="fas fa-info-circle me-2"></i>
                    Data di atas adalah ringkasan dari profil pengguna dan riwayat reservasi yang mengaktifkan data penyewa ini.
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
