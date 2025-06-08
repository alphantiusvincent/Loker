<?php
require_once "koneksi.php";
session_start();

// Periksa apakah pengguna adalah perusahaan yang login
$isLoggedIn = $_SESSION['is_logged_in'] ?? false;
$userType = $_SESSION['user_type'] ?? '';
$profileId = $_SESSION['profile_id'] ?? null; // Ini adalah perusahaan_id

if (!$isLoggedIn || $userType !== 'perusahaan' || !$profileId) {
    header("Location: login.php?message=" . urlencode("Anda harus login sebagai PERUSAHAAN untuk mengelola lowongan."));
    exit();
}

$lowongan_id = $_GET['id'] ?? null;

if (!$lowongan_id) {
    header("Location: dashboard_perusahaan.php?message=" . urlencode("ID Lowongan tidak diberikan untuk dihapus."));
    exit();
}

$message = '';

// Cek apakah lowongan ini milik perusahaan yang sedang login
$query_check_owner = "SELECT perusahaan_id FROM lowongan WHERE lowongan_id = ?";
$stmt_check_owner = mysqli_prepare($conn, $query_check_owner);
mysqli_stmt_bind_param($stmt_check_owner, "i", $lowongan_id);
mysqli_stmt_execute($stmt_check_owner);
mysqli_stmt_bind_result($stmt_check_owner, $owner_perusahaan_id);
mysqli_stmt_fetch($stmt_check_owner);
mysqli_stmt_close($stmt_check_owner);

if ($owner_perusahaan_id != $profileId) {
    header("Location: dashboard_perusahaan.php?message=" . urlencode("Anda tidak memiliki izin untuk menghapus lowongan ini."));
    exit();
}

// Cek apakah ada pelamar untuk lowongan ini
$query_check_pelamar = "SELECT COUNT(*) FROM lamaran WHERE lowongan_id = ?";
$stmt_check_pelamar = mysqli_prepare($conn, $query_check_pelamar);
mysqli_stmt_bind_param($stmt_check_pelamar, "i", $lowongan_id);
mysqli_stmt_execute($stmt_check_pelamar);
mysqli_stmt_bind_result($stmt_check_pelamar, $jumlah_pelamar);
mysqli_stmt_fetch($stmt_check_pelamar);
mysqli_stmt_close($stmt_check_pelamar);

if ($jumlah_pelamar > 0) {
    // Kriteria: Jika lowongan sudah ada pelamar, maka tidak dapat dihapus
    $message = "error|Lowongan tidak dapat dihapus karena sudah ada " . $jumlah_pelamar . " pelamar. Anda bisa mengubah statusnya menjadi nonaktif.";
    header("Location: dashboard_perusahaan.php?message=" . urlencode($message));
    exit();
}

// Jika tidak ada pelamar, lanjutkan proses hapus
$query_delete = "DELETE FROM lowongan WHERE lowongan_id = ?";
$stmt_delete = mysqli_prepare($conn, $query_delete);
if ($stmt_delete) {
    mysqli_stmt_bind_param($stmt_delete, "i", $lowongan_id);
    if (mysqli_stmt_execute($stmt_delete)) {
        $message = "success|Lowongan berhasil dihapus.";
    } else {
        $message = "error|Gagal menghapus lowongan: " . mysqli_error($conn);
        error_log("SQL Error (delete_lowongan.php): " . mysqli_error($conn));
    }
    mysqli_stmt_close($stmt_delete);
} else {
    $message = "error|Gagal menyiapkan statement hapus lowongan.";
    error_log("Prepare statement error (delete_lowongan.php): " . mysqli_error($conn));
}

header("Location: dashboard_perusahaan.php?message=" . urlencode($message));
exit();
?>
