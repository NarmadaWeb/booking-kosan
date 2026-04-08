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

// Get current user data
$stmt = $conn->prepare("SELECT * FROM pengguna WHERE id_pengguna = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    header("Location: ../logout.php");
    exit();
}

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profil'])) {
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $email        = trim($_POST['email']);
    $no_hp        = trim($_POST['no_hp']);
    
    // Check email uniqueness (excluding current user)
    $cek = $conn->prepare("SELECT id_pengguna FROM pengguna WHERE email = ? AND id_pengguna != ?");
    $cek->bind_param("si", $email, $user_id);
    $cek->execute();
    if ($cek->get_result()->num_rows > 0) {
        $message = "Email sudah digunakan akun lain.";
    } else {
        // Handle foto_profil upload
        $foto = $user['foto_profil'];
        if (!empty($_FILES['foto_profil']['name'])) {
            $target_dir = "../public/uploads/profil/";
            if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
            $ext          = strtolower(pathinfo($_FILES['foto_profil']['name'], PATHINFO_EXTENSION));
            $allowed_ext  = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($ext, $allowed_ext)) {
                $new_file = "profil_" . $user_id . "_" . time() . ".$ext";
                if (move_uploaded_file($_FILES['foto_profil']['tmp_name'], $target_dir . $new_file)) {
                    $foto = "uploads/profil/" . $new_file;
                }
            }
        }
        
        $upd = $conn->prepare("UPDATE pengguna SET nama_lengkap = ?, email = ?, no_hp = ?, foto_profil = ? WHERE id_pengguna = ?");
        $upd->bind_param("ssssi", $nama_lengkap, $email, $no_hp, $foto, $user_id);
        if ($upd->execute()) {
            $success = "Profil berhasil diperbarui!";
            $user['nama_lengkap'] = $nama_lengkap;
            $user['email']        = $email;
            $user['no_hp']        = $no_hp;
            $user['foto_profil']  = $foto;
        }
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ubah_password'])) {
    $old_pass = $_POST['old_password'];
    $new_pass = $_POST['new_password'];
    $confirm  = $_POST['confirm_new'];
    
    if (!password_verify($old_pass, $user['kata_sandi'])) {
        $message = "Password lama tidak benar.";
    } elseif (strlen($new_pass) < 6) {
        $message = "Password baru minimal 6 karakter.";
    } elseif ($new_pass !== $confirm) {
        $message = "Konfirmasi password baru tidak cocok.";
    } else {
        $hashed = password_hash($new_pass, PASSWORD_BCRYPT, ['cost' => 12]);
        $upd = $conn->prepare("UPDATE pengguna SET kata_sandi = ? WHERE id_pengguna = ?");
        $upd->bind_param("si", $hashed, $user_id);
        if ($upd->execute()) {
            $success = "Password berhasil diubah!";
        }
    }
}

// Unread notifications count
$notif_count = $conn->prepare("SELECT COUNT(*) as cnt FROM notifikasi WHERE id_pengguna = ? AND status_baca = 'belum dibaca'");
$notif_count->bind_param("i", $user_id);
$notif_count->execute();
$unread = $notif_count->get_result()->fetch_assoc()['cnt'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - Kos Berkah Malika</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Poppins', sans-serif; }
        body { background-color: #f4f6fb; }
        .navbar { background: white !important; box-shadow: 0 2px 15px rgba(0,0,0,0.08); }
        .page-header {
            background: linear-gradient(135deg, #0d6efd, #0b5ed7);
            color: white;
            padding: 3rem 0 5rem;
        }
        .profile-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.06);
            margin-top: -3rem;
            overflow: hidden;
        }
        .profile-avatar-wrapper {
            position: relative;
            display: inline-block;
        }
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid white;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .avatar-upload-btn {
            position: absolute;
            bottom: 5px;
            right: 5px;
            width: 35px;
            height: 35px;
            background: #0d6efd;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.8rem;
            cursor: pointer;
            border: 3px solid white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        .info-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.06);
            overflow: hidden;
        }
        .section-header {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 1.2rem 1.5rem;
            border-bottom: 1px solid #dee2e6;
        }
        .form-control, .form-select {
            border-radius: 12px;
            padding: 0.75rem 1rem;
            border: 2px solid #e9ecef;
            transition: all 0.3s;
        }
        .form-control:focus { border-color: #0d6efd; box-shadow: 0 0 0 4px rgba(13,110,253,0.1); }
        .form-label { font-weight: 500; font-size: 0.85rem; color: #555; }
        .btn-save {
            background: linear-gradient(135deg, #0d6efd, #0b5ed7);
            border: none;
            border-radius: 12px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(13, 110, 253, 0.3);
            transition: all 0.3s;
        }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(13, 110, 253, 0.4); }
        .stat-pill {
            background: #f0f7ff;
            border: 1px solid #cce5ff;
            border-radius: 50px;
            padding: 0.4rem 1rem;
            font-size: 0.8rem;
            color: #0d6efd;
            font-weight: 500;
        }
        .input-group-text {
            border-radius: 12px 0 0 12px;
            border: 2px solid #e9ecef;
            border-right: 0;
            background: #f8f9fa;
            color: #6c757d;
        }
        .input-group .form-control { border-radius: 0 12px 12px 0; border-left: 0; }
        .input-group:focus-within .input-group-text { border-color: #0d6efd; }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white">
        <div class="container">
            <a class="navbar-brand fw-bold text-primary" href="../index.php">
                <i class="fas fa-home me-2"></i>Kos Berkah Malika
            </a>
            <div class="navbar-nav ms-auto d-flex flex-row align-items-center gap-2">
                <a href="../index.php" class="btn btn-outline-dark btn-sm rounded-pill">
                    <i class="fas fa-home me-1"></i>Beranda
                </a>
                <a href="notifikasi.php" class="btn btn-light btn-sm rounded-pill position-relative">
                    <i class="fas fa-bell"></i>
                    <?php if ($unread > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.65rem"><?php echo $unread; ?></span>
                    <?php endif; ?>
                </a>
                <a href="pesanan_saya.php" class="btn btn-outline-primary btn-sm rounded-pill">
                    <i class="fas fa-calendar-alt me-1"></i>Pesanan Saya
                </a>
                <a href="../logout.php" class="btn btn-outline-danger btn-sm rounded-pill">
                    <i class="fas fa-sign-out-alt me-1"></i>Keluar
                </a>
            </div>
        </div>
    </nav>

    <!-- Header -->
    <div class="page-header">
        <div class="container text-center">
            <h3 class="fw-bold mb-1">Profil Saya</h3>
            <p class="opacity-75 mb-0">Kelola informasi akun Anda</p>
        </div>
    </div>

    <div class="container pb-5">
        <div class="row justify-content-center g-4">
            <!-- Left: Profile Summary -->
            <div class="col-lg-4">
                <div class="profile-card text-center p-4">
                    <div class="profile-avatar-wrapper mb-3">
                        <img src="<?php echo !empty($user['foto_profil']) ? '../public/' . htmlspecialchars($user['foto_profil']) : 'https://ui-avatars.com/api/?name=' . urlencode($user['nama_lengkap']) . '&size=120&background=0d6efd&color=fff&bold=true'; ?>"
                             class="profile-avatar" id="avatarPreview" alt="Foto Profil">
                        <label for="foto_profil_hidden" class="avatar-upload-btn" title="Ganti Foto">
                            <i class="fas fa-camera"></i>
                        </label>
                    </div>
                    <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($user['nama_lengkap']); ?></h5>
                    <p class="text-muted small mb-2">@<?php echo htmlspecialchars($user['username']); ?></p>
                    <span class="stat-pill"><i class="fas fa-user-tag me-1"></i><?php echo ucfirst($user['peran']); ?></span>
                    
                    <hr class="my-3">
                    
                    <div class="text-start">
                        <p class="small mb-2 text-muted"><i class="fas fa-envelope me-2 text-primary"></i><?php echo htmlspecialchars($user['email']); ?></p>
                        <p class="small mb-2 text-muted"><i class="fas fa-phone me-2 text-success"></i><?php echo !empty($user['no_hp']) ? htmlspecialchars($user['no_hp']) : 'Belum diisi'; ?></p>
                        <p class="small mb-0 text-muted"><i class="fas fa-calendar me-2 text-warning"></i>Bergabung <?php echo date('d M Y', strtotime($user['dibuat_pada'])); ?></p>
                    </div>
                    
                    <div class="mt-3">
                        <a href="pesanan_saya.php" class="btn btn-primary btn-sm rounded-pill w-100">
                            <i class="fas fa-calendar-alt me-1"></i>Riwayat Pesanan
                        </a>
                    </div>
                </div>
            </div>

            <!-- Right: Forms -->
            <div class="col-lg-8">
                <?php if ($success): ?>
                    <div class="alert alert-success rounded-3 border-0 mb-3">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                <?php if ($message): ?>
                    <div class="alert alert-danger rounded-3 border-0 mb-3">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <!-- Edit Profile Form -->
                <div class="info-card mb-4">
                    <div class="section-header">
                        <h6 class="fw-bold mb-0"><i class="fas fa-user-edit me-2 text-primary"></i>Edit Informasi Profil</h6>
                    </div>
                    <div class="p-4">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="update_profil" value="1">
                            <input type="file" name="foto_profil" id="foto_profil_hidden" accept="image/*" style="display:none" onchange="previewPhoto(this)">
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                    <small class="text-muted">Username tidak dapat diubah</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                        <input type="text" name="nama_lengkap" class="form-control" value="<?php echo htmlspecialchars($user['nama_lengkap']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Nomor HP</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                        <input type="tel" name="no_hp" class="form-control" value="<?php echo htmlspecialchars($user['no_hp'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4">
                                <button type="submit" class="btn btn-save btn-primary text-white">
                                    <i class="fas fa-save me-2"></i>Simpan Perubahan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Change Password Form -->
                <div class="info-card">
                    <div class="section-header">
                        <h6 class="fw-bold mb-0"><i class="fas fa-lock me-2 text-warning"></i>Ubah Password</h6>
                    </div>
                    <div class="p-4">
                        <form method="POST">
                            <input type="hidden" name="ubah_password" value="1">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Password Lama</label>
                                    <input type="password" name="old_password" class="form-control" placeholder="Masukkan password saat ini" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Password Baru</label>
                                    <input type="password" name="new_password" class="form-control" placeholder="Minimal 6 karakter" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Konfirmasi Password Baru</label>
                                    <input type="password" name="confirm_new" class="form-control" placeholder="Ulangi password baru" required>
                                </div>
                            </div>
                            <div class="mt-4">
                                <button type="submit" class="btn btn-warning text-white rounded-pill px-4 fw-600">
                                    <i class="fas fa-key me-2"></i>Ubah Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function previewPhoto(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = e => document.getElementById('avatarPreview').src = e.target.result;
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>
