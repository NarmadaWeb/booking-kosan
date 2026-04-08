<?php
require_once '../src/Auth/AuthController.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth   = new AuthController($conn);
    $result = $auth->login($_POST['username'], $_POST['password']);
    
    if ($result['status']) {
        if ($result['role'] === 'penyewa') {
            header("Location: ../index.php");
            exit();
        } else {
            $message = "Gunakan halaman Login Admin.";
            session_destroy();
        }
    } else {
        $message = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Kos Berkah Malika</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Poppins', sans-serif; }
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .login-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.2);
            overflow: hidden;
            width: 100%;
            max-width: 420px;
        }
        .card-header-custom {
            background: linear-gradient(135deg, #0d6efd, #0b5ed7);
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
        .form-control {
            border-radius: 12px;
            padding: 0.75rem 1rem;
            border: 2px solid #e9ecef;
            transition: all 0.3s;
            font-size: 0.9rem;
        }
        .form-control:focus { border-color: #0d6efd; box-shadow: 0 0 0 4px rgba(13,110,253,0.15); }
        .input-group-text {
            border-radius: 12px 0 0 12px;
            border: 2px solid #e9ecef;
            border-right: 0;
            background: #f8f9fa;
            color: #6c757d;
        }
        .input-group .form-control { border-radius: 0 12px 12px 0; border-left: 0; }
        .input-group:focus-within .input-group-text { border-color: #0d6efd; }
        .btn-login {
            background: linear-gradient(135deg, #0d6efd, #0b5ed7);
            border: none;
            border-radius: 12px;
            padding: 0.85rem;
            font-weight: 600;
            font-size: 1rem;
            box-shadow: 0 4px 15px rgba(13, 110, 253, 0.4);
            transition: all 0.3s;
        }
        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(13, 110, 253, 0.5); }
        .form-label { font-weight: 500; font-size: 0.85rem; color: #555; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="card-header-custom">
            <div class="brand-icon"><i class="fas fa-home"></i></div>
            <h4 class="fw-bold mb-1">Kos Berkah Malika</h4>
            <p class="mb-0 opacity-75 small">Masuk ke akun penyewa Anda</p>
        </div>

        <div class="card-body-custom">
            <?php if (isset($_GET['registered'])): ?>
                <div class="alert alert-success rounded-3 border-0 small">
                    <i class="fas fa-check-circle me-2"></i>Akun berhasil dibuat! Silakan masuk.
                </div>
            <?php endif; ?>
            
            <?php if ($message): ?>
                <div class="alert alert-danger rounded-3 border-0 small">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" name="username" class="form-control" placeholder="Masukkan username" required autofocus>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" name="password" class="form-control" placeholder="Masukkan password" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-login btn-primary w-100 text-white">
                    <i class="fas fa-sign-in-alt me-2"></i>Masuk
                </button>

                <div class="text-center mt-4">
                    <span class="text-muted small">Belum punya akun?</span>
                    <a href="daftar.php" class="text-decoration-none fw-bold ms-1 small" style="color:#0d6efd">Daftar sekarang</a>
                    <div class="mt-2">
                        <a href="../admin/login.php" class="text-muted small text-decoration-none">
                            <i class="fas fa-shield-alt me-1"></i>Login sebagai Admin
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
