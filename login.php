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
        $query = "SELECT user_id, username, password, user_type FROM users WHERE username = ?";
        $stmt = mysqli_prepare($conn, $query);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if ($user && $password === $user['password']) {
                $_SESSION['is_logged_in'] = true;
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_type'] = $user['user_type'];

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
                            $_SESSION['profile_id'] = $profile_id;
                        } else {
                            $message = "error|Profil pengguna tidak ditemukan. Silakan hubungi admin.";
                        }
                    } else {
                        $message = "error|Gagal menyiapkan statement profil.";
                    }
                }

                if (empty($message)) { // Hanya redirect jika tidak ada error profil
                    $message = "success|Login berhasil! Selamat datang, " . htmlspecialchars($user['username']) . "!";
                    header("Location: index.php?message=" . urlencode($message));
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

// Pesan feedback (sukses/gagal) untuk alert JavaScript
// Ambil pesan dari URL (jika ada) atau dari proses POST
$feedback_text_for_alert = '';
if (isset($_GET['message'])) {
    $feedback_text_for_alert = $_GET['message'];
} elseif (isset($message)) {
    // Jika $message dari POST request, formatnya "type|text"
    $parts = explode('|', $message, 2);
    $feedback_text_for_alert = $parts[1] ?? $parts[0]; // Ambil bagian teks, atau seluruhnya jika tidak ada '|'
}

// Tampilkan alert jika ada pesan
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
<?= $feedback_script ?> <div class="login-container">
    <h2>Login</h2>
    <label for="userType">Login sebagai:</label>
    <select id="userType">
        <option value="jobseeker">Pencari Kerja</option>
        <option value="company">Perusahaan</option>
    </select>
    
    <div id="jobSeekerForm">
        <form id="loginForm" action="login.php" method="POST"> <div class="form-group">
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
    // ... (Sisa JavaScript tetap sama, karena sekarang form di-submit via PHP POST) ...
</script>

</body>
</html>