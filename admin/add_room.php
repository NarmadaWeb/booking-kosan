<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
require_once '../config/database.php';

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name        = $_POST['name'];
    $location    = $_POST['location'];
    $description = $_POST['description'];
    $price_month = $_POST['price_month'] ?? 0;
    $price_year  = $_POST['price_year'] ?? 0;
    $floor       = $_POST['floor'];
    $status      = $_POST['status_kamar'] ?? 'tersedia';
    
    $target_dir = "../public/image/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

    function uploadToPath($file_key, $target_dir) {
        if (!empty($_FILES[$file_key]['name'])) {
            $filename = uniqid() . '_' . basename($_FILES[$file_key]['name']);
            move_uploaded_file($_FILES[$file_key]['tmp_name'], $target_dir . $filename);
            return 'image/' . $filename;
        }
        return null;
    }

    $img_main      = uploadToPath('image_main',     $target_dir);
    $img_wardrobe  = uploadToPath('image_wardrobe',  $target_dir);
    $img_bed       = uploadToPath('image_bed',       $target_dir);
    $img_kitchen   = uploadToPath('image_kitchen',   $target_dir);
    $img_bathroom  = uploadToPath('image_bathroom',  $target_dir);
    $img_other     = uploadToPath('image_other',     $target_dir);

    $stmt = $conn->prepare("INSERT INTO kamar (nama_kamar, lokasi, deskripsi, harga_per_bulan, harga_per_tahun, lantai, foto_utama, foto_lemari, foto_kasur, foto_dapur, foto_kamar_mandi, foto_lainnya, status_kamar) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssddisssssss", $name, $location, $description, $price_month, $price_year, $floor, $img_main, $img_wardrobe, $img_bed, $img_kitchen, $img_bathroom, $img_other, $status);
    
    if ($stmt->execute()) {
        $success_msg = "Kamar berhasil ditambahkan!";
    } else {
        $error_msg = "Gagal menambahkan kamar: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Kamar - Admin Berkah Malika</title>
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
        .card-custom { background: white; border-radius: 16px; border: 0; box-shadow: 0 4px 15px rgba(0,0,0,0.06); padding: 1.5rem; }
        .form-label { font-weight: 600; color: #4b5563; font-size: 0.85rem; }
        .form-control, .form-select { border-radius: 10px; border: 1px solid #e5e7eb; padding: 0.75rem; font-size: 0.9rem; }
        .form-control:focus { border-color: #4f46e5; box-shadow: 0 0 0 4px rgba(79,70,229,0.1); }
        .image-preview-label { height: 120px; border: 2px dashed #e5e7eb; border-radius: 12px; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #9ca3af; cursor: pointer; transition: 0.2s; }
        .image-preview-label:hover { border-color: #4f46e5; color: #4f46e5; background: #f5f3ff; }
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
                <h4 class="fw-bold mb-1"><i class="fas fa-plus-circle me-2"></i>Tambah Kamar Baru</h4>
                <p class="opacity-75 mb-0 small">Masukkan detail unit kamar kost baru</p>
            </div>
            <a href="rooms.php" class="btn btn-white bg-white text-indigo-600 fw-600 rounded-pill px-4 shadow-sm">
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
                                <label class="form-label text-uppercase letter-spacing-1">Nama Kamar</label>
                                <input type="text" name="name" class="form-control" placeholder="Contoh: Kamar 102" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-uppercase">Lokasi / Kategori</label>
                                <input type="text" name="location" class="form-control" placeholder="Contoh: Lantai Bawah">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-uppercase">Lantai</label>
                                <input type="number" name="floor" class="form-control" value="1" min="1">
                            </div>
                            <div class="col-12">
                                <label class="form-label text-uppercase">Deskripsi Kamar</label>
                                <textarea name="description" class="form-control" rows="4" placeholder="Detail fasilitas pendukung..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="bg-light p-4 rounded-3 border">
                            <h6 class="fw-bold mb-3 border-bottom pb-2">Informasi Harga & Status</h6>
                            <div class="mb-3">
                                <label class="form-label">Harga per BULAN (Rp)</label>
                                <input type="number" name="price_month" class="form-control fw-bold text-indigo-600" placeholder="0" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Harga per TAHUN (Rp)</label>
                                <input type="number" name="price_year" class="form-control" placeholder="0" required>
                            </div>
                            <div class="mb-0">
                                <label class="form-label">Status Unit</label>
                                <select name="status_kamar" class="form-select">
                                    <option value="tersedia">Tersedia</option>
                                    <option value="terisi">Terisi</option>
                                    <option value="perbaikan">Perbaikan</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <h6 class="fw-bold mt-5 mb-3"><i class="fas fa-images me-2 text-indigo-600"></i>Galeri Foto Kamar</h6>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Foto Utama</label>
                        <input type="file" name="image_main" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Lemari Pakaian</label>
                        <input type="file" name="image_wardrobe" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tempat Tidur</label>
                        <input type="file" name="image_bed" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Dapur</label>
                        <input type="file" name="image_kitchen" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Kamar Mandi</label>
                        <input type="file" name="image_bathroom" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Foto Lainnya</label>
                        <input type="file" name="image_other" class="form-control">
                    </div>
                </div>

                <div class="mt-5 pt-3 border-top text-end">
                    <button type="reset" class="btn btn-light rounded-pill px-4 me-2">Reset Form</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-5 shadow-lg">Simpan Kamar Baru</button>
                </div>
            </form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
