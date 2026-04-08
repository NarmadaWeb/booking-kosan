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
    $conn->query("DELETE FROM reservasi WHERE id_reservasi = $id");
    header("Location: bookings.php?msg=deleted");
    exit();
}

// Auto-verify: cek pembayaran pending ke Midtrans API
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
                $ur = $conn->prepare("UPDATE reservasi SET status_reservasi = 'Menunggu' WHERE id_reservasi = ? AND status_reservasi = 'Menunggu Pembayaran'");
                $ur->bind_param("i", $prow['id_reservasi']);
                $ur->execute();
            } elseif ($ts == 'cancel' || $ts == 'deny' || $ts == 'expire') {
                $ur = $conn->prepare("UPDATE reservasi SET status_reservasi = 'Dibatalkan' WHERE id_reservasi = ? AND status_reservasi = 'Menunggu Pembayaran'");
                $ur->bind_param("i", $prow['id_reservasi']);
                $ur->execute();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Reservasi - Admin Berkah Malika</title>
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
        .status-badge { border-radius: 50px; padding: 0.3rem 0.8rem; font-size: 0.75rem; font-weight: 600; }
        .status-menunggu { background: #fef3c7; color: #d97706; }
        .status-dikonfirmasi { background: #d1fae5; color: #059669; }
        .status-dibatalkan { background: #fee2e2; color: #dc2626; }
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
            <a href="bookings.php" class="nav-link-sidebar active"><i class="fas fa-calendar-check"></i> Reservasi</a>
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
                <h4 class="fw-bold mb-1"><i class="fas fa-receipt me-2"></i>Kelola Reservasi</h4>
                <p class="opacity-75 mb-0 small">Pantau status pemesanan kamar kost</p>
            </div>
            <a href="add_booking_offline.php" class="btn btn-light rounded-pill fw-bold text-primary px-4">
                <i class="fas fa-plus me-2"></i>Tambah Pemesanan Offline
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
                            <th>Pemesan</th>
                            <th>Periode Sewa</th>
                            <th>Total Bayar</th>
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT r.*, k.nama_kamar 
                                FROM reservasi r 
                                JOIN kamar k ON r.id_kamar = k.id_kamar 
                                ORDER BY r.id_reservasi DESC";
                        $result = $conn->query($sql);

                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                $status_lower = strtolower($row['status_reservasi']);
                                $status_cls = 'status-menunggu';
                                if ($status_lower == 'dikonfirmasi') $status_cls = 'status-dikonfirmasi';
                                if ($status_lower == 'dibatalkan') $status_cls = 'status-dibatalkan';
                                
                                echo "<tr>";
                                echo "<td><div class='fw-bold'>{$row['nama_kamar']}</div><small class='text-muted'>ID: #{$row['id_reservasi']}</small></td>";
                                echo "<td>
                                        <div class='fw-bold'>{$row['nama_pemesan']}</div>
                                        <div class='small text-muted'><i class='fas fa-user me-1'></i>ID Pengguna: {$row['id_pengguna']}</div>
                                        " . (!empty($row['catatan']) ? "<div class='small text-indigo-600 mt-1'><i class='fas fa-sticky-note me-1'></i>" . htmlspecialchars($row['catatan']) . "</div>" : "") . "
                                      </td>";
                                echo "<td>
                                        <div class='small fw-bold'>" . date('d M Y', strtotime($row['tanggal_masuk'])) . "</div>
                                        <div class='small text-muted'>s/d " . date('d M Y', strtotime($row['tanggal_keluar'])) . "</div>
                                        <div class='badge bg-light text-dark border mt-1' style='font-size:0.7rem'>{$row['durasi_sewa']}</div>
                                      </td>";
                                echo "<td>
                                        <div class='fw-bold text-indigo-600'>Rp " . number_format($row['total_harga']) . "</div>
                                        <div class='small text-muted'>{$row['metode_pembayaran']}</div>
                                      </td>";
                                echo "<td><span class='status-badge {$status_cls}'>{$row['status_reservasi']}</span></td>";
                                echo "<td class='text-end'>
                                        <a href='edit_booking.php?id={$row['id_reservasi']}' class='btn btn-light btn-sm rounded-pill px-3 me-2'>
                                            <i class='fas fa-eye me-1'></i> Detail
                                        </a>
                                        <a href='bookings.php?action=delete&id={$row['id_reservasi']}' class='btn btn-outline-danger btn-sm rounded-pill px-3' onclick=" . 'return confirm("Hapus data reservasi ini?")' . ">
                                            <i class='fas fa-trash me-1'></i> Hapus
                                        </a>
                                      </td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='6' class='text-center py-5 text-muted'>Belum ada reservasi masuk.</td></tr>";
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
