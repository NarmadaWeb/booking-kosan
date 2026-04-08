<?php
require_once '../src/Auth/AuthController.php';

$message = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username     = trim($_POST['username']);
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $email        = trim($_POST['email']);
    $no_hp        = trim($_POST['no_hp']);
    $password     = $_POST['password'];
    $confirm_pass = $_POST['confirm_password'];

    if ($password !== $confirm_pass) {
        $message = "Password tidak cocok!";
    } elseif (strlen($password) < 6) {
        $message = "Password minimal 6 karakter.";
    } else {
        // Check email & username uniqueness
        $cek = $conn->prepare("SELECT id_pengguna FROM pengguna WHERE username = ? OR email = ?");
        $cek->bind_param("ss", $username, $email);
        $cek->execute();
        if ($cek->get_result()->num_rows > 0) {
            $message = "Username atau email sudah terdaftar.";
        } else {
            $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $conn->prepare("INSERT INTO pengguna (username, nama_lengkap, email, no_hp, kata_sandi, peran) VALUES (?, ?, ?, ?, ?, 'penyewa')");
            $stmt->bind_param("sssss", $username, $nama_lengkap, $email, $no_hp, $hashed);
            if ($stmt->execute()) {
                header("Location: login.php?registered=1");
                exit();
            } else {
                $message = "Terjadi kesalahan. Silakan coba lagi.";
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
    <title>Daftar Akun - Kos Berkah Malika</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Poppins', sans-serif; }
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .register-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.2);
            overflow: hidden;
            width: 100%;
            max-width: 500px;
        }
        .card-header-custom {
            background: linear-gradient(135deg, #667eea, #764ba2);
            padding: 2.5rem 2rem 2rem;
            text-align: center;
            color: white;
        }
        .card-header-custom .brand-icon {
            width: 70px;
            height: 70px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.8rem;
        }
        .card-body-custom { padding: 2rem; }
        .form-control, .form-select {
            border-radius: 12px;
            padding: 0.75rem 1rem;
            border: 2px solid #e9ecef;
            transition: all 0.3s;
            font-size: 0.9rem;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15);
        }
        .input-group-text {
            border-radius: 12px 0 0 12px;
            border: 2px solid #e9ecef;
            border-right: 0;
            background: #f8f9fa;
            color: #6c757d;
        }
        .input-group .form-control { border-radius: 0 12px 12px 0; border-left: 0; }
        .input-group:focus-within .input-group-text { border-color: #667eea; }
        .btn-register {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            border-radius: 12px;
            padding: 0.85rem;
            font-weight: 600;
            font-size: 1rem;
            letter-spacing: 0.5px;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        .btn-register:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(102, 126, 234, 0.5); }
        .form-label { font-weight: 500; font-size: 0.85rem; color: #555; }
        .divider { height: 1px; background: linear-gradient(to right, transparent, #dee2e6, transparent); margin: 1.5rem 0; }
    </style>
</head>
<body>
    <div class="register-card">
        <div class="card-header-custom">
            <div class="brand-icon"><i class="fas fa-home"></i></div>
            <h4 class="fw-bold mb-1">Kos Berkah Malika</h4>
            <p class="mb-0 opacity-75 small">Buat akun penyewa baru</p>
        </div>

        <div class="card-body-custom">
            <?php if ($message): ?>
                <div class="alert alert-danger rounded-3 border-0 small" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <div class="row g-3">
                    <!-- Username -->
                    <div class="col-12">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" name="username" class="form-control" placeholder="Masukkan username unik" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <!-- Nama Lengkap -->
                    <div class="col-12">
                        <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                            <input type="text" name="nama_lengkap" class="form-control" placeholder="Nama sesuai KTP" value="<?php echo htmlspecialchars($_POST['nama_lengkap'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="col-12">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" name="email" class="form-control" placeholder="contoh@email.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <!-- No HP -->
                    <div class="col-12">
                        <label class="form-label">Nomor HP</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                            <input type="tel" name="no_hp" class="form-control" placeholder="08xxxxxxxxxx" value="<?php echo htmlspecialchars($_POST['no_hp'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="col-12"><div class="divider"></div></div>

                    <!-- Password -->
                    <div class="col-12">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" name="password" id="password" class="form-control" placeholder="Minimal 6 karakter" required>
                        </div>
                    </div>

                    <!-- Konfirmasi Password -->
                    <div class="col-12">
                        <label class="form-label">Konfirmasi Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Ulangi password" required>
                        </div>
                        <div id="passMatch" class="small mt-1 d-none"></div>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-register btn-primary w-100 text-white">
                        <i class="fas fa-user-plus me-2"></i>Daftar Sekarang
                    </button>
                </div>

                <div class="text-center mt-4">
                    <span class="text-muted small">Sudah punya akun?</span>
                    <a href="login.php" class="text-decoration-none fw-bold ms-1 small" style="color:#667eea">Masuk di sini</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        const p1 = document.getElementById('password');
        const p2 = document.getElementById('confirm_password');
        const msg = document.getElementById('passMatch');
        
        function checkPass() {
            if (p2.value.length === 0) { msg.classList.add('d-none'); return; }
            msg.classList.remove('d-none');
            if (p1.value === p2.value) {
                msg.innerHTML = '<i class="fas fa-check-circle text-success me-1"></i><span class="text-success">Password cocok</span>';
            } else {
                msg.innerHTML = '<i class="fas fa-times-circle text-danger me-1"></i><span class="text-danger">Password tidak cocok</span>';
            }
        }
        p1.addEventListener('input', checkPass);
        p2.addEventListener('input', checkPass);
    </script>
</body>
</html>
