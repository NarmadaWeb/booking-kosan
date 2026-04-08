<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../admin_login.php");
    exit();
}
require_once '../config/database.php';

// Date range filter
$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');
$bulan_int = (int)$bulan;
$tahun_int = (int)$tahun;

// Pendapatan per bulan from laporan_keuangan
// Actual schema: id_laporan, bulan, tahun, total_pendapatan, total_reservasi, total_kamar_terisi, total_kamar_kosong, keterangan, dibuat_pada
$lap_stmt = $conn->prepare("SELECT * FROM laporan_keuangan WHERE bulan = ? AND tahun = ?");
$lap_stmt->bind_param("ii", $bulan_int, $tahun_int);
$lap_stmt->execute();
$laporan = $lap_stmt->get_result()->fetch_assoc();

// Stats from reservasi
$bulan_fmt = sprintf('%04d-%02d', $tahun_int, $bulan_int);
$total_reservasi_bln = $conn->query("SELECT COUNT(*) as c FROM reservasi WHERE DATE_FORMAT(dibuat_pada, '%Y-%m') = '$bulan_fmt' AND status_reservasi = 'Dikonfirmasi'")->fetch_assoc()['c'];
$total_pendapatan_bln = $conn->query("SELECT SUM(total_harga) as t FROM reservasi WHERE DATE_FORMAT(dibuat_pada, '%Y-%m') = '$bulan_fmt' AND status_reservasi = 'Dikonfirmasi'")->fetch_assoc()['t'] ?? 0;

// Monthly chart data (last 12 months)
$chart_labels = [];
$chart_data   = [];
for ($i = 11; $i >= 0; $i--) {
    $ts = mktime(0, 0, 0, date('m') - $i, 1, date('Y'));
    $lbl   = date('M Y', $ts);
    $ym    = date('Y-m', $ts);
    $total = $conn->query("SELECT SUM(total_harga) as t FROM reservasi WHERE DATE_FORMAT(dibuat_pada, '%Y-%m') = '$ym' AND status_reservasi = 'Dikonfirmasi'")->fetch_assoc()['t'] ?? 0;
    $chart_labels[] = $lbl;
    $chart_data[]   = (float)$total;
}

// Pembayaran by method
$metode_data = $conn->query("SELECT metode_pembayaran, COUNT(*) as cnt, SUM(total_harga) as total FROM reservasi WHERE status_reservasi = 'Dikonfirmasi' GROUP BY metode_pembayaran");

// Top kamar
$top_kamar = $conn->query("SELECT k.nama_kamar, COUNT(r.id_reservasi) as cnt, SUM(r.total_harga) as pendapatan FROM reservasi r JOIN kamar k ON r.id_kamar = k.id_kamar WHERE r.status_reservasi = 'Dikonfirmasi' GROUP BY k.id_kamar ORDER BY cnt DESC LIMIT 5");

$bulan_names = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Keuangan - Admin Berkah Malika</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        * { font-family: 'Poppins', sans-serif; }
        body { background: #f4f6fb; }
        .sidebar { width: 250px; position: fixed; top: 0; left: 0; height: 100vh; background: linear-gradient(180deg, #1e293b, #0f172a); z-index: 100; overflow-y: auto; }
        .sidebar-brand { padding: 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .nav-link-sidebar { color: rgba(255,255,255,0.7); padding: 0.75rem 1.5rem; display: flex; align-items: center; gap: 0.75rem; border-radius: 0; transition: all 0.2s; text-decoration: none; font-size: 0.9rem; }
        .nav-link-sidebar:hover, .nav-link-sidebar.active { background: rgba(255,255,255,0.1); color: white; }
        .nav-link-sidebar i { width: 20px; text-align: center; }
        .main-content { margin-left: 250px; padding: 2rem; }
        .page-header { background: linear-gradient(135deg, #0ea5e9, #0284c7); border-radius: 16px; padding: 1.5rem; color: white; margin-bottom: 2rem; }
        .stat-card { background: white; border-radius: 16px; padding: 1.5rem; box-shadow: 0 4px 15px rgba(0,0,0,0.06); }
        .chart-card { background: white; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.06); padding: 1.5rem; }
        .table-card { background: white; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.06); overflow: hidden; }
        .table th { background: #f8f9fa; font-weight: 600; font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; border: 0; padding: 1rem; }
        .table td { padding: 0.85rem 1rem; vertical-align: middle; border-color: #f0f0f0; font-size: 0.88rem; }
        .filter-card { background: white; border-radius: 16px; padding: 1rem 1.5rem; box-shadow: 0 4px 15px rgba(0,0,0,0.06); margin-bottom: 1.5rem; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <div class="text-white fw-bold"><i class="fas fa-home me-2"></i>Berkah Malika</div>
            <small class="text-white-50 small">Panel Admin</small>
        </div>
        <nav class="mt-2">
            <a href="index.php" class="nav-link-sidebar"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="rooms.php" class="nav-link-sidebar"><i class="fas fa-bed"></i> Kelola Kamar</a>
            <a href="bookings.php" class="nav-link-sidebar"><i class="fas fa-calendar-check"></i> Reservasi</a>
            <a href="penyewa.php" class="nav-link-sidebar"><i class="fas fa-users"></i> Data Penyewa</a>
            <a href="pembayaran.php" class="nav-link-sidebar"><i class="fas fa-credit-card"></i> Pembayaran</a>
            <a href="notifikasi.php" class="nav-link-sidebar"><i class="fas fa-bell"></i> Notifikasi</a>
            <a href="laporan.php" class="nav-link-sidebar active"><i class="fas fa-chart-bar"></i> Laporan</a>
            <a href="users.php" class="nav-link-sidebar"><i class="fas fa-user-cog"></i> Pengguna</a>
            <hr style="border-color:rgba(255,255,255,0.1)">
            <a href="../logout.php" class="nav-link-sidebar text-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="page-header">
            <h4 class="fw-bold mb-1"><i class="fas fa-chart-bar me-2"></i>Laporan Keuangan</h4>
            <p class="opacity-75 mb-0 small">Analisis pendapatan dan statistik reservasi</p>
        </div>

        <!-- Filter -->
        <div class="filter-card d-flex align-items-center gap-3">
            <i class="fas fa-filter text-muted"></i>
            <form method="GET" class="d-flex gap-2 align-items-center mb-0">
                <select name="bulan" class="form-select form-select-sm rounded-pill" style="width:auto">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?php echo $m; ?>" <?php if ($m == $bulan_int) echo 'selected'; ?>><?php echo $bulan_names[$m-1]; ?></option>
                    <?php endfor; ?>
                </select>
                <select name="tahun" class="form-select form-select-sm rounded-pill" style="width:auto">
                    <?php for ($y = date('Y'); $y >= date('Y') - 3; $y--): ?>
                    <option value="<?php echo $y; ?>" <?php if ($y == $tahun_int) echo 'selected'; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
                <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3"><i class="fas fa-search me-1"></i>Filter</button>
            </form>
        </div>

        <!-- Key Stats -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="text-muted small mb-1">Pendapatan <?php echo $bulan_names[$bulan_int - 1]; ?></div>
                    <div style="font-size:1.5rem;font-weight:700;color:#0284c7">Rp <?php echo number_format($total_pendapatan_bln, 0, ',', '.'); ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="text-muted small mb-1">Reservasi Bulan Ini</div>
                    <div style="font-size:1.5rem;font-weight:700;color:#059669"><?php echo $total_reservasi_bln; ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="text-muted small mb-1">Total Pendapatan (All)</div>
                    <div style="font-size:1.5rem;font-weight:700;color:#7c3aed">Rp <?php echo number_format(array_sum($chart_data), 0, ',', '.'); ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="text-muted small mb-1">Laporan DB (<?php echo $bulan_names[$bulan_int-1]; ?>)</div>
                    <?php if ($laporan): ?>
                    <div>
                        <div class="small fw-bold text-success">Pendapatan: Rp <?php echo number_format($laporan['total_pendapatan'], 0, ',', '.'); ?></div>
                        <div class="small text-muted"><?php echo $laporan['total_reservasi']; ?> reservasi &bull; <?php echo $laporan['total_kamar_terisi']; ?> kamar terisi</div>
                        <?php if ($laporan['keterangan']): ?><div class="small text-muted mt-1"><?php echo htmlspecialchars($laporan['keterangan']); ?></div><?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-muted small">Belum ada data laporan</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Chart -->
        <div class="chart-card mb-4">
            <h6 class="fw-bold mb-3">Grafik Pendapatan 12 Bulan Terakhir</h6>
            <canvas id="revenueChart" style="max-height: 300px;"></canvas>
        </div>

        <!-- Two columns -->
        <div class="row g-4">
            <!-- Metode Pembayaran -->
            <div class="col-md-6">
                <div class="table-card">
                    <div class="p-3 border-bottom"><h6 class="fw-bold mb-0"><i class="fas fa-credit-card me-2 text-primary"></i>Metode Pembayaran</h6></div>
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Metode</th><th class="text-center">Transaksi</th><th class="text-end">Total</th></tr></thead>
                        <tbody>
                        <?php if ($metode_data && $metode_data->num_rows > 0):
                            while ($m = $metode_data->fetch_assoc()): ?>
                        <tr>
                            <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($m['metode_pembayaran'] ?: 'Transfer'); ?></span></td>
                            <td class="text-center"><?php echo $m['cnt']; ?></td>
                            <td class="text-end"><strong>Rp <?php echo number_format($m['total'], 0, ',', '.'); ?></strong></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="3" class="text-center text-muted py-3">Belum ada data</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Top Kamar -->
            <div class="col-md-6">
                <div class="table-card">
                    <div class="p-3 border-bottom"><h6 class="fw-bold mb-0"><i class="fas fa-trophy me-2 text-warning"></i>Top 5 Kamar</h6></div>
                    <table class="table table-hover mb-0">
                        <thead><tr><th>#</th><th>Kamar</th><th class="text-center">Booking</th><th class="text-end">Pendapatan</th></tr></thead>
                        <tbody>
                        <?php 
                        $rank = 1;
                        if ($top_kamar && $top_kamar->num_rows > 0):
                            while ($tk = $top_kamar->fetch_assoc()): ?>
                        <tr>
                            <td><span class="badge <?php echo $rank===1 ? 'bg-warning' : 'bg-secondary'; ?>"><?php echo $rank++; ?></span></td>
                            <td><?php echo htmlspecialchars($tk['nama_kamar']); ?></td>
                            <td class="text-center"><?php echo $tk['cnt']; ?></td>
                            <td class="text-end">Rp <?php echo number_format($tk['pendapatan'], 0, ',', '.'); ?></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">Belum ada data</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const ctx = document.getElementById('revenueChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'Pendapatan (Rp)',
                    data: <?php echo json_encode($chart_data); ?>,
                    backgroundColor: 'rgba(2, 132, 199, 0.15)',
                    borderColor: 'rgba(2, 132, 199, 1)',
                    borderWidth: 2,
                    borderRadius: 8,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => 'Rp ' + new Intl.NumberFormat('id-ID').format(ctx.parsed.y)
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: val => 'Rp ' + new Intl.NumberFormat('id-ID').format(val, {notation: 'compact'})
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
