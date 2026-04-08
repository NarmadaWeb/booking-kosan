<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
require_once '../config/database.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_kamar = (int)$_POST['id_kamar'];
    $nama_pemesan = $_POST['nama_pemesan'];
    $no_hp_pemesan = $_POST['no_hp_pemesan'];
    $tanggal_masuk = $_POST['tanggal_masuk'];
    $durasi_bulan = (int)$_POST['durasi_bulan'];
    $total_harga = $_POST['total_harga'];
    $metode_pembayaran = $_POST['metode_pembayaran']; // Tunai, Transfer

    // Hitung tanggal keluar
    $date = new DateTime($tanggal_masuk);
    $date->modify("+$durasi_bulan month");
    $tanggal_keluar = $date->format('Y-m-d');

    $durasi_sewa = $durasi_bulan . ' Bulan';

    // Check if room is available
    $chk = $conn->prepare("SELECT status_kamar FROM kamar WHERE id_kamar = ?");
    $chk->bind_param("i", $id_kamar);
    $chk->execute();
    $res = $chk->get_result()->fetch_assoc();

    if ($res && $res['status_kamar'] !== 'tersedia') {
        $message = "<div class='alert alert-danger'>Kamar tidak tersedia.</div>";
    } else {
        // Insert into reservasi
        $stmt = $conn->prepare("INSERT INTO reservasi (id_kamar, id_pengguna, nama_pemesan, no_hp_pemesan, tanggal_masuk, tanggal_keluar, durasi_sewa, total_harga, status_reservasi, metode_pembayaran) VALUES (?, 1, ?, ?, ?, ?, ?, ?, 'Dikonfirmasi', ?)");
        // Note: id_pengguna is set to 1 (Admin) as a placeholder for offline users, or could be a generic offline user id.
        $stmt->bind_param("isssssds", $id_kamar, $nama_pemesan, $no_hp_pemesan, $tanggal_masuk, $tanggal_keluar, $durasi_sewa, $total_harga, $metode_pembayaran);

        if ($stmt->execute()) {
            $id_reservasi = $stmt->insert_id;

            // Insert into pembayaran
            $kode_pesanan = "OFFLINE-" . $id_reservasi . "-" . time();
            $p_stmt = $conn->prepare("INSERT INTO pembayaran (id_reservasi, kode_pesanan, jumlah_bayar, jenis_pembayaran, status_transaksi, dibayar_pada) VALUES (?, ?, ?, ?, 'lunas', CURRENT_TIMESTAMP)");
            $p_stmt->bind_param("isds", $id_reservasi, $kode_pesanan, $total_harga, $metode_pembayaran);
            $p_stmt->execute();

            // Update room status
            $conn->query("UPDATE kamar SET status_kamar = 'terisi' WHERE id_kamar = $id_kamar");

            header("Location: bookings.php?msg=offline_added");
            exit();
        } else {
            $message = "<div class='alert alert-danger'>Gagal menambahkan pemesanan: " . $conn->error . "</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Pemesanan Offline - Admin</title>
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
        .card-custom { border: 0; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.06); }
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
        <h4 class="fw-bold mb-4"><i class="fas fa-user-plus me-2"></i>Tambah Pemesanan Offline</h4>

        <div class="card card-custom p-4" style="max-width: 800px;">
            <?php echo $message; ?>

            <form method="POST">
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label">Kamar Kosong</label>
                        <select name="id_kamar" class="form-select" required onchange="updatePrice()">
                            <option value="">-- Pilih Kamar --</option>
                            <?php
                            $rooms = $conn->query("SELECT * FROM kamar WHERE status_kamar = 'tersedia'");
                            while ($r = $rooms->fetch_assoc()) {
                                echo "<option value='{$r['id_kamar']}' data-price='{$r['harga_per_bulan']}'>{$r['nama_kamar']} (Lantai {$r['lantai']}) - Rp " . number_format($r['harga_per_bulan']) . "/bln</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nama Pemesan</label>
                        <input type="text" name="nama_pemesan" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">No HP</label>
                        <input type="text" name="no_hp_pemesan" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Tanggal Masuk</label>
                        <input type="date" name="tanggal_masuk" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Durasi (Bulan)</label>
                        <input type="number" name="durasi_bulan" id="durasi_bulan" class="form-control" value="1" min="1" required onchange="updatePrice()">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Total Pembayaran (Rp)</label>
                        <input type="number" name="total_harga" id="total_harga" class="form-control" required readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Metode Pembayaran</label>
                        <select name="metode_pembayaran" class="form-select" required>
                            <option value="Tunai">Tunai</option>
                            <option value="Transfer">Transfer Langsung</option>
                        </select>
                    </div>
                </div>
                <hr class="my-4">
                <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Simpan Pemesanan & Pembayaran</button>
                <a href="bookings.php" class="btn btn-light ms-2">Batal</a>
            </form>
        </div>
    </div>
    <script>
        function updatePrice() {
            const select = document.querySelector('select[name="id_kamar"]');
            const durasi = document.getElementById('durasi_bulan').value;
            if (select.selectedIndex > 0) {
                const price = select.options[select.selectedIndex].getAttribute('data-price');
                document.getElementById('total_harga').value = price * durasi;
            } else {
                document.getElementById('total_harga').value = 0;
            }
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>