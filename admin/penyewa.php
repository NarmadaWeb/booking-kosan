<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
require_once '../config/database.php';

// Handle actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id     = (int)$_GET['id'];
    $action = $_GET['action'];
    
    if ($action === 'aktif') {
        // Aktifkan penyewa dan update kamar + reservasi
        $res_query = $conn->query("SELECT id_reservasi, id_kamar FROM data_penyewa WHERE id_penyewa = $id");
        if ($res_query && $res_query->num_rows > 0) {
            $row = $res_query->fetch_assoc();
            $rid = $row['id_reservasi'];
            $kid = $row['id_kamar'];
            // Tandai penyewa aktif
            $conn->query("UPDATE data_penyewa SET status_huni = 'aktif' WHERE id_penyewa = $id");
            // Tandai kamar terisi
            $conn->query("UPDATE kamar SET status_kamar = 'terisi' WHERE id_kamar = $kid");
            // Kembalikan status reservasi ke Dikonfirmasi
            $conn->query("UPDATE reservasi SET status_reservasi = 'Dikonfirmasi' WHERE id_reservasi = $rid");
        }
    } elseif ($action === 'keluar') {
        // Tandai keluar dan bebaskan kamar
        $res_query = $conn->query("SELECT id_reservasi, id_kamar FROM data_penyewa WHERE id_penyewa = $id");
        if ($res_query && $res_query->num_rows > 0) {
            $row = $res_query->fetch_assoc();
            $rid = $row['id_reservasi'];
            $kid = $row['id_kamar'];
            // Tandai penyewa sudah keluar
            $conn->query("UPDATE data_penyewa SET status_huni = 'sudah keluar' WHERE id_penyewa = $id");
            // Bebaskan kamar
            $conn->query("UPDATE kamar SET status_kamar = 'tersedia' WHERE id_kamar = $kid");
            // Selesaikan reservasi
            $conn->query("UPDATE reservasi SET status_reservasi = 'Selesai' WHERE id_reservasi = $rid");
        } else {
            $conn->query("UPDATE data_penyewa SET status_huni = 'sudah keluar' WHERE id_penyewa = $id");
        }
    } elseif ($action === 'checkout') {
        // Advanced checkout: update all linked statuses
        $res_query = $conn->query("SELECT id_reservasi, id_kamar FROM data_penyewa WHERE id_penyewa = $id");
        if ($res_query && $res_query->num_rows > 0) {
            $row = $res_query->fetch_assoc();
            $rid = $row['id_reservasi'];
            $kid = $row['id_kamar'];
            
            // Mark tenant as out
            $conn->query("UPDATE data_penyewa SET status_huni = 'sudah keluar' WHERE id_penyewa = $id");
            
            // Mark reservation as finished and update checkout date to today
            $conn->query("UPDATE reservasi SET status_reservasi = 'Selesai', tanggal_keluar = CURDATE() WHERE id_reservasi = $rid");
            
            // Free the room
            $conn->query("UPDATE kamar SET status_kamar = 'tersedia' WHERE id_kamar = $kid");
        }
    }
    // The delete action is handled in a separate block below to allow for more complex logic before redirect
    if ($action !== 'delete') {
        header("Location: penyewa.php?msg=checkout_success");
        exit();
    }
}

// Handle delete
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    // Get reservation ID before deleting tenant
    $res_id_query = $conn->query("SELECT id_reservasi, id_kamar FROM data_penyewa WHERE id_penyewa = $id");
    if ($res_id_query && $res_id_query->num_rows > 0) {
        $row = $res_id_query->fetch_assoc();
        $rid = $row['id_reservasi'];
        $kid = $row['id_kamar'];
        
        // Update reservation status so room becomes available
        $conn->query("UPDATE reservasi SET status_reservasi = 'Selesai' WHERE id_reservasi = $rid");
        
        // Also update the static status_kamar for backward compatibility (in case some old code still uses it)
        $conn->query("UPDATE kamar SET status_kamar = 'tersedia' WHERE id_kamar = $kid");
    }
    
    $conn->query("DELETE FROM data_penyewa WHERE id_penyewa = $id");
    header("Location: penyewa.php");
    exit();
}

// Filter
$filter = $_GET['filter'] ?? 'aktif';
$where  = $filter === 'all' ? '' : "WHERE dp.status_huni = '" . ($filter === 'keluar' ? 'sudah keluar' : 'aktif') . "'";

$sql = "SELECT dp.*, p.username, p.nama_lengkap, p.email, p.no_hp, k.nama_kamar, k.lantai,
               r.tanggal_masuk, r.tanggal_keluar, r.metode_pembayaran, r.total_harga
        FROM data_penyewa dp
        JOIN pengguna p ON dp.id_pengguna = p.id_pengguna
        JOIN kamar k ON dp.id_kamar = k.id_kamar
        JOIN reservasi r ON dp.id_reservasi = r.id_reservasi
        $where
        ORDER BY dp.dibuat_pada DESC";
$penyewa_list = $conn->query($sql);

$total_aktif  = $conn->query("SELECT COUNT(*) as c FROM data_penyewa WHERE status_huni='aktif'")->fetch_assoc()['c'];
$total_keluar = $conn->query("SELECT COUNT(*) as c FROM data_penyewa WHERE status_huni='sudah keluar'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Penyewa - Admin Berkah Malika</title>
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
        .stat-card { background: white; border-radius: 16px; padding: 1.5rem; box-shadow: 0 4px 15px rgba(0,0,0,0.06); }
        .table-card { background: white; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.06); overflow: hidden; }
        .table th { background: #f8f9fa; font-weight: 600; font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; border: 0; padding: 1rem; }
        .table td { padding: 1rem; vertical-align: middle; border-color: #f0f0f0; font-size: 0.88rem; }
        .badge-aktif  { background: #d1fae5; color: #059669; border-radius: 50px; padding: 0.3rem 0.8rem; font-size: 0.78rem; font-weight: 600; }
        .badge-keluar { background: #fee2e2; color: #dc2626; border-radius: 50px; padding: 0.3rem 0.8rem; font-size: 0.78rem; font-weight: 600; }
        .filter-btn { border-radius: 50px; padding: 0.4rem 1.2rem; font-size: 0.85rem; border: 2px solid; transition: all 0.2s; }
        .avatar-sm { width: 38px; height: 38px; border-radius: 50%; background: linear-gradient(135deg, #10b981, #059669); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem; flex-shrink: 0; }
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
        <!-- Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="fw-bold mb-1"><i class="fas fa-users me-2"></i>Data Penyewa</h4>
                    <p class="opacity-75 mb-0 small">Manajemen penghuni aktif kos</p>
                </div>
            </div>
        </div>

        <?php if (isset($_GET['msg']) && $_GET['msg'] == 'checkout_success'): ?>
            <div class="alert alert-success rounded-3 border-0 shadow-sm mb-4"><i class="fas fa-check-circle me-2"></i>Penyewa berhasil di-checkout dan kamar telah tersedia kembali.</div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width:50px;height:50px;background:#d1fae5;border-radius:14px;display:flex;align-items:center;justify-content:center;color:#059669;font-size:1.3rem;"><i class="fas fa-user-check"></i></div>
                        <div>
                            <div style="font-size:1.8rem;font-weight:700;color:#059669"><?php echo $total_aktif; ?></div>
                            <div class="text-muted small">Penghuni Aktif</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width:50px;height:50px;background:#fee2e2;border-radius:14px;display:flex;align-items:center;justify-content:center;color:#dc2626;font-size:1.3rem;"><i class="fas fa-user-minus"></i></div>
                        <div>
                            <div style="font-size:1.8rem;font-weight:700;color:#dc2626"><?php echo $total_keluar; ?></div>
                            <div class="text-muted small">Sudah Keluar</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width:50px;height:50px;background:#e0f2fe;border-radius:14px;display:flex;align-items:center;justify-content:center;color:#0284c7;font-size:1.3rem;"><i class="fas fa-users"></i></div>
                        <div>
                            <div style="font-size:1.8rem;font-weight:700;color:#0284c7"><?php echo $total_aktif + $total_keluar; ?></div>
                            <div class="text-muted small">Total Penyewa</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="table-card">
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0">Daftar Penyewa</h6>
                <div class="d-flex gap-2">
                    <a href="penyewa.php?filter=aktif" class="filter-btn btn <?php echo $filter==='aktif' ? 'btn-success' : 'btn-outline-success'; ?>">Aktif</a>
                    <a href="penyewa.php?filter=keluar" class="filter-btn btn <?php echo $filter==='keluar' ? 'btn-danger' : 'btn-outline-danger'; ?>">Keluar</a>
                    <a href="penyewa.php?filter=all" class="filter-btn btn <?php echo $filter==='all' ? 'btn-secondary' : 'btn-outline-secondary'; ?>">Semua</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Penyewa</th>
                            <th>Kamar</th>
                            <th>Periode Huni</th>

                            <th>Pekerjaan</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($penyewa_list && $penyewa_list->num_rows > 0): ?>
                            <?php while ($row = $penyewa_list->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-sm"><?php echo strtoupper(substr($row['nama_lengkap'], 0, 1)); ?></div>
                                        <div>
                                            <div class="fw-bold small"><?php echo htmlspecialchars($row['nama_lengkap']); ?></div>
                                            <div class="text-muted" style="font-size:0.75rem"><?php echo htmlspecialchars($row['email']); ?></div>
                                            <?php if(!empty($row['catatan'])): ?>
                                            <div class="text-success small opacity-75 mt-1" style="font-size:0.7rem"><i class="fas fa-sticky-note me-1"></i><?php echo htmlspecialchars(mb_strimwidth($row['catatan'], 0, 30, "...")); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-bold small"><?php echo htmlspecialchars($row['nama_kamar']); ?></div>
                                    <small class="text-muted">Lantai <?php echo $row['lantai']; ?></small>
                                </td>
                                <td>
                                    <div class="small"><?php echo date('d/m/Y', strtotime($row['tanggal_masuk'])); ?></div>
                                    <div class="small text-muted">s/d <?php echo date('d/m/Y', strtotime($row['tanggal_keluar'])); ?></div>
                                </td>

                                <td><span class="small"><?php echo htmlspecialchars($row['pekerjaan'] ?? '-'); ?></span></td>
                                <td>
                                    <?php if ($row['status_huni'] === 'aktif'): ?>
                                    <span class="badge-aktif"><i class="fas fa-circle me-1" style="font-size:0.5rem"></i>Aktif</span>
                                    <?php else: ?>
                                    <span class="badge-keluar"><i class="fas fa-circle me-1" style="font-size:0.5rem"></i>Keluar</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-light btn-sm rounded-pill" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                                        <ul class="dropdown-menu dropdown-menu-end border-0 shadow">
                                            <li><a class="dropdown-item" href="detail_penyewa.php?id=<?php echo $row['id_penyewa']; ?>"><i class="fas fa-info-circle me-2 text-info"></i> Lihat Detail</a></li>
                                            <?php if ($row['status_huni'] === 'aktif'): ?>
                                            <li><a class="dropdown-item" href="penyewa.php?action=checkout&id=<?php echo $row['id_penyewa']; ?>" onclick="return confirm('Proses checkout penyewa ini? Kamar akan otomatis tersedia kembali.')"><i class="fas fa-sign-out-alt me-2 text-primary"></i> Atur Keluar / Checkout</a></li>
                                            <li><a class="dropdown-item" href="penyewa.php?action=keluar&id=<?php echo $row['id_penyewa']; ?>" onclick="return confirm('Tandai penyewa ini sudah keluar?')"><i class="fas fa-check-circle me-2 text-warning"></i> Tandai Keluar Saja</a></li>
                                            <?php else: ?>
                                            <li><a class="dropdown-item" href="penyewa.php?action=aktif&id=<?php echo $row['id_penyewa']; ?>" onclick="return confirm('Aktifkan kembali penyewa ini? Kamar akan otomatis ditandai terisi.')"><i class="fas fa-redo me-2 text-success"></i> Aktifkan Kembali</a></li>
                                            <?php endif; ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger" href="penyewa.php?action=delete&id=<?php echo $row['id_penyewa']; ?>" onclick="return confirm('Hapus data penyewa ini?')"><i class="fas fa-trash me-2"></i> Hapus</a></li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted"><i class="fas fa-users fa-3x mb-3 d-block opacity-25"></i>Tidak ada data penyewa</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
