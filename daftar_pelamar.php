<?php
require_once "koneksi.php";
session_start();

// Periksa apakah pengguna adalah perusahaan yang login
$isLoggedIn = $_SESSION['is_logged_in'] ?? false;
$userType = $_SESSION['user_type'] ?? '';
$profileId = $_SESSION['profile_id'] ?? null; // Ini adalah perusahaan_id

if (!$isLoggedIn || $userType !== 'perusahaan' || !$profileId) {
    header("Location: login.php?message=" . urlencode("Anda harus login sebagai PERUSAHAAN untuk melihat pelamar."));
    exit();
}

$lowongan_id = $_GET['lowongan_id'] ?? null;

if (!$lowongan_id) {
    header("Location: dashboard_perusahaan.php?message=" . urlencode("ID Lowongan tidak diberikan untuk melihat pelamar."));
    exit();
}

// Ambil detail lowongan untuk judul
$lowongan_title = "Lowongan Tidak Ditemukan";
$query_title = "SELECT judul, perusahaan_id FROM lowongan WHERE lowongan_id = ?";
$stmt_title = mysqli_prepare($conn, $query_title);
if ($stmt_title) {
    mysqli_stmt_bind_param($stmt_title, "i", $lowongan_id);
    mysqli_stmt_execute($stmt_title);
    mysqli_stmt_bind_result($stmt_title, $title_db, $owner_perusahaan_id);
    mysqli_stmt_fetch($stmt_title);
    mysqli_stmt_close($stmt_title);

    if ($owner_perusahaan_id != $profileId) {
        header("Location: dashboard_perusahaan.php?message=" . urlencode("Anda tidak memiliki izin untuk melihat pelamar lowongan ini."));
        exit();
    }
    if ($title_db) {
        $lowongan_title = $title_db;
    }
}


// Ambil daftar pelamar untuk lowongan ini
$pelamar_list = [];
$query_pelamar = "SELECT 
                    lam.lamaran_id, 
                    lam.nama_lengkap, 
                    lam.email, 
                    lam.nomor_hp, 
                    lam.cv, 
                    lam.portofolio, 
                    lam.tanggal_lamar,
                    lam.status AS status_lamaran,
                    pj.pendidikan, pj.alamat -- Contoh data tambahan dari pencari_kerja
                  FROM lamaran lam
                  JOIN lowongan l ON lam.lowongan_id = l.lowongan_id
                  LEFT JOIN pencari_kerja pj ON lam.pencari_id = pj.pencari_id
                  WHERE lam.lowongan_id = ? AND l.perusahaan_id = ? -- Pastikan perusahaan_id cocok
                  ORDER BY lam.tanggal_lamar DESC";

$stmt_pelamar = mysqli_prepare($conn, $query_pelamar);
if ($stmt_pelamar) {
    mysqli_stmt_bind_param($stmt_pelamar, "ii", $lowongan_id, $profileId);
    mysqli_stmt_execute($stmt_pelamar);
    $result_pelamar = mysqli_stmt_get_result($stmt_pelamar);
    while ($row = mysqli_fetch_assoc($result_pelamar)) {
        $pelamar_list[] = $row;
    }
    mysqli_stmt_close($stmt_pelamar);
} else {
    error_log("Error preparing pelamar query: " . mysqli_error($conn));
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pelamar untuk <?= htmlspecialchars($lowongan_title) ?> | BawaMap</title>
  <link rel="stylesheet" href="styles.css">
  <link rel="icon" href="Image/logo-BM.png" type="image/x-icon">
  <style>
    .tabel-pelamar-dashboard {
        width: 100%;
        border-collapse: collapse;
        margin-top: 2rem;
    }
    .tabel-pelamar-dashboard th, .tabel-pelamar-dashboard td {
        border: 1px solid var(--warna-border);
        padding: 0.75rem;
        text-align: left;
    }
    .tabel-pelamar-dashboard th {
        background-color: var(--warna-sekunder);
        font-weight: 600;
        color: var(--warna-teks);
    }
    .tabel-pelamar-dashboard tr:nth-child(even) {
        background-color: #f9f9f9;
    }
    .tabel-pelamar-dashboard .btn-link {
        padding: 0.4rem 0.8rem;
        background-color: var(--warna-primer);
        color: white;
        border-radius: var(--radius);
        text-decoration: none;
        font-size: 0.875rem;
        display: inline-block;
    }
    .btn-link:hover {
        opacity: 0.9;
    }
    .pelamar-status-dropdown {
        padding: 5px;
        border-radius: 4px;
        border: 1px solid #ccc;
    }
    .pelamar-status-dropdown.tertunda { border-color: orange; }
    .pelamar-status-dropdown.ditinjau { border-color: blue; }
    .pelamar-status-dropdown.diterima { border-color: green; }
    .pelamar-status-dropdown.ditolak { border-color: red; }
    @media (max-width: 768px) {
        .tabel-pelamar-dashboard, .tabel-pelamar-dashboard thead, .tabel-pelamar-dashboard tbody, .tabel-pelamar-dashboard th, .tabel-pelamar-dashboard td, .tabel-pelamar-dashboard tr {
            display: block;
        }
        .tabel-pelamar-dashboard thead tr {
            position: absolute;
            top: -9999px;
            left: -9999px;
        }
        .tabel-pelamar-dashboard tr { border: 1px solid var(--warna-border); margin-bottom: 1rem; }
        .tabel-pelamar-dashboard td {
            border: none;
            border-bottom: 1px solid #eee;
            position: relative;
            padding-left: 50%;
            text-align: right;
        }
        .tabel-pelamar-dashboard td:before {
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
    }
  </style>
</head>
<body>
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
      <div class="otentikasi-navigasi" style="display: none;">
        <a href="login.php"><button class="tombol-masuk">Masuk</button></a>
        <a href="register.php"><button class="tombol-daftar">Daftar</button></a>
      </div>
      <div class="selamat-datang-user" style="display: flex;">
        <span id="welcome-message">👋 Selamat Datang Kembali, <b><?= htmlspecialchars($namaPerusahaan) ?></b>!</span>
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
        <div class="tautan-kembali">
            <a href="dashboard_perusahaan.php">
                <img src="Image/back.png" alt="Panah Kiri" class="ikon-kecil">
                <span>Kembali ke Dashboard</span>
            </a>
        </div>
        <h2>Daftar Pelamar untuk Lowongan: "<?= htmlspecialchars($lowongan_title) ?>"</h2>

        <?php if (!empty($pelamar_list)) : ?>
            <table class="tabel-pelamar-dashboard">
                <thead>
                    <tr>
                        <th>Nama Pelamar</th>
                        <th>Email</th>
                        <th>Nomor HP</th>
                        <th>CV</th>
                        <th>Portofolio</th>
                        <th>Tanggal Lamaran</th>
                        <th>Status</th>
                        </tr>
                </thead>
                <tbody>
                    <?php foreach ($pelamar_list as $pelamar) : ?>
                        <tr>
                            <td data-label="Nama Pelamar"><?= htmlspecialchars($pelamar['nama_lengkap']) ?></td>
                            <td data-label="Email"><?= htmlspecialchars($pelamar['email']) ?></td>
                            <td data-label="Nomor HP"><?= htmlspecialchars($pelamar['nomor_hp']) ?></td>
                            <td data-label="CV">
                                <?php if (!empty($pelamar['cv'])) : ?>
                                    <a href="uploads/cv/<?= urlencode($pelamar['cv']) ?>" target="_blank" class="btn-link">Lihat CV</a>
                                <?php else : ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td data-label="Portofolio">
                                <?php if (!empty($pelamar['portofolio'])) : ?>
                                    <a href="uploads/portofolio/<?= urlencode($pelamar['portofolio']) ?>" target="_blank" class="btn-link">Lihat Portofolio</a>
                                <?php else : ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td data-label="Tanggal Lamaran"><?= date("d M Y", strtotime($pelamar['tanggal_lamar'])) ?></td>
                            <td data-label="Status">
                                <select class="pelamar-status-dropdown <?= htmlspecialchars($pelamar['status_lamaran']) ?>" 
                                        data-lamaran-id="<?= htmlspecialchars($pelamar['lamaran_id']) ?>">
                                    <option value="tertunda" <?= ($pelamar['status_lamaran'] == 'tertunda') ? 'selected' : '' ?>>Tertunda</option>
                                    <option value="ditinjau" <?= ($pelamar['status_lamaran'] == 'ditinjau') ? 'selected' : '' ?>>Ditinjau</option>
                                    <option value="diterima" <?= ($pelamar['status_lamaran'] == 'diterima') ? 'selected' : '' ?>>Diterima</option>
                                    <option value="ditolak" <?= ($pelamar['status_lamaran'] == 'ditolak') ? 'selected' : '' ?>>Ditolak</option>
                                </select>
                            </td>
                            </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p>Tidak ada pelamar untuk lowongan ini saat ini.</p>
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

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Logic untuk logout
      const logoutButton = document.getElementById('logout-button');
      if (logoutButton) {
        logoutButton.addEventListener('click', function() {
          window.location.href = 'logout_process.php'; 
        });
      }

      // Logic untuk update status pelamar (menggunakan AJAX)
      document.querySelectorAll('.pelamar-status-dropdown').forEach(dropdown => {
        dropdown.addEventListener('change', function() {
          const lamaranId = this.dataset.lamaranId;
          const newStatus = this.value;

          // Kirim perubahan status ke skrip PHP terpisah (update_status_lamaran.php)
          fetch('update_status_lamaran.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `lamaran_id=${lamaranId}&status=${newStatus}`
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              alert('Status berhasil diperbarui!');
              // Tambahkan atau hapus kelas status untuk styling
              dropdown.classList.remove('tertunda', 'ditinjau', 'diterima', 'ditolak');
              dropdown.classList.add(newStatus);
            } else {
              alert('Gagal memperbarui status: ' + data.message);
            }
          })
          .catch(error => {
            console.error('Error updating status:', error);
            alert('Terjadi kesalahan koneksi saat memperbarui status.');
          });
        });
      });
    });
  </script>
</body>
</html>