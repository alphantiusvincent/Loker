<?php
require_once "koneksi.php";
session_start();

$message = ''; // Untuk pesan sukses/gagal login

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $message = "error|Username dan password harus diisi.";
    } else {
        // Prepared statement untuk mencari user
        $query = "SELECT user_id, username, password, user_type FROM users WHERE username = ?";
        $stmt = mysqli_prepare($conn, $query);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if ($user && $password === $user['password']) { // PENTING: Bandingkan plain text password (untuk demo)
                // Login berhasil
                $_SESSION['is_logged_in'] = true;
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_type'] = $user['user_type'];

                // Dapatkan profile_id (pencari_id atau perusahaan_id)
                $profile_id = null;
                if ($user['user_type'] === 'pencari_kerja') {
                    $query_profile = "SELECT pencari_id FROM pencari_kerja WHERE user_id = ?";
                } elseif ($user['user_type'] === 'perusahaan') {
                    $query_profile = "SELECT perusahaan_id FROM perusahaan WHERE user_id = ?";
                }

                if (isset($query_profile)) {
                    $stmt_profile = mysqli_prepare($conn, $query_profile);
                    if ($stmt_profile) {
                        mysqli_stmt_bind_param($stmt_profile, "i", $user['user_id']);
                        mysqli_stmt_execute($stmt_profile);
                        $result_profile = mysqli_stmt_get_result($stmt_profile);
                        $profile_data = mysqli_fetch_assoc($result_profile);
                        mysqli_stmt_close($stmt_profile);
                        
                        if ($profile_data) {
                            $profile_id = ($user['user_type'] === 'pencari_kerja') ? $profile_data['pencari_id'] : $profile_data['perusahaan_id'];
                            $_SESSION['profile_id'] = $profile_id; // Simpan profile_id ke sesi
                        } else {
                            $message = "error|Profil pengguna tidak ditemukan. Silakan hubungi admin.";
                        }
                    } else {
                        $message = "error|Gagal menyiapkan statement profil.";
                    }
                }

                if (!empty($message)) { // Jika ada error profil, jangan redirect
                     // Tampilkan error message
                } else {
                    $message = "success|Login berhasil! Selamat datang, " . htmlspecialchars($user['username']) . "!";
                    // Redirect ke index.php
                    header("Location: index.php");
                    exit();
                }

            } else {
                $message = "error|Username atau password salah.";
            }
        } else {
            $message = "error|Gagal menyiapkan statement login.";
            error_log("Prepare statement error (login.php): " . mysqli_error($conn));
        }
    }
}

// Pesan feedback (sukses/gagal) dari pengiriman form
$feedback_message = '';
if (isset($_GET['message'])) { // Ambil pesan dari URL redirect
    $msg = $_GET['message'];
    if (strpos($msg, "Anda harus login") !== false || strpos($msg, "gagal") !== false) {
        $feedback_message = "<script>alert('" . htmlspecialchars($msg) . "');</script>";
    } else {
        $feedback_message = "<script>alert('" . htmlspecialchars($msg) . "');</script>";
    }
} elseif (isset($message)) { // Ambil pesan dari POST request
    list($type, $text) = explode('|', $message, 2);
    $feedback_message = "<script>alert('" . htmlspecialchars($text) . "');</script>";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="login.css">
</head>
<body>
<?= $feedback_message ?>
<div class="login-container">
    <h2>Login</h2>
    <label for="userType">Login sebagai:</label>
    <select id="userType">
        <option value="jobseeker">Pencari Kerja</option>
        <option value="company">Perusahaan</option>
    </select>
    
    <div id="jobSeekerForm">
        <form id="loginForm" action="login.php" method="POST">
            <div class="form-group">
                <label for="js-username">Username</label>
                <input type="text" id="js-username" name="username" required>
            </div>
            <div class="form-group">
                <label for="js-password">Password</label>
                <input type="password" id="js-password" name="password" required>
            </div>
            <div class="form-group">
                <button type="submit">Login</button>
            </div>
            <div class="form-group" style="margin-top: 15px; text-align: center;">
                <p>Belum punya akun? <a href="register.php">Daftar sekarang</a></p>
            </div>
        </form>
    </div>
</div>

<script>
    // Bagian JavaScript ini hanya untuk interaksi UI (opsi masuk, dll),
    // tidak lagi untuk proses login itu sendiri.
    document.addEventListener('DOMContentLoaded', function() {
        const userTypeSelect = document.getElementById('userType');
        const loginForm = document.getElementById('loginForm'); // Referensi ke form
        
        // Hapus event listener submit lama jika ada
        if (loginForm) {
            loginForm.removeEventListener('submit', /* fungsi submit lama */); 
        }

        // Contoh bagaimana mungkin Anda ingin mengatur form action dinamis
        // Berdasarkan pilihan userType (jika ada form berbeda untuk jobseeker/company)
        // Untuk saat ini, form action sudah statis ke login.php POST.
        userTypeSelect.addEventListener('change', function() {
            // Logika untuk menampilkan/menyembunyikan form tertentu jika ada
            // (saat ini tidak ada form terpisah di HTML, hanya select)
            console.log('Selected user type:', this.value);
        });

        // Kosongkan localStorage saat halaman login dimuat,
        // karena sekarang kita mengandalkan sesi PHP.
        localStorage.removeItem('isLoggedIn');
        localStorage.removeItem('username');
        localStorage.removeItem('userType');
        localStorage.removeItem('userId');
        localStorage.removeItem('profileId');
    });
</script>

</body>
</html>