<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
require_once '../config/database.php';

$success_msg = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'updated') $success_msg = "Status reservasi berhasil diperbarui.";
    if ($_GET['msg'] == 'deleted') $success_msg = "Data reservasi berhasil dihapus.";
    if ($_GET['msg'] == 'offline_added') $success_msg = "Pemesanan offline berhasil ditambahkan.";
}

// Handle Delete
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $conn->query("DELETE FROM pembayaran WHERE id_pembayaran = $id");
    header("Location: pembayaran.php");
    exit();
}

// Handle update status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $id     = (int)$_POST['id_pembayaran'];
    $status = $_POST['status_transaksi'];
    $stmt   = $conn->prepare("UPDATE pembayaran SET status_transaksi = ? WHERE id_pembayaran = ?");
    $stmt->bind_param("si", $status, $id);
    $stmt->execute();
    
    // Jika diubah ke lunas/settlement, update juga status reservasi
    if ($status == 'lunas' || $status == 'settlement') {
        $conn->query("UPDATE reservasi SET status_reservasi = 'Menunggu' WHERE id_reservasi = (SELECT id_reservasi FROM pembayaran WHERE id_pembayaran = $id)");
    }
    
    header("Location: pembayaran.php?updated=1");
    exit();
}

// Auto-verify: cek semua pembayaran yang masih pending ke Midtrans API
$pending_payments = $conn->query("SELECT p.id_pembayaran, p.kode_pesanan, p.id_reservasi FROM pembayaran p JOIN reservasi r ON p.id_reservasi = r.id_reservasi WHERE p.status_transaksi = 'pending' ORDER BY p.id_pembayaran DESC LIMIT 20");

$serverKey = MIDTRANS_SERVER_KEY;
$isProduction = MIDTRANS_IS_PRODUCTION;

if ($pending_payments && $pending_payments->num_rows > 0) {
    while ($prow = $pending_payments->fetch_assoc()) {
        $order_id = $prow['kode_pesanan'];
        $api_url = $isProduction
            ? "https://api.midtrans.com/v2/{$order_id}/status"
            : "https://api.sandbox.midtrans.com/v2/{$order_id}/status";

        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode($serverKey . ':')
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response && $httpCode == 200) {
            $api_result = json_decode($response, true);
            $ts = $api_result['transaction_status'] ?? '';
            $pt = $api_result['payment_type'] ?? '';
            $fs = $api_result['fraud_status'] ?? '';
            $ps = json_encode($api_result);

            $up = $conn->prepare("UPDATE pembayaran SET status_transaksi = ?, jenis_pembayaran = ?, status_penipuan = ?, pesan_status = ?, dibayar_pada = CURRENT_TIMESTAMP WHERE id_pembayaran = ?");
            $up->bind_param("ssssi", $ts, $pt, $fs, $ps, $prow['id_pembayaran']);
            $up->execute();

            if ($ts == 'capture' || $ts == 'settlement') {
                $ur = $conn->prepare("UPDATE reservasi SET status_reservasi = 'Menunggu' WHERE id_reservasi = ?");
                $ur->bind_param("i", $prow['id_reservasi']);
                $ur->execute();
            } elseif ($ts == 'cancel' || $ts == 'deny' || $ts == 'expire') {
                $ur = $conn->prepare("UPDATE reservasi SET status_reservasi = 'Dibatalkan' WHERE id_reservasi = ?");
                $ur->bind_param("i", $prow['id_reservasi']);
                $ur->execute();
            }
        }
    }
}

// Stats (include settlement/capture as lunas)
$total_lunas    = $conn->query("SELECT COUNT(*) as c FROM pembayaran WHERE status_transaksi IN ('lunas','settlement','capture')")->fetch_assoc()['c'];
$total_menunggu = $conn->query("SELECT COUNT(*) as c FROM pembayaran WHERE status_transaksi = 'pending'")->fetch_assoc()['c'];
$total_pendapatan = $conn->query("SELECT SUM(jumlah_bayar) as total FROM pembayaran WHERE status_transaksi IN ('lunas','settlement','capture')")->fetch_assoc()['total'] ?? 0;

// Get payments
$sql = "SELECT pb.*, r.id_reservasi, r.nama_pemesan, k.nama_kamar
        FROM pembayaran pb
        JOIN reservasi r ON pb.id_reservasi = r.id_reservasi
        JOIN kamar k ON r.id_kamar = k.id_kamar
        ORDER BY pb.dibuat_pada DESC";
$payments = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran - Admin Berkah Malika</title>
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
        .page-header { background: linear-gradient(135deg, #8b5cf6, #6d28d9); border-radius: 16px; padding: 1.5rem; color: white; margin-bottom: 2rem; }
        .stat-card { background: white; border-radius: 16px; padding: 1.5rem; box-shadow: 0 4px 15px rgba(0,0,0,0.06); }
        .table-card { background: white; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.06); overflow: hidden; }
        .table th { background: #f8f9fa; font-weight: 600; font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; border: 0; padding: 1rem; }
        .table td { padding: 1rem; vertical-align: middle; border-color: #f0f0f0; font-size: 0.88rem; }
        .badge-lunas    { background: #d1fae5; color: #059669; border-radius: 50px; padding: 0.3rem 0.8rem; font-size: 0.78rem; font-weight: 600; }
        .badge-menunggu { background: #fef3c7; color: #d97706; border-radius: 50px; padding: 0.3rem 0.8rem; font-size: 0.78rem; font-weight: 600; }
        .badge-gagal    { background: #fee2e2; color: #dc2626; border-radius: 50px; padding: 0.3rem 0.8rem; font-size: 0.78rem; font-weight: 600; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <div class="text-white fw-bold"><i class="fas fa-home me-2 text-purple" style="color:#8b5cf6"></i>Berkah Malika</div>
            <small class="text-white-50 small">Panel Admin</small>
        </div>
        <nav class="mt-2">
            <a href="index.php" class="nav-link-sidebar"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="rooms.php" class="nav-link-sidebar"><i class="fas fa-bed"></i> Kelola Kamar</a>
            <a href="bookings.php" class="nav-link-sidebar"><i class="fas fa-calendar-check"></i> Reservasi</a>
            <a href="penyewa.php" class="nav-link-sidebar"><i class="fas fa-users"></i> Data Penyewa</a>
            <a href="pembayaran.php" class="nav-link-sidebar active"><i class="fas fa-credit-card"></i> Pembayaran</a>
            <a href="notifikasi.php" class="nav-link-sidebar"><i class="fas fa-bell"></i> Notifikasi</a>
            <a href="laporan.php" class="nav-link-sidebar"><i class="fas fa-chart-bar"></i> Laporan</a>
            <a href="users.php" class="nav-link-sidebar"><i class="fas fa-user-cog"></i> Pengguna</a>
            <hr style="border-color:rgba(255,255,255,0.1)">
            <a href="../logout.php" class="nav-link-sidebar text-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="page-header">
            <h4 class="fw-bold mb-1"><i class="fas fa-credit-card me-2"></i>Data Pembayaran</h4>
            <p class="opacity-75 mb-0 small">Riwayat transaksi via Midtrans & Tunai</p>
        </div>

        <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success rounded-3 border-0"><i class="fas fa-check-circle me-2"></i>Status pembayaran berhasil diperbarui.</div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="text-muted small mb-1">Total Pendapatan (Lunas)</div>
                    <div style="font-size:1.6rem;font-weight:700;color:#059669">Rp <?php echo number_format($total_pendapatan, 0, ',', '.'); ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width:45px;height:45px;background:#d1fae5;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#059669;font-size:1.2rem"><i class="fas fa-check"></i></div>
                        <div>
                            <div style="font-size:1.8rem;font-weight:700"><?php echo $total_lunas; ?></div>
                            <div class="text-muted small">Pembayaran Lunas</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width:45px;height:45px;background:#fef3c7;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#d97706;font-size:1.2rem"><i class="fas fa-clock"></i></div>
                        <div>
                            <div style="font-size:1.8rem;font-weight:700"><?php echo $total_menunggu; ?></div>
                            <div class="text-muted small">Menunggu Pembayaran</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="table-card">
            <div class="p-3 border-bottom">
                <h6 class="fw-bold mb-0">Riwayat Transaksi</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Kode Pesanan</th>
                            <th>Pemesan</th>
                            <th>Kamar</th>
                            <th>Jumlah</th>
                            <th>Metode</th>
                            <th>Status</th>
                            <th>Dibayar</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($payments && $payments->num_rows > 0): while ($row = $payments->fetch_assoc()): 
                            $sts = $row['status_transaksi'];
                            $sts_label = $sts;
                            if ($sts === 'settlement' || $sts === 'capture' || $sts === 'lunas') {
                                $bdg = 'badge-lunas';
                                $sts_label = 'Lunas';
                            } elseif ($sts === 'pending' || $sts === 'menunggu') {
                                $bdg = 'badge-menunggu';
                                $sts_label = 'Menunggu';
                            } else {
                                $bdg = 'badge-gagal';
                                $sts_label = ucfirst($sts);
                            }
                        ?>
                        <tr>
                            <td><code class="text-primary"><?php echo htmlspecialchars($row['kode_pesanan']); ?></code></td>
                            <td><?php echo htmlspecialchars($row['nama_pemesan']); ?></td>
                            <td><?php echo htmlspecialchars($row['nama_kamar']); ?></td>
                            <td><strong>Rp <?php echo number_format($row['jumlah_bayar'], 0, ',', '.'); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['jenis_pembayaran'] ?? '-'); ?></td>
                            <td><span class="<?php echo $bdg; ?>"><?php echo htmlspecialchars($sts_label); ?></span></td>
                            <td><small class="text-muted"><?php echo $row['dibayar_pada'] ? date('d/m/Y', strtotime($row['dibayar_pada'])) : '-'; ?></small></td>
                            <td>
                                <div class="dropdown">
                                    <button class="btn btn-light btn-sm rounded-pill" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow">
                                        <li>
                                            <form method="POST" class="px-3 py-2" style="min-width:200px">
                                                <input type="hidden" name="update_status" value="1">
                                                <input type="hidden" name="id_pembayaran" value="<?php echo $row['id_pembayaran']; ?>">
                                                <label class="form-label small fw-bold">Update Status</label>
                                                <select name="status_transaksi" class="form-select form-select-sm mb-2">
                                                    <option value="menunggu" <?php if($sts==='menunggu') echo 'selected'; ?>>Menunggu</option>
                                                    <option value="lunas" <?php if($sts==='lunas') echo 'selected'; ?>>Lunas</option>
                                                    <option value="gagal" <?php if($sts==='gagal') echo 'selected'; ?>>Gagal</option>
                                                    <option value="refund" <?php if($sts==='refund') echo 'selected'; ?>>Refund</option>
                                                </select>
                                                <button type="submit" class="btn btn-primary btn-sm w-100 rounded-pill">Simpan</button>
                                            </form>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger" href="pembayaran.php?action=delete&id=<?php echo $row['id_pembayaran']; ?>" onclick="return confirm('Hapus data pembayaran ini?')"><i class="fas fa-trash me-2"></i>Hapus</a></li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted"><i class="fas fa-credit-card fa-3x mb-3 d-block opacity-25"></i>Belum ada data pembayaran</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
