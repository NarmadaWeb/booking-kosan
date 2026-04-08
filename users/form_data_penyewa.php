<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$success = '';

// Check if user has a confirmed reservation
$stmt = $conn->prepare("SELECT r.*, k.nama_kamar FROM reservasi r JOIN kamar k ON r.id_kamar = k.id_kamar WHERE r.id_pengguna = ? AND r.status_reservasi = 'Dikonfirmasi' ORDER BY r.dibuat_pada DESC LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$reservasi = $stmt->get_result()->fetch_assoc();

if (!$reservasi) {
    die("
    <div class='container py-5 text-center'>
        <div class='alert alert-warning shadow-sm mx-auto' style='max-width: 500px; border-radius:16px;'>
            <i class='fas fa-exclamation-triangle fa-3x text-warning mb-3 d-block'></i>
            <h5>Belum Ada Reservasi Dikonfirmasi</h5>
            <p class='text-muted small mb-3'>Untuk mengisi data penyewa, Anda perlu memiliki reservasi yang sudah dikonfirmasi admin terlebih dahulu.</p>
            <a href='pesanan_saya.php' class='btn btn-primary rounded-pill px-4'>
                <i class='fas fa-calendar-alt me-2'></i>Lihat Pesanan Saya
            </a>
        </div>
    </div>");
}

// Check if data_penyewa already exists
$cek = $conn->prepare("SELECT * FROM data_penyewa WHERE id_reservasi = ? AND id_pengguna = ?");
$cek->bind_param("ii", $reservasi['id_reservasi'], $user_id);
$cek->execute();
$existing = $cek->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $no_ktp      = trim($_POST['no_ktp']);
    $alamat_asal = trim($_POST['alamat_asal']);
    $pekerjaan   = trim($_POST['pekerjaan']);
    
    
    if ($existing) {
        // Update
        $upd = $conn->prepare("UPDATE data_penyewa SET no_ktp=?, alamat_asal=?, pekerjaan=? WHERE id_penyewa=?");
        $upd->bind_param("sssi", $no_ktp, $alamat_asal, $pekerjaan, $existing['id_penyewa']);
        if ($upd->execute()) $success = "Data penyewa berhasil diperbarui!";
        else $message = "Gagal menyimpan: " . $conn->error;
    } else {
        // Insert
        $id_res = $reservasi['id_reservasi'];
        $id_kmr = $reservasi['id_kamar'];
        $tgl_mk = $reservasi['tanggal_masuk'];
        $tgl_kl = $reservasi['tanggal_keluar'];
        $ins = $conn->prepare("INSERT INTO data_penyewa (id_reservasi, id_pengguna, id_kamar, no_ktp, alamat_asal, pekerjaan, tanggal_masuk, tanggal_keluar, status_huni) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'aktif')");
        $ins->bind_param("iiisssss", $id_res, $user_id, $id_kmr, $no_ktp, $alamat_asal, $pekerjaan, $tgl_mk, $tgl_kl);
        if ($ins->execute()) $success = "Data penyewa berhasil disimpan!";
        else $message = "Gagal menyimpan: " . $conn->error;
    }
    
    // Refresh existing
    $cek->execute();
    $existing = $cek->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Penyewa - Kos Berkah Malika</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Poppins', sans-serif; }
        body { background: #f4f6fb; }
        .navbar { background: white !important; box-shadow: 0 2px 15px rgba(0,0,0,0.08); }
        .page-header { background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 2.5rem 0; margin-bottom: 2rem; }
        .form-card { background: white; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.06); overflow: hidden; }
        .section-head { background: linear-gradient(to right, #f0fdf4, white); padding: 1.2rem 1.5rem; border-bottom: 1px solid #d1fae5; }
        .form-control, .form-select { border-radius: 12px; padding: 0.75rem 1rem; border: 2px solid #e9ecef; transition: all 0.3s; }
        .form-control:focus { border-color: #10b981; box-shadow: 0 0 0 4px rgba(16,185,129,0.15); }
        .form-label { font-weight: 500; font-size: 0.85rem; color: #555; }
        .btn-simpan { background: linear-gradient(135deg, #10b981, #059669); border: none; border-radius: 12px; padding: 0.85rem 2rem; font-weight: 600; box-shadow: 0 4px 15px rgba(16,185,129,0.35); transition: all 0.3s; }
        .btn-simpan:hover { transform: translateY(-2px); }
        .info-box { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 1rem; }

    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white">
        <div class="container">
            <a class="navbar-brand fw-bold text-primary" href="../index.php"><i class="fas fa-home me-2"></i>Kos Berkah Malika</a>
            <div class="navbar-nav ms-auto d-flex flex-row gap-2">
                <a href="../index.php" class="btn btn-outline-dark btn-sm rounded-pill"><i class="fas fa-home me-1"></i>Beranda</a>
                <a href="pesanan_saya.php" class="btn btn-outline-primary btn-sm rounded-pill"><i class="fas fa-calendar-alt me-1"></i>Pesanan</a>
                <a href="profil.php" class="btn btn-outline-secondary btn-sm rounded-pill"><i class="fas fa-user me-1"></i>Profil</a>
                <a href="../logout.php" class="btn btn-outline-danger btn-sm rounded-pill"><i class="fas fa-sign-out-alt me-1"></i>Keluar</a>
            </div>
        </div>
    </nav>

    <div class="page-header">
        <div class="container">
            <h4 class="fw-bold mb-1"><i class="fas fa-id-card me-2"></i>Data Penyewa</h4>
            <p class="opacity-75 mb-0 small">Lengkapi data diri Anda sebagai penghuni kos</p>
        </div>
    </div>

    <div class="container pb-5" style="max-width: 700px;">
        <?php if ($success): ?>
            <div class="alert alert-success rounded-3 border-0"><i class="fas fa-check-circle me-2"></i><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($message): ?>
            <div class="alert alert-danger rounded-3 border-0"><i class="fas fa-exclamation-circle me-2"></i><?php echo $message; ?></div>
        <?php endif; ?>

        <!-- Info Reservasi -->
        <div class="info-box mb-4">
            <p class="mb-1 fw-bold text-success"><i class="fas fa-check-circle me-2"></i>Reservasi Dikonfirmasi</p>
            <p class="mb-1 small text-muted"><strong>Kamar:</strong> <?php echo htmlspecialchars($reservasi['nama_kamar']); ?></p>
            <p class="mb-0 small text-muted"><strong>Periode:</strong> <?php echo date('d M Y', strtotime($reservasi['tanggal_masuk'])); ?> – <?php echo date('d M Y', strtotime($reservasi['tanggal_keluar'])); ?></p>
        </div>

        <!-- Form Data Penyewa -->
        <div class="form-card">
            <div class="section-head">
                <h6 class="fw-bold mb-0"><i class="fas fa-user-check me-2 text-success"></i>
                    <?php echo $existing ? 'Update Data Penyewa' : 'Isi Data Penyewa'; ?>
                </h6>
            </div>
            <div class="p-4">
                <form method="POST" enctype="multipart/form-data">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">NIK / Nomor KTP</label>
                            <input type="text" name="no_ktp" class="form-control" maxlength="50"
                                   value="<?php echo htmlspecialchars($existing['no_ktp'] ?? ''); ?>"
                                   placeholder="16 digit NIK sesuai KTP">
                        </div>



                        <div class="col-12">
                            <label class="form-label">Alamat Asal</label>
                            <textarea name="alamat_asal" class="form-control" rows="3" placeholder="Alamat lengkap asal daerah Anda"><?php echo htmlspecialchars($existing['alamat_asal'] ?? ''); ?></textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Pekerjaan</label>
                            <input type="text" name="pekerjaan" class="form-control"
                                   value="<?php echo htmlspecialchars($existing['pekerjaan'] ?? ''); ?>"
                                   placeholder="Contoh: Mahasiswa, Karyawan Swasta, dll.">
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-simpan btn-success text-white w-100">
                            <i class="fas fa-save me-2"></i><?php echo $existing ? 'Perbarui Data' : 'Simpan Data'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
