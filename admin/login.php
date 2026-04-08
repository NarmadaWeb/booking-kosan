<?php
require_once '../config/database.php';
require_once '../src/Auth/AuthController.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth = new AuthController($conn);
    $result = $auth->login($_POST['username'], $_POST['password']);
    
    if ($result['status']) {
        if ($result['role'] === 'admin') {
            header("Location: index.php");
            exit();
        } else {
            $message = "Access Denied: You are not an Admin.";
            session_destroy(); // Logout immediately if not admin
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
    <title>Login Admin - Berkah Malika</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4f46e5;
            --primary-hover: #4338ca;
            --bg-gradient: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        }

        * { font-family: 'Poppins', sans-serif; }

        body {
            background: var(--bg-gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            overflow: hidden;
            position: relative;
        }

        /* Background Shapes */
        .shape {
            position: absolute;
            filter: blur(80px);
            z-index: 0;
            opacity: 0.4;
        }
        .shape-1 {
            width: 400px;
            height: 400px;
            background: #4f46e5;
            top: -100px;
            left: -100px;
            border-radius: 50%;
        }
        .shape-2 {
            width: 300px;
            height: 300px;
            background: #7c3aed;
            bottom: -50px;
            right: -50px;
            border-radius: 50%;
        }

        .login-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 420px;
            padding: 20px;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 2.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .brand-logo {
            width: 64px;
            height: 64px;
            background: var(--primary-color);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 1.5rem;
            color: white;
            box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.4);
        }

        .login-header h4 {
            color: white;
            font-weight: 700;
            text-align: center;
            margin-bottom: 0.5rem;
        }

        .login-header p {
            color: rgba(255, 255, 255, 0.5);
            text-align: center;
            font-size: 0.875rem;
            margin-bottom: 2rem;
        }

        .form-label {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        .input-group-custom {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .input-group-custom i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.4);
            transition: 0.3s;
        }

        .form-control {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 0.75rem 1rem 0.75rem 2.8rem;
            color: white;
            transition: 0.3s;
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.2);
            color: white;
        }

        .form-control:focus + i {
            color: var(--primary-color);
        }

        .btn-login {
            background: var(--primary-color);
            border: none;
            border-radius: 12px;
            padding: 0.75rem;
            font-weight: 600;
            color: white;
            width: 100%;
            margin-top: 1rem;
            transition: 0.3s;
            box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2);
        }

        .btn-login:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.4);
        }

        .alert-custom {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #f87171;
            border-radius: 12px;
            padding: 0.75rem;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
        }

        .footer-links {
            text-align: center;
            margin-top: 1.5rem;
        }

        .footer-links a {
            color: rgba(255, 255, 255, 0.4);
            text-decoration: none;
            font-size: 0.8125rem;
            transition: 0.2s;
        }

        .footer-links a:hover {
            color: white;
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.4);
            cursor: pointer;
            z-index: 10;
        }
    </style>
</head>
<body>
    <div class="shape shape-1"></div>
    <div class="shape shape-2"></div>

    <div class="login-container">
        <div class="glass-card">
            <div class="brand-logo" style="overflow: hidden; background: white;">
                <img src="../public/image/logo1.png" alt="Logo" style="width: 100%; height: 100%; object-fit: contain;" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                <i class="fa-solid fa-user-shield" style="display: none; color: var(--primary-color);"></i>
            </div>
            
            <div class="login-header">
                <h4>Administrator</h4>
                <p>Silakan masuk ke panel kontrol</p>
            </div>

            <?php if ($message): ?>
                <div class="alert-custom">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <div class="input-group-custom">
                        <input type="text" name="username" class="form-control" autocomplete="off" required>
                        <i class="fas fa-user-gear"></i>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Kata Sandi</label>
                    <div class="input-group-custom">
                        <input type="password" name="password" id="passwordInput" class="form-control" required>
                        <i class="fas fa-lock"></i>
                        <span class="password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </span>
                    </div>
                </div>

                <button type="submit" class="btn btn-login">
                    Masuk Sekarang <i class="fas fa-arrow-right ms-2"></i>
                </button>
            </form>

            <div class="footer-links">
                <a href="../users/login.php"><i class="fas fa-users me-1"></i> Login sebagai Pengguna Umum</a>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('passwordInput');
            const icon = document.getElementById('toggleIcon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
