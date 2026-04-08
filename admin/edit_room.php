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

// Get Room Data
$stmt = $conn->prepare("SELECT * FROM kamar WHERE id_kamar = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();

if (!$room) {
    header("Location: rooms.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name        = $_POST['name'];
    $location    = $_POST['location'];
    $description = $_POST['description'];
    $price_month = $_POST['price_month'];
    $price_year  = $_POST['price_year'];
    $floor       = $_POST['floor'];
    $status      = $_POST['status_kamar'];
    
    $target_dir = "../public/image/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

    function uploadToPath($file_key, $target_dir, $old_val) {
        if (!empty($_FILES[$file_key]['name'])) {
            $filename = uniqid() . '_' . basename($_FILES[$file_key]['name']);
            move_uploaded_file($_FILES[$file_key]['tmp_name'], $target_dir . $filename);
            return 'image/' . $filename;
        }
        return $old_val;
    }

    $img_main      = uploadToPath('image_main',     $target_dir, $room['foto_utama']);
    $img_wardrobe  = uploadToPath('image_wardrobe',  $target_dir, $room['foto_lemari']);
    $img_bed       = uploadToPath('image_bed',       $target_dir, $room['foto_kasur']);
    $img_kitchen   = uploadToPath('image_kitchen',   $target_dir, $room['foto_dapur']);
    $img_bathroom  = uploadToPath('image_bathroom',  $target_dir, $room['foto_kamar_mandi']);
    $img_other     = uploadToPath('image_other',     $target_dir, $room['foto_lainnya']);

    $upd = $conn->prepare("UPDATE kamar SET nama_kamar=?, lokasi=?, deskripsi=?, harga_per_bulan=?, harga_per_tahun=?, lantai=?, foto_utama=?, foto_lemari=?, foto_kasur=?, foto_dapur=?, foto_kamar_mandi=?, foto_lainnya=?, status_kamar=? WHERE id_kamar=?");
    $upd->bind_param("sssddisssssssi", $name, $location, $description, $price_month, $price_year, $floor, $img_main, $img_wardrobe, $img_bed, $img_kitchen, $img_bathroom, $img_other, $status, $id);
    
    if ($upd->execute()) {
        $success_msg = "Data kamar berhasil diperbarui!";
        // Refresh room data
        $stmt->execute();
        $room = $stmt->get_result()->fetch_assoc();
    } else {
        $error_msg = "Gagal memperbarui kamar: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Kamar - Admin Berkah Malika</title>
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
        .page-header { background: linear-gradient(135deg, #f59e0b, #d97706); border-radius: 16px; padding: 1.5rem; color: white; margin-bottom: 2rem; }
        .card-custom { background: white; border-radius: 16px; border: 0; box-shadow: 0 4px 15px rgba(0,0,0,0.06); padding: 1.5rem; }
        .form-label { font-weight: 600; color: #4b5563; font-size: 0.85rem; }
        .form-control, .form-select { border-radius: 10px; border: 1px solid #e5e7eb; padding: 0.75rem; font-size: 0.9rem; }
        .form-control:focus { border-color: #f59e0b; box-shadow: 0 0 0 4px rgba(245,158,11,0.1); }
        .img-thumb-edit { width: 100%; height: 100px; object-fit: cover; border-radius: 10px; margin-bottom: 10px; border: 1px solid #e5e7eb; }
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
                <h4 class="fw-bold mb-1"><i class="fas fa-edit me-2"></i>Edit Detail Kamar</h4>
                <p class="opacity-75 mb-0 small">Perbarui data kamar: <?php echo htmlspecialchars($room['nama_kamar']); ?></p>
            </div>
            <a href="rooms.php" class="btn btn-white bg-white text-dark fw-600 rounded-pill px-4 shadow-sm">
                <i class="fas fa-arrow-left me-2"></i>Kembali
            </a>
        </div>

        <?php if ($success_msg): ?>
            <div class="alert alert-success rounded-3 border-0 shadow-sm mb-4"><i class="fas fa-check-circle me-2"></i><?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
            <div class="alert alert-danger rounded-3 border-0 shadow-sm mb-4"><i class="fas fa-exclamation-circle me-2"></i><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <div class="card-custom mb-4">
            <form method="POST" enctype="multipart/form-data">
                <div class="row g-4">
                    <div class="col-md-8">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label text-uppercase">Nama Kamar</label>
                                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($room['nama_kamar']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-uppercase">Lokasi / Kategori</label>
                                <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($room['lokasi']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-uppercase">Lantai</label>
                                <input type="number" name="floor" class="form-control" value="<?php echo $room['lantai']; ?>" min="1">
                            </div>
                            <div class="col-12">
                                <label class="form-label text-uppercase">Deskripsi Kamar</label>
                                <textarea name="description" class="form-control" rows="4"><?php echo htmlspecialchars($room['deskripsi']); ?></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="bg-light p-4 rounded-3 border h-100">
                            <h6 class="fw-bold mb-3 border-bottom pb-2">Informasi Harga & Status</h6>
                            <div class="mb-3">
                                <label class="form-label">Harga per BULAN (Rp)</label>
                                <input type="number" name="price_month" class="form-control fw-bold text-indigo-600" value="<?php echo $room['harga_per_bulan']; ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Harga per TAHUN (Rp)</label>
                                <input type="number" name="price_year" class="form-control" value="<?php echo $room['harga_per_tahun']; ?>" required>
                            </div>
                            <div class="mb-0">
                                <label class="form-label">Status Unit</label>
                                <select name="status_kamar" class="form-select">
                                    <option value="tersedia" <?php if($room['status_kamar'] == 'tersedia') echo 'selected'; ?>>Tersedia</option>
                                    <option value="terisi" <?php if($room['status_kamar'] == 'terisi') echo 'selected'; ?>>Terisi</option>
                                    <option value="perbaikan" <?php if($room['status_kamar'] == 'perbaikan') echo 'selected'; ?>>Perbaikan</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <h6 class="fw-bold mt-5 mb-3"><i class="fas fa-images me-2 text-warning"></i>Galeri Foto Kamar</h6>
                <div class="row g-3">
                    <?php
                    $images = [
                        'image_main' => ['label' => 'Foto Utama', 'key' => 'foto_utama'],
                        'image_wardrobe' => ['label' => 'Lemari Pakaian', 'key' => 'foto_lemari'],
                        'image_bed' => ['label' => 'Tempat Tidur', 'key' => 'foto_kasur'],
                        'image_kitchen' => ['label' => 'Dapur', 'key' => 'foto_dapur'],
                        'image_bathroom' => ['label' => 'Kamar Mandi', 'key' => 'foto_kamar_mandi'],
                        'image_other' => ['label' => 'Foto Lainnya', 'key' => 'foto_lainnya'],
                    ];
                    foreach ($images as $field => $data):
                        $val = $room[$data['key']];
                    ?>
                    <div class="col-md-4 col-6">
                        <label class="form-label"><?php echo $data['label']; ?></label>
                        <img src="../public/<?php echo $val; ?>" class="img-thumb-edit" onerror="this.src='https://via.placeholder.com/100?text=No+Image'">
                        <input type="file" name="<?php echo $field; ?>" class="form-control form-control-sm">
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="mt-5 pt-3 border-top text-end">
                    <button type="submit" class="btn btn-warning rounded-pill px-5 text-white fw-bold shadow-lg">Simpan Perubahan</button>
                    <a href="rooms.php" class="btn btn-light rounded-pill px-4 ms-2">Batal</a>
                </div>
            </form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
