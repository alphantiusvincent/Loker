<?php
require_once "koneksi.php";
session_start();

// Periksa apakah pengguna adalah perusahaan yang login
$isLoggedIn = $_SESSION['is_logged_in'] ?? false;
$username = $_SESSION['username'] ?? '';
$userType = $_SESSION['user_type'] ?? '';
$profileId = $_SESSION['profile_id'] ?? null; // Ini adalah perusahaan_id
$namaPerusahaan = $_SESSION['nama_perusahaan'] ?? ''; // Nama perusahaan dari sesi login

// Jika tidak login sebagai perusahaan, arahkan ke halaman login
if (!$isLoggedIn || $userType !== 'perusahaan' || !$profileId) {
    header("Location: login.php?message=" . urlencode("Anda harus login sebagai PERUSAHAAN untuk mengakses dashboard."));
    exit();
}

// Inisialisasi pesan feedback
$feedback_message = '';
if (isset($_GET['message'])) {
    $feedback_message = "<script>alert('" . htmlspecialchars($_GET['message']) . "');</script>";
}

// Ambil semua lowongan yang diposting oleh perusahaan ini, beserta jumlah pelamar
$lowongan_perusahaan = [];
$query_lowongan = "SELECT 
                        l.lowongan_id, 
                        l.judul, 
                        l.tanggal_posting, 
                        l.tanggal_deadline,
                        l.status,
                        COUNT(lam.lamaran_id) AS jumlah_pelamar
                    FROM lowongan l
                    LEFT JOIN lamaran lam ON l.lowongan_id = lam.lowongan_id
                    WHERE l.perusahaan_id = ?
                    GROUP BY l.lowongan_id
                    ORDER BY l.tanggal_posting DESC";

$stmt_lowongan = mysqli_prepare($conn, $query_lowongan);
if ($stmt_lowongan) {
    mysqli_stmt_bind_param($stmt_lowongan, "i", $profileId);
    mysqli_stmt_execute($stmt_lowongan);
    $result_lowongan = mysqli_stmt_get_result($stmt_lowongan);
    while ($row = mysqli_fetch_assoc($result_lowongan)) {
        $lowongan_perusahaan[] = $row;
    }
    mysqli_stmt_close($stmt_lowongan);
} else {
    error_log("Error preparing lowongan query (dashboard_perusahaan.php): " . mysqli_error($conn));
    // Tampilkan pesan error ke pengguna jika terjadi masalah query
    $feedback_message = "<script>alert('Error: Gagal memuat daftar lowongan perusahaan.');</script>";
}

// Hitung total lowongan
$total_lowongan = count($lowongan_perusahaan);

// Hitung total pelamar (semua pelamar untuk semua lowongan perusahaan ini)
$total_pelamar = 0;
foreach ($lowongan_perusahaan as $lowongan) {
    $total_pelamar += $lowongan['jumlah_pelamar'];
}

// Tambahkan query untuk jumlah pelamar yang 'diterima' (opsional, jika ingin metrik lebih spesifik)
// Misalnya:
// $total_pelamar_diterima = 0;
// $query_diterima = "SELECT COUNT(lamaran_id) FROM lamaran WHERE lowongan_id IN (SELECT lowongan_id FROM lowongan WHERE perusahaan_id = ?) AND status = 'diterima'";
// $stmt_diterima = mysqli_prepare($conn, $query_diterima);
// if ($stmt_diterima) {
//     mysqli_stmt_bind_param($stmt_diterima, "i", $profileId);
//     mysqli_stmt_execute($stmt_diterima);
//     mysqli_stmt_bind_result($stmt_diterma, $count_diterima);
//     mysqli_stmt_fetch($stmt_diterma);
//     mysqli_stmt_close($stmt_diterma);
//     $total_pelamar_diterima = $count_diterima;
// }
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Perusahaan | BawaMap</title>
  <link rel="stylesheet" href="styles.css">
  <link rel="icon" href="Image/logo-BM.png" type="image/x-icon">
  <style>
    /* Styling tambahan untuk dashboard */
    .dashboard-ringkasan {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    .dashboard-card {
        background-color: var(--warna-latar);
        padding: 1.5rem;
        border-radius: var(--radius);
        box-shadow: var(--bayangan);
        text-align: center;
    }
    .dashboard-card h3 {
        font-size: 1.125rem;
        color: var(--warna-teks-sekunder);
        margin-bottom: 0.5rem;
    }
    .dashboard-card p {
        font-size: 2.5rem;
        font-weight: 700;
        color: var(--warna-primer);
    }
    .tabel-lowongan-dashboard {
        width: 100%;
        border-collapse: collapse;
        margin-top: 2rem;
    }
    .tabel-lowongan-dashboard th, .tabel-lowongan-dashboard td {
        border: 1px solid var(--warna-border);
        padding: 0.75rem;
        text-align: left;
    }
    .tabel-lowongan-dashboard th {
        background-color: var(--warna-sekunder);
        font-weight: 600;
        color: var(--warna-teks);
    }
    .tabel-lowongan-dashboard tr:nth-child(even) {
        background-color: #f9f9f9;
    }
    .tabel-lowongan-dashboard td.aksi {
        white-space: nowrap;
    }
    .tabel-lowongan-dashboard .btn-aksi {
        padding: 0.4rem 0.8rem;
        border-radius: var(--radius);
        font-size: 0.875rem;
        text-decoration: none;
        margin-right: 0.5rem;
        display: inline-block; /* Untuk tombol yang berada di baris yang sama */
        margin-bottom: 0.25rem; /* Jarak antar tombol di mobile */
    }
    .btn-edit { background-color: var(--warna-info); color: white; }
    .btn-delete { background-color: var(--warning); color: white; }
    .btn-view-applicants { background-color: var(--warna-sukses); color: white; }
    .btn-edit:hover, .btn-delete:hover, .btn-view-applicants:hover {
        opacity: 0.9;
    }
    @media (max-width: 768px) {
        .tabel-lowongan-dashboard, .tabel-lowongan-dashboard thead, .tabel-lowongan-dashboard tbody, .tabel-lowongan-dashboard th, .tabel-lowongan-dashboard td, .tabel-lowongan-dashboard tr {
            display: block;
        }
        .tabel-lowongan-dashboard thead tr {
            position: absolute;
            top: -9999px;
            left: -9999px;
        }
        .tabel-lowongan-dashboard tr { border: 1px solid var(--warna-border); margin-bottom: 1rem; }
        .tabel-lowongan-dashboard td {
            border: none;
            border-bottom: 1px solid #eee;
            position: relative;
            padding-left: 50%;
            text-align: right;
        }
        .tabel-lowongan-dashboard td:before {
            position: absolute;
            top: 6px;
            left: 6px;
            width: 45%;
            padding-right: 10px;
            white-space: nowrap;
            content: attr(data-label);
            font-weight: bold;
            text-align: left;
        }
        .tabel-lowongan-dashboard td.aksi {
            text-align: left; /* Biarkan aksi sejajar kiri di mobile */
        }
    }
  </style>
</head>
<body>
  <?= $feedback_message ?>
  <header class="navigasi">
    <div class="container">
      <div class="merek-navigasi">
        <div class="kepala-kartu">
          <div class="logo-perusahaan1">
            <img src="Image/logo-BM.png" alt="logo-BM">
          </div>
          <a href="index.php"><h1>BawaMap</h1></a>
        </div>
      </div>
      <nav class="menu-navigasi">
        <ul>
          <li><a href="index.php">Beranda</a></li>
          <li><a href="#">Kategori</a></li>
          <li><a href="#">Perusahaan</a></li>
          <li><a href="#">Tentang Kami</a></li>
        </ul>
      </nav>
      <div class="otentikasi-navigasi" style="display: none;"> <a href="login.php"><button class="tombol-masuk">Masuk</button></a>
        <a href="register.php"><button class="tombol-daftar">Daftar</button></a>
      </div>
      <div class="selamat-datang-user" style="display: flex;"> <span id="welcome-message">👋 Selamat Datang Kembali, <b><?= htmlspecialchars($namaPerusahaan) ?></b>!</span>
        <button class="tombol-logout" id="logout-button">Logout</button>
        <a href="tambah_lowongan.php">
          <button class="tombol-tambah-lowongan">Tambah Lowongan</button>
        </a>
        <a href="dashboard_perusahaan.php">
            <button class="tombol-dashboard">Dashboard</button>
        </a>
      </div>
    </div>
  </header>

  <main>
    <div class="container">
        <h2>Dashboard Perusahaan</h2>
        <p>Selamat datang di dashboard Anda, <?= htmlspecialchars($namaPerusahaan) ?>. Di sini Anda bisa mengelola lowongan pekerjaan Anda.</p>

        <div class="dashboard-ringkasan">
            <div class="dashboard-card">
                <h3>Total Lowongan Diposting</h3>
                <p><?= $total_lowongan ?></p>
            </div>
            <div class="dashboard-card">
                <h3>Total Pelamar Keseluruhan</h3>
                <p><?= $total_pelamar ?></p>
            </div>
            </div>

        <h3>Lowongan Anda</h3>
        <?php if (!empty($lowongan_perusahaan)) : ?>
            <table class="tabel-lowongan-dashboard">
                <thead>
                    <tr>
                        <th>Judul Lowongan</th>
                        <th>Tanggal Posting</th>
                        <th>Deadline</th>
                        <th>Status</th>
                        <th>Pelamar</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lowongan_perusahaan as $lowongan) : ?>
                        <tr>
                            <td data-label="Judul Lowongan"><?= htmlspecialchars($lowongan['judul']) ?></td>
                            <td data-label="Tanggal Posting"><?= date("d M Y", strtotime($lowongan['tanggal_posting'])) ?></td>
                            <td data-label="Deadline"><?= date("d M Y", strtotime($lowongan['tanggal_deadline'])) ?></td>
                            <td data-label="Status"><?= htmlspecialchars(ucfirst($lowongan['status'])) ?></td>
                            <td data-label="Pelamar"><?= htmlspecialchars($lowongan['jumlah_pelamar']) ?></td>
                            <td class="aksi">
                                <a href="edit_lowongan.php?id=<?= htmlspecialchars($lowongan['lowongan_id']) ?>" class="btn-aksi btn-edit">Edit</a>
                                <a href="delete_lowongan.php?id=<?= htmlspecialchars($lowongan['lowongan_id']) ?>" class="btn-aksi btn-delete" onclick="return confirm('Apakah Anda yakin ingin menghapus lowongan \'<?= htmlspecialchars($lowongan['judul']) ?>\'? Ini akan menghapus semua lamaran terkait!');">Hapus</a>
                                <a href="daftar_pelamar.php?lowongan_id=<?= htmlspecialchars($lowongan['lowongan_id']) ?>" class="btn-aksi btn-view-applicants">Lihat Pelamar (<?= htmlspecialchars($lowongan['jumlah_pelamar']) ?>)</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p>Anda belum memposting lowongan pekerjaan apa pun.</p>
            <p><a href="tambah_lowongan.php">Tambahkan lowongan pertama Anda sekarang!</a></p>
        <?php endif; ?>

    </div>
  </main>

  <footer class="footer">
    <div class="container">
      <div class="konten-footer">
        <div class="tentang-footer">
          <h3>BawaMap</h3>
          <p>Platform pencarian kerja terbaik di Indonesia. Menghubungkan pencari kerja dengan perusahaan terkemuka.</p>
        </div>
        <div class="tautan-footer">
          <div class="kolom-footer">
            <h4>Menu</h4>
            <ul>
              <li><a href="index.php">Beranda</a></li>
              <li><a href="#">Lowongan</a></li>
              <li><a href="#">Perusahaan</a></li>
              <li><a href="#">Tips Karir</a></li>
            </ul>
          </div>
          <div class="kolom-footer">
            <h4>Kategori</h4>
            <ul>
              <li><a href="#">IT & Teknologi</a></li>
              <li><a href="#">Keuangan</a></li>
              <li><a href="#">Pendidikan</a></li>
              <li><a href="#">Kesehatan</a></li>
              <li><a href="#">Pemasaran</a></li>
            </ul>
          </div>
          <div class="kolom-footer">
            <h4>Info Perusahaan</h4>
            <ul>
              <li><a href="#">Tentang Kami</a></li>
              <li><a href="#">Hubungi Kami</a></li>
              <li><a href="#">Kebijakan Privasi</a></li>
              <li><a href="#">Syarat & Ketentuan</a></li>
            </ul>
          </div>
        </div>
      </div>
      <div class="footer-bawah">
        <p>&copy; www.BawaMap.co.id</p>
      </div>
    </div>
  </footer>
<?php
// koneksi.php
$db_host = "localhost"; // Biasanya 'localhost' untuk XAMPP
$db_user = "root";      // <<< GANTI DENGAN USER DATABASE ANDA
$db_pass = "";          // <<< GANTI DENGAN PASSWORD DATABASE ANDA (kosong jika default XAMPP/WAMP)
$db_name = "bawamap";   // Nama database Anda

// Buat koneksi
$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

// Periksa koneksi
if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Opsional: Atur charset menjadi utf8mb4
mysqli_set_charset($conn, "utf8mb4");
?>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Logic untuk logout
      const logoutButton = document.getElementById('logout-button');
      if (logoutButton) {
        logoutButton.addEventListener('click', function() {
          window.location.href = 'logout_process.php'; 
        });
      }
    });
  </script>
</body>
</html>