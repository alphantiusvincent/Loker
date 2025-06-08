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
        $query = "SELECT user_id, username, password, user_type, email FROM users WHERE username = ?";
        $stmt = mysqli_prepare($conn, $query);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if ($user && $password === $user['password']) { // PENTING: Bandingkan plain text password (untuk demo)
                $_SESSION['is_logged_in'] = true;
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_type'] = $user['user_type'];
                $_SESSION['email'] = $user['email']; // Simpan email ke sesi

                $profile_id = null;
                if ($user['user_type'] === 'pencari_kerja') {
                    $query_profile = "SELECT pencari_id, nama_lengkap FROM pencari_kerja WHERE user_id = ?";
                    $stmt_profile = mysqli_prepare($conn, $query_profile);
                    if ($stmt_profile) {
                        mysqli_stmt_bind_param($stmt_profile, "i", $user['user_id']);
                        mysqli_stmt_execute($stmt_profile);
                        $result_profile = mysqli_stmt_get_result($stmt_profile);
                        $profile_data = mysqli_fetch_assoc($result_profile);
                        mysqli_stmt_close($stmt_profile);
                        
                        if ($profile_data) {
                            $profile_id = $profile_data['pencari_id'];
                            $_SESSION['profile_id'] = $profile_id; // Simpan profile_id ke sesi
                            $_SESSION['nama_lengkap'] = $profile_data['nama_lengkap']; // Simpan nama lengkap ke sesi
                        } else {
                            $message = "error|Profil pencari kerja tidak ditemukan untuk user ini. Silakan daftar ulang atau hubungi admin.";
                        }
                    } else {
                        $message = "error|Gagal menyiapkan statement profil pencari kerja.";
                    }
                } elseif ($user['user_type'] === 'perusahaan') {
                    $query_profile = "SELECT perusahaan_id, nama_perusahaan FROM perusahaan WHERE user_id = ?";
                    $stmt_profile = mysqli_prepare($conn, $query_profile);
                    if ($stmt_profile) {
                        mysqli_stmt_bind_param($stmt_profile, "i", $user['user_id']);
                        mysqli_stmt_execute($stmt_profile);
                        $result_profile = mysqli_stmt_get_result($stmt_profile);
                        $profile_data = mysqli_fetch_assoc($result_profile);
                        mysqli_stmt_close($stmt_profile);

                        if ($profile_data) {
                            $profile_id = $profile_data['perusahaan_id'];
                            $_SESSION['profile_id'] = $profile_id; // Simpan profile_id ke sesi
                            $_SESSION['nama_perusahaan'] = $profile_data['nama_perusahaan']; // Simpan nama perusahaan ke sesi
                        } else {
                            $message = "error|Profil perusahaan tidak ditemukan untuk user ini. Silakan daftar ulang atau hubungi admin.";
                        }
                    } else {
                        $message = "error|Gagal menyiapkan statement profil perusahaan.";
                    }
                }

                if (!empty($message)) { // Jika ada error profil, jangan redirect
                     // Tampilkan error message, lalu biarkan form login tetap tampil
                } else {
                    $message = "success|Login berhasil! Selamat datang, " . htmlspecialchars($user['username']) . "!";
                    // === PERUBAHAN REDIRECT DI SINI ===
                    if ($user['user_type'] === 'perusahaan') {
                        header("Location: dashboard_perusahaan.php?message=" . urlencode("Login berhasil! Selamat datang, " . htmlspecialchars($_SESSION['nama_perusahaan']) . "!")); // Redirect ke dashboard perusahaan
                    } else {
                        header("Location: index.php?message=" . urlencode("Login berhasil! Selamat datang, " . htmlspecialchars($_SESSION['username']) . "!")); // Redirect ke halaman utama
                    }
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

$feedback_text_for_alert = '';
if (isset($_GET['message'])) {
    $feedback_text_for_alert = $_GET['message'];
} elseif (isset($message)) {
    $parts = explode('|', $message, 2);
    $feedback_text_for_alert = $parts[1] ?? $parts[0];
}

$feedback_script = '';
if (!empty($feedback_text_for_alert)) {
    $feedback_script = "<script>alert('" . htmlspecialchars($feedback_text_for_alert) . "');</script>";
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
<?= $feedback_script ?>
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
    document.addEventListener('DOMContentLoaded', function() {
        const userTypeSelect = document.getElementById('userType');
        const loginForm = document.getElementById('loginForm');
        
        // Hapus event listener submit lama jika ada (pastikan hanya ada satu submit handler)
        if (loginForm) {
            // Jika ada event listener JS sebelumnya yang terdaftar, hapus di sini
            // Namun, karena sekarang form di-submit via PHP POST, event listener JS sebelumnya tidak relevan
            // dan tidak perlu dihapus secara eksplisit kecuali Anda punya JS lain di sini.
        }

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
        localStorage.removeItem('nama_lengkap'); // Hapus juga ini
        localStorage.removeItem('nama_perusahaan'); // Hapus juga ini
    });
</script>

</body>
</html>