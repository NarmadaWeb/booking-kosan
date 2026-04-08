<?php
require_once __DIR__ . '/../../config/database.php';

class AuthController {
    private $conn;

    public function __construct($db_conn) {
        $this->conn = $db_conn;
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function register($username, $password, $role = 'penyewa') {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        
        // Check if username exists
        $stmt = $this->conn->prepare("SELECT id_pengguna FROM pengguna WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            return ['status' => false, 'message' => 'Username sudah digunakan'];
        }

        // Insert new user - nama_lengkap defaults to username if not provided
        $stmt = $this->conn->prepare("INSERT INTO pengguna (username, nama_lengkap, email, kata_sandi, peran) VALUES (?, ?, ?, ?, ?)");
        $email = $username . '@user.com'; // placeholder email
        $stmt->bind_param("sssss", $username, $username, $email, $hashed_password, $role);
        
        if ($stmt->execute()) {
            return ['status' => true, 'message' => 'Registrasi berhasil'];
        } else {
            return ['status' => false, 'message' => 'Error: ' . $this->conn->error];
        }
    }

    public function login($username, $password) {
        $stmt = $this->conn->prepare("SELECT id_pengguna, username, kata_sandi, peran FROM pengguna WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['kata_sandi'])) {
                $_SESSION['user_id']  = $user['id_pengguna'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = $user['peran'];
                return ['status' => true, 'role' => $user['peran']];
            }
        }
        return ['status' => false, 'message' => 'Username atau password salah'];
    }

    public function logout() {
        session_unset();
        session_destroy();
    }
}
?>
