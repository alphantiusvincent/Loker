<?php
require_once "koneksi.php";
session_start();

header('Content-Type: application/json'); // Beri tahu browser bahwa responsnya adalah JSON

// Periksa apakah pengguna adalah perusahaan yang login
$isLoggedIn = $_SESSION['is_logged_in'] ?? false;
$userType = $_SESSION['user_type'] ?? '';
$profileId = $_SESSION['profile_id'] ?? null; // Ini adalah perusahaan_id

if (!$isLoggedIn || $userType !== 'perusahaan' || !$profileId) {
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki izin untuk melakukan aksi ini.']);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $lamaran_id = $_POST['lamaran_id'] ?? null;
    $status = $_POST['status'] ?? null;

    if (!$lamaran_id || !$status) {
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap.']);
        exit();
    }

    // Validasi status yang diizinkan
    $allowed_statuses = ['tertunda', 'ditinjau', 'diterima', 'ditolak'];
    if (!in_array($status, $allowed_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Status tidak valid.']);
        exit();
    }

    // Pastikan lamaran ini milik lowongan perusahaan yang sedang login
    $check_ownership_query = "SELECT l.perusahaan_id FROM lamaran lam JOIN lowongan l ON lam.lowongan_id = l.lowongan_id WHERE lam.lamaran_id = ?";
    $stmt_ownership = mysqli_prepare($conn, $check_ownership_query);
    if ($stmt_ownership) {
        mysqli_stmt_bind_param($stmt_ownership, "i", $lamaran_id);
        mysqli_stmt_execute($stmt_ownership);
        mysqli_stmt_bind_result($stmt_ownership, $owner_perusahaan_id);
        mysqli_stmt_fetch($stmt_ownership);
        mysqli_stmt_close($stmt_ownership);

        if ($owner_perusahaan_id != $profileId) {
            echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki izin untuk mengubah status lamaran ini.']);
            exit();
        }
    } else {
        error_log("Error preparing ownership check statement: " . mysqli_error($conn));
        echo json_encode(['success' => false, 'message' => 'Internal server error (ownership check).']);
        exit();
    }

    // Lakukan update status
    $update_query = "UPDATE lamaran SET status = ? WHERE lamaran_id = ?";
    $stmt = mysqli_prepare($conn, $update_query);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "si", $status, $lamaran_id);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => 'Status lamaran berhasil diperbarui.']);
        } else {
            error_log("SQL Error (update_status_lamaran.php): " . mysqli_error($conn));
            echo json_encode(['success' => false, 'message' => 'Gagal memperbarui status di database.']);
        }
        mysqli_stmt_close($stmt);
    } else {
        error_log("Prepare statement error (update_status_lamaran.php): " . mysqli_error($conn));
        echo json_encode(['success' => false, 'message' => 'Internal server error (prepare statement).']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Metode request tidak diizinkan.']);
}
?>