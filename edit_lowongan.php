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

$message = ''; // Untuk menyimpan pesan feedback
$lowongan_id = $_GET['id'] ?? null;
$lowongan_data = null; // Data lowongan yang akan diisi ke form

// Logika untuk mengambil daftar kategori dari DB
$kategoris = [];
$query_kategori = "SELECT kategori_id, nama_kategori FROM kategori ORDER BY nama_kategori ASC";
$result_kategori = mysqli_query($conn, $query_kategori);
if ($result_kategori) {
    while ($row = mysqli_fetch_assoc($result_kategori)) {
        $kategoris[] = $row;
    }
} else {
    error_log("Error fetching categories: " . mysqli_error($conn));
}


// --- Logika Mengambil Data Lowongan untuk Diedit ---
if ($lowongan_id) {
    $query_lowongan = "SELECT * FROM lowongan WHERE lowongan_id = ? AND perusahaan_id = ?";
    $stmt_lowongan = mysqli_prepare($conn, $query_lowongan);
    if ($stmt_lowongan) {
        mysqli_stmt_bind_param($stmt_lowongan, "ii", $lowongan_id, $profileId);
        mysqli_stmt_execute($stmt_lowongan);
        $result_lowongan = mysqli_stmt_get_result($stmt_lowongan);
        $lowongan_data = mysqli_fetch_assoc($result_lowongan);
        mysqli_stmt_close($stmt_lowongan);

        if (!$lowongan_data) {
            header("Location: dashboard_perusahaan.php?message=" . urlencode("Lowongan tidak ditemukan atau Anda tidak memiliki izin untuk mengeditnya."));
            exit();
        }
    } else {
        $message = "error|Gagal menyiapkan statement untuk mengambil lowongan.";
        error_log("Prepare statement error (edit_lowongan.php - fetch): " . mysqli_error($conn));
    }
} else {
    header("Location: dashboard_perusahaan.php?message=" . urlencode("ID Lowongan tidak diberikan untuk diedit."));
    exit();
}


// --- Logika Pengiriman Form Edit Lowongan (saat form disubmit) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $judul = $_POST['judul'] ?? '';
    $deskripsi = $_POST['deskripsi'] ?? '';
    $kualifikasi = $_POST['kualifikasi'] ?? '';
    $benefit = $_POST['benefit'] ?? '';
    $kategori_id = $_POST['kategori'] ?? '';
    $jenis_pekerjaan = $_POST['jenisPekerjaan'] ?? '';
    $lokasi_kota = $_POST['lokasiKota'] ?? '';
    $lokasi_provinsi = $_POST['lokasiProvinsi'] ?? '';
    $gaji_min = $_POST['gajiMin'] ?? null;
    $gaji_max = $_POST['gajiMax'] ?? null;
    $tanggal_deadline = $_POST['tanggalDeadline'] ?? '';
    $status_lowongan = $_POST['statusLowongan'] ?? 'aktif';

    // Penanganan Upload Logo Perusahaan (Opsional) - Sama seperti tambah_lowongan.php
    $logo_filename = '';
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $file_tmp_name = $_FILES['logo']['tmp_name'];
        $file_name = basename($_FILES['logo']['name']);
        $file_size = $_FILES['logo']['size'];
        $file_type = $_FILES['logo']['type'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($file_ext, $allowed_ext)) {
            $message = "error|Ekstensi file logo tidak diizinkan. Hanya JPG, JPEG, PNG, GIF.";
        } elseif ($file_size > 5 * 1024 * 1024) { // Max 5MB
            $message = "error|Ukuran file logo terlalu besar (maks 5MB).";
        } else {
            $new_file_name = uniqid('logo_', true) . '.' . $file_ext;
            $upload_path = 'Image/' . $new_file_name;

            if (move_uploaded_file($file_tmp_name, $upload_path)) {
                $logo_filename = $new_file_name;
                $update_logo_query = "UPDATE perusahaan SET logo = ? WHERE perusahaan_id = ?";
                $stmt_logo = mysqli_prepare($conn, $update_logo_query);
                if ($stmt_logo) {
                    mysqli_stmt_bind_param($stmt_logo, "si", $logo_filename, $profileId);
                    mysqli_stmt_execute($stmt_logo);
                    mysqli_stmt_close($stmt_logo);
                } else {
                    error_log("Error preparing logo update statement: " . mysqli_error($conn));
                }
            } else {
                $message = "error|Gagal mengunggah file logo.";
            }
        }
    }

    if (empty($message)) {
        if (empty($judul) || empty($deskripsi) || empty($kualifikasi) || empty($kategori_id) || empty($jenis_pekerjaan) || empty($lokasi_kota) || empty($lokasi_provinsi) || empty($tanggal_deadline)) {
            $message = "error|Semua bidang wajib (kecuali benefit & gaji opsional) harus diisi.";
        } else {
            $gaji_min = filter_var($gaji_min, FILTER_VALIDATE_INT) === false ? null : (int)$gaji_min;
            $gaji_max = filter_var($gaji_max, FILTER_VALIDATE_INT) === false ? null : (int)$gaji_max;
            
            // Prepared statement untuk UPDATE ke tabel lowongan
            $update_query = "UPDATE lowongan SET 
                                judul = ?, deskripsi = ?, kualifikasi = ?, benefit = ?, kategori_id = ?, 
                                jenis_pekerjaan = ?, lokasi_kota = ?, lokasi_provinsi = ?, 
                                gaji_min = ?, gaji_max = ?, tanggal_deadline = ?, status = ?
                             WHERE lowongan_id = ? AND perusahaan_id = ?"; 
            $stmt = mysqli_prepare($conn, $update_query);

            if ($stmt) {
                // PERBAIKAN DI SINI: Type definition string yang benar untuk 14 parameter
                // s=judul, s=deskripsi, s=kualifikasi, s=benefit, i=kategori_id, s=jenis_pekerjaan,
                // s=lokasi_kota, s=lokasi_provinsi, i=gaji_min, i=gaji_max, s=tanggal_deadline, s=status_lowongan,
                // i=lowongan_id, i=profileId
                mysqli_stmt_bind_param($stmt, "ssssisssiiisii", // <<< String ini memiliki 14 karakter
                    $judul, 
                    $deskripsi, 
                    $kualifikasi, 
                    $benefit, 
                    $kategori_id, 
                    $jenis_pekerjaan, 
                    $lokasi_kota, 
                    $lokasi_provinsi, 
                    $gaji_min, 
                    $gaji_max, 
                    $tanggal_deadline,
                    $status_lowongan,
                    $lowongan_id, // ID lowongan yang akan diupdate
                    $profileId    // perusahaan_id untuk validasi keamanan
                );

                if (mysqli_stmt_execute($stmt)) {
                    $message = "success|Lowongan berhasil diperbarui!";
                    header("Location: dashboard_perusahaan.php?message=" . urlencode($message));
                    exit();
                } else {
                    $message = "error|Gagal memperbarui lowongan: " . mysqli_error($conn);
                    error_log("SQL Error (edit_lowongan.php - update): " . mysqli_error($conn));
                }
                mysqli_stmt_close($stmt);
            } else {
                $message = "error|Gagal menyiapkan statement database untuk memperbarui lowongan.";
                error_log("Prepare statement error (edit_lowongan.php - update): " . mysqli_error($conn));
            }
        }
    }
}

// Pesan feedback
$feedback_text_for_alert = '';
if (!empty($message)) {
    list($type, $text) = explode('|', $message, 2);
    $feedback_text_for_alert = $text;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Lowongan | BawaMap</title>
  <link rel="stylesheet" href="styles.css">
  <link rel="icon" href="Image/logo-BM.png" type="image/x-icon">
</head>
<body>
  <?php if (!empty($feedback_text_for_alert)) : ?>
    <script>
      alert('<?= htmlspecialchars($feedback_text_for_alert) ?>');
    </script>
  <?php endif; ?>

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
        <span id="welcome-message">👋 Selamat Datang Kembali, <b><?= htmlspecialchars($_SESSION['nama_perusahaan'] ?? $_SESSION['username'] ?? '') ?></b>!</span>
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

      <div class="container-formulir-aplikasi">
        <div class="header-formulir-aplikasi">
          <h2>Edit Lowongan Pekerjaan</h2>
          <p>Perbarui detail lowongan Anda.</p>
        </div>
        
        <form class="formulir-aplikasi" method="POST" action="edit_lowongan.php?id=<?= htmlspecialchars($lowongan_id) ?>" enctype="multipart/form-data">
          <div class="bagian-formulir">
            <h3>Detail Lowongan</h3>
            <div class="grup-formulir">
              <label for="judul">Judul Pekerjaan <span class="wajib">*</span></label>
              <input type="text" id="judul" name="judul" placeholder="Contoh: Senior Frontend Developer" value="<?= htmlspecialchars($lowongan_data['judul'] ?? '') ?>" required>
            </div>
            
            <div class="grup-formulir">
              <label for="deskripsi">Deskripsi Pekerjaan <span class="wajib">*</span></label>
              <textarea id="deskripsi" name="deskripsi" rows="5" placeholder="Jelaskan tanggung jawab utama dan lingkungan kerja..." required><?= htmlspecialchars($lowongan_data['deskripsi'] ?? '') ?></textarea>
            </div>
            
            <div class="grup-formulir">
              <label for="kualifikasi">Syarat & Kualifikasi <span class="wajib">*</span></label>
              <textarea id="kualifikasi" name="kualifikasi" rows="5" placeholder="Daftar poin-poin kualifikasi (misal: Minimal 3 tahun pengalaman)" required><?= htmlspecialchars($lowongan_data['kualifikasi'] ?? '') ?></textarea>
            </div>
            
            <div class="grup-formulir">
              <label for="benefit">Benefit (Opsional)</label>
              <textarea id="benefit" name="benefit" rows="3" placeholder="Daftar benefit yang ditawarkan (misal: Asuransi Kesehatan, Gaji Kompetitif)"><?= htmlspecialchars($lowongan_data['benefit'] ?? '') ?></textarea>
            </div>
          </div>

          <div class="bagian-formulir">
            <h3>Informasi Tambahan</h3>
            <div class="baris-formulir">
              <div class="grup-formulir">
                <label for="kategori">Kategori <span class="wajib">*</span></label>
                <select id="kategori" name="kategori" required>
                  <option value="">Pilih Kategori</option>
                  <?php foreach ($kategoris as $kategori) : ?>
                    <option value="<?= htmlspecialchars($kategori['kategori_id']) ?>"
                        <?= (isset($lowongan_data['kategori_id']) && $lowongan_data['kategori_id'] == $kategori['kategori_id']) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($kategori['nama_kategori']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="grup-formulir">
                <label for="jenisPekerjaan">Jenis Pekerjaan <span class="wajib">*</span></label>
                <select id="jenisPekerjaan" name="jenisPekerjaan" required>
                  <option value="">Pilih Jenis</option>
                  <option value="full-time" <?= (isset($lowongan_data['jenis_pekerjaan']) && $lowongan_data['jenis_pekerjaan'] == 'full-time') ? 'selected' : '' ?>>Full-time</option>
                  <option value="part-time" <?= (isset($lowongan_data['jenis_pekerjaan']) && $lowongan_data['jenis_pekerjaan'] == 'part-time') ? 'selected' : '' ?>>Part-time</option>
                  <option value="remote" <?= (isset($lowongan_data['jenis_pekerjaan']) && $lowongan_data['jenis_pekerjaan'] == 'remote') ? 'selected' : '' ?>>Remote</option>
                  <option value="freelance" <?= (isset($lowongan_data['jenis_pekerjaan']) && $lowongan_data['jenis_pekerjaan'] == 'freelance') ? 'selected' : '' ?>>Freelance</option>
                </select>
              </div>
            </div>

            <div class="baris-formulir">
              <div class="grup-formulir">
                <label for="lokasiKota">Kota Lokasi <span class="wajib">*</span></label>
                <input type="text" id="lokasiKota" name="lokasiKota" placeholder="Contoh: Jakarta Selatan" value="<?= htmlspecialchars($lowongan_data['lokasi_kota'] ?? '') ?>" required>
              </div>
              <div class="grup-formulir">
                <label for="lokasiProvinsi">Provinsi Lokasi <span class="wajib">*</span></label>
                <input type="text" id="lokasiProvinsi" name="lokasiProvinsi" placeholder="Contoh: DKI Jakarta" value="<?= htmlspecialchars($lowongan_data['lokasi_provinsi'] ?? '') ?>" required>
              </div>
            </div>

            <div class="baris-formulir">
              <div class="grup-formulir">
                <label for="gajiMin">Gaji Minimal (Rp, Opsional)</label>
                <input type="number" id="gajiMin" name="gajiMin" placeholder="Contoh: 5000000" value="<?= htmlspecialchars($lowongan_data['gaji_min'] ?? '') ?>">
              </div>
              <div class="grup-formulir">
                <label for="gajiMax">Gaji Maksimal (Rp, Opsional)</label>
                <input type="number" id="gajiMax" name="gajiMax" placeholder="Contoh: 10000000" value="<?= htmlspecialchars($lowongan_data['gaji_max'] ?? '') ?>">
              </div>
            </div>
            
            <div class="grup-formulir">
              <label for="tanggalDeadline">Tanggal Batas Lamaran <span class="wajib">*</span></label>
              <input type="date" id="tanggalDeadline" name="tanggalDeadline" value="<?= htmlspecialchars($lowongan_data['tanggal_deadline'] ?? '') ?>" required>
            </div>
            
            <div class="grup-formulir">
              <label for="statusLowongan">Status Lowongan <span class="wajib">*</span></label>
              <select id="statusLowongan" name="statusLowongan" required>
                <option value="aktif" <?= (isset($lowongan_data['status']) && $lowongan_data['status'] == 'aktif') ? 'selected' : '' ?>>Aktif</option>
                <option value="nonaktif" <?= (isset($lowongan_data['status']) && $lowongan_data['status'] == 'nonaktif') ? 'selected' : '' ?>>Nonaktif</option>
              </select>
            </div>
          </div>
          
          <div class="aksi-formulir">
            <button type="submit" class="tombol-kirim">Perbarui Lowongan</button>
          </div>
        </form>
      </div>
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
      const logoutButton = document.getElementById('logout-button');
      if (logoutButton) {
        logoutButton.addEventListener('click', function() {
          window.location.href = 'logout_process.php'; 
        });
      }

      // Logika untuk nama file input logo
      const namaFileLogoSpan = document.getElementById('namaFileLogo');
      const logoInput = document.getElementById('logo');

      if (logoInput) {
        logoInput.addEventListener('change', function() {
            if (this.files && this.files.length > 0) {
                namaFileLogoSpan.textContent = this.files[0].name;
            } else {
                namaFileLogoSpan.textContent = 'Belum ada file dipilih';
            }
        });
      }
    });
  </script>
</body>
</html>