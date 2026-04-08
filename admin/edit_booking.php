<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
require_once '../config/database.php';

$id = (int)$_GET['id'];
$success_msg = '';
$error_msg = '';

// Get Booking Data
$sql = "SELECT r.*, k.nama_kamar, k.lokasi as lokasi_kamar, u.nama_lengkap as nama_user, u.email as email_user
        FROM reservasi r 
        JOIN kamar k ON r.id_kamar = k.id_kamar 
        JOIN pengguna u ON r.id_pengguna = u.id_pengguna
        WHERE r.id_reservasi = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    header("Location: bookings.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $status = $_POST['status_reservasi'];
    $check_in = $_POST['tanggal_masuk'];
    $check_out = $_POST['tanggal_keluar'];
    
    $upd = $conn->prepare("UPDATE reservasi SET status_reservasi=?, tanggal_masuk=?, tanggal_keluar=? WHERE id_reservasi=?");
    $upd->bind_param("sssi", $status, $check_in, $check_out, $id);
    
    if ($upd->execute()) {
        $msg_judul = "";
        $msg_pesan = "";
        $msg_jenis = "reservasi";

        if ($status == 'Dikonfirmasi') {
            // Update room status
            $conn->query("UPDATE kamar SET status_kamar='terisi' WHERE id_kamar = {$booking['id_kamar']}");
            
            // AUTOMATION: Add to data_penyewa
            $cek_penyewa = $conn->prepare("SELECT id_penyewa FROM data_penyewa WHERE id_reservasi = ?");
            $cek_penyewa->bind_param("i", $id);
            $cek_penyewa->execute();
            if ($cek_penyewa->get_result()->num_rows == 0) {
                // Get some defaults from user profile if possible, though data_penyewa_form handles detail
                $ins_penyewa = $conn->prepare("INSERT INTO data_penyewa (id_reservasi, id_pengguna, id_kamar, tanggal_masuk, tanggal_keluar, status_huni) VALUES (?, ?, ?, ?, ?, 'aktif')");
                $ins_penyewa->bind_param("iiiss", $id, $booking['id_pengguna'], $booking['id_kamar'], $check_in, $check_out);
                $ins_penyewa->execute();
            }

            // AUTOMATION: Add to pembayaran
            $cek_pembayaran = $conn->prepare("SELECT id_pembayaran FROM pembayaran WHERE id_reservasi = ?");
            $cek_pembayaran->bind_param("i", $id);
            $cek_pembayaran->execute();
            if ($cek_pembayaran->get_result()->num_rows == 0) {
                $kode_inv = "INV-" . date('Ymd') . "-" . str_pad($id, 4, '0', STR_PAD_LEFT);
                $now = date('Y-m-d H:i:s');
                $ins_pembayaran = $conn->prepare("INSERT INTO pembayaran (id_reservasi, kode_pesanan, jumlah_bayar, jenis_pembayaran, status_transaksi, dibayar_pada, dibuat_pada) VALUES (?, ?, ?, ?, 'lunas', ?, ?)");
                $ins_pembayaran->bind_param("isdsss", $id, $kode_inv, $booking['total_harga'], $booking['metode_pembayaran'], $now, $now);
                $ins_pembayaran->execute();
            }

            $msg_judul = "Reservasi Dikonfirmasi! 🎉";
            $msg_pesan = "Kabar baik! Reservasi Anda untuk kamar {$booking['nama_kamar']} telah dikonfirmasi oleh admin. Silakan lengkapi data penyewa di menu 'Data Saya'.";
        } elseif ($status == 'Dibatalkan') {
            $conn->query("UPDATE kamar SET status_kamar='tersedia' WHERE id_kamar = {$booking['id_kamar']}");
            $msg_judul = "Reservasi Dibatalkan";
            $msg_pesan = "Mohon maaf, reservasi Anda untuk kamar {$booking['nama_kamar']} telah dibatalkan oleh admin.";
        }

        // Send Notification
        if ($msg_judul) {
            $notif = $conn->prepare("INSERT INTO notifikasi (id_pengguna, judul, pesan, jenis) VALUES (?, ?, ?, ?)");
            $notif->bind_param("isss", $booking['id_pengguna'], $msg_judul, $msg_pesan, $msg_jenis);
            $notif->execute();
        }

        header("Location: bookings.php?msg=updated");
        exit();
    } else {
        $error_msg = "Gagal memperbarui status: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Reservasi - Admin Berkah Malika</title>
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
        .card-custom { background: white; border-radius: 16px; border: 0; box-shadow: 0 4px 15px rgba(0,0,0,0.06); padding: 2rem; }
        .status-pill { border-radius: 50px; padding: 0.4rem 1.2rem; font-weight: 600; font-size: 0.85rem; }
        .proof-img { max-width: 100%; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .detail-label { font-size: 0.75rem; text-transform: uppercase; color: #9ca3af; font-weight: 600; letter-spacing: 0.5px; }
        .detail-value { font-weight: 600; color: #1f2937; margin-bottom: 1rem; }
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
                <h4 class="fw-bold mb-1"><i class="fas fa-info-circle me-2"></i>Detail Reservasi #<?php echo $id; ?></h4>
                <p class="opacity-75 mb-0 small">Lihat dan perbarui status pemesanan</p>
            </div>
            <a href="bookings.php" class="btn btn-white bg-white text-indigo-600 fw-600 rounded-pill px-4 shadow-sm">
                <i class="fas fa-arrow-left me-2"></i>Kembali
            </a>
        </div>

        <div class="row g-4">
            <div class="col-md-7">
                <div class="card-custom">
                    <h5 class="fw-bold mb-4 border-bottom pb-3">Informasi Pemesanan</h5>
                    <div class="row">
                        <div class="col-6">
                            <div class="detail-label">Kamar</div>
                            <div class="detail-value text-indigo-600"><?php echo htmlspecialchars($booking['nama_kamar']); ?></div>
                        </div>
                        <div class="col-6">
                            <div class="detail-label">Lokasi</div>
                            <div class="detail-value"><?php echo htmlspecialchars($booking['lokasi_kamar']); ?></div>
                        </div>
                        <div class="col-12"><hr class="my-3 opacity-25"></div>
                        <div class="col-6">
                            <div class="detail-label">Nama Pemesan</div>
                            <div class="detail-value"><?php echo htmlspecialchars($booking['nama_pemesan']); ?></div>
                        </div>
                        <div class="col-6">
                            <div class="detail-label">Email / User</div>
                            <div class="detail-value"><?php echo htmlspecialchars($booking['email_user']); ?></div>
                        </div>
                        <div class="col-12"><hr class="my-3 opacity-25"></div>
                        <div class="col-6">
                            <div class="detail-label">Tanggal Masuk</div>
                            <div class="detail-value"><?php echo date('d F Y', strtotime($booking['tanggal_masuk'])); ?></div>
                        </div>
                        <div class="col-6">
                            <div class="detail-label">Tanggal Keluar</div>
                            <div class="detail-value"><?php echo date('d F Y', strtotime($booking['tanggal_keluar'])); ?></div>
                        </div>
                        <div class="col-6">
                            <div class="detail-label">Durasi Sewa</div>
                            <div class="detail-value"><span class="badge bg-light text-dark border"><?php echo $booking['durasi_sewa']; ?></span></div>
                        </div>
                        <div class="col-6">
                            <div class="detail-label">Total Harga</div>
                            <div class="detail-value text-indigo-600" style="font-size:1.2rem">Rp <?php echo number_format($booking['total_harga']); ?></div>
                        </div>
                        <div class="col-12"><hr class="my-2 opacity-10"></div>
                        <div class="col-12">
                            <div class="detail-label">Catatan Tambahan</div>
                            <div class="detail-value bg-light p-3 rounded-4 border <?php echo empty($booking['catatan']) ? 'text-muted italic fw-normal' : ''; ?>">
                                <?php echo !empty($booking['catatan']) ? nl2br(htmlspecialchars($booking['catatan'])) : 'Tidak ada catatan khusus.'; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-5">
                <!-- Status Update -->
                <div class="card-custom mb-4">
                    <h5 class="fw-bold mb-4">Update Status</h5>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Ubah Status Reservasi</label>
                            <select name="status_reservasi" class="form-select mb-3">
                                <option value="Menunggu" <?php if($booking['status_reservasi'] == 'Menunggu') echo 'selected'; ?>>Menunggu Konfirmasi</option>
                                <option value="Dikonfirmasi" <?php if($booking['status_reservasi'] == 'Dikonfirmasi') echo 'selected'; ?>>Konfirmasi (Setuju)</option>
                                <option value="Dibatalkan" <?php if($booking['status_reservasi'] == 'Dibatalkan') echo 'selected'; ?>>Batalkan Pesanan</option>
                            </select>
                        </div>
                        <div class="row g-2 mb-4">
                            <div class="col-6">
                                <label class="form-label small">Tgl Masuk</label>
                                <input type="date" name="tanggal_masuk" class="form-control form-control-sm" value="<?php echo $booking['tanggal_masuk']; ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label small">Tgl Keluar</label>
                                <input type="date" name="tanggal_keluar" class="form-control form-control-sm" value="<?php echo $booking['tanggal_keluar']; ?>">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 rounded-pill py-2 shadow"><i class="fas fa-save me-2"></i>Simpan Perubahan</button>
                    </form>
                </div>

                <!-- Payment Proof -->
                <div class="card-custom">
                    <h5 class="fw-bold mb-3">Bukti Pembayaran</h5>
                    <div class="detail-label mb-2">Metode: <?php echo $booking['metode_pembayaran']; ?></div>
                    
                    <?php if($booking['metode_pembayaran'] == 'Tunai'): ?>
                        <div class="alert alert-success border-0 bg-opacity-10 bg-success text-center py-4">
                            <i class="fas fa-hand-holding-usd fa-2x mb-2 text-success"></i>
                            <div class="small fw-bold text-success">Pembayaran Tunai / Langsung</div>
                            <div class="small text-muted">Tidak memerlukan unggahan bukti digital.</div>
                        </div>
                    <?php elseif($booking['bukti_pembayaran']): ?>
                        <div class="text-center">
                            <img src="../public/<?php echo $booking['bukti_pembayaran']; ?>" class="proof-img mb-3" onerror="this.src='https://via.placeholder.com/300?text=Bukti+Gagal+Dimuat'">
                            <a href="../public/<?php echo $booking['bukti_pembayaran']; ?>" target="_blank" class="btn btn-outline-indigo btn-sm rounded-pill"><i class="fas fa-expand me-2"></i>Lihat Ukuran Penuh</a>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-light border text-center py-4">
                            <i class="fas fa-image fa-2x mb-2 text-muted opacity-50"></i>
                            <div class="small text-muted">Belum ada bukti pembayaran diunggah.</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
