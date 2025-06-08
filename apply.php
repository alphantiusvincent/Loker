<?php
require_once "koneksi.php";
session_start();

// Inisialisasi variabel pesan feedback
$message = '';

// Periksa apakah pengguna adalah pencari kerja yang login
$loggedInProfileId = $_SESSION['profile_id'] ?? null; // Ini adalah pencari_id dari tabel pencari_kerja
$loggedInUserType = $_SESSION['user_type'] ?? null;
// $loggedInUsername = $_SESSION['username'] ?? null; // Username dari tabel users (opsional, tidak digunakan langsung di sini)

if (!$loggedInProfileId || $loggedInUserType !== 'pencari_kerja') {
    // Redirect ke halaman login jika tidak login sebagai pencari kerja
    header("Location: login.php?message=" . urlencode("Anda harus login sebagai PENCARI KERJA untuk melamar pekerjaan."));
    exit();
}

// Ambil lowongan_id dari URL
$lowongan_id = $_GET['id'] ?? null;

if (!$lowongan_id) {
    header("Location: index.php?error=" . urlencode("ID Lowongan tidak ditemukan."));
    exit();
}

$job_summary = null;
// Ambil ringkasan lowongan untuk sidebar dari database
$query_job_summary = "SELECT 
                        l.lowongan_id AS id, l.judul, l.jenis_pekerjaan, l.lokasi_kota, l.lokasi_provinsi, 
                        l.gaji_min, l.gaji_max, l.tanggal_deadline,
                        p.nama_perusahaan, p.logo, k.nama_kategori
                      FROM lowongan l
                      JOIN perusahaan p ON l.perusahaan_id = p.perusahaan_id
                      JOIN kategori k ON l.kategori_id = k.kategori_id
                      WHERE l.lowongan_id = ?";
$stmt_job_summary = mysqli_prepare($conn, $query_job_summary);
if ($stmt_job_summary) {
    mysqli_stmt_bind_param($stmt_job_summary, "i", $lowongan_id);
    mysqli_stmt_execute($stmt_job_summary);
    $result_job_summary = mysqli_stmt_get_result($stmt_job_summary);
    $job_summary = mysqli_fetch_assoc($result_job_summary);
    mysqli_stmt_close($stmt_job_summary);
}

if (!$job_summary) {
    header("Location: index.php?error=" . urlencode("Ringkasan lowongan tidak ditemukan."));
    exit();
}

// --- Logika Pengiriman Lamaran (saat form disubmit) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nama_lengkap = $_POST['namaLengkap'] ?? '';
    $tanggal_lahir = $_POST['tanggalLahir'] ?? '';
    $email = $_POST['surel'] ?? '';
    $nomor_hp = $_POST['telepon'] ?? '';
    $surat_lamaran = $_POST['suratLamaran'] ?? '';

    // Penanganan upload file (Hanya ambil nama file untuk demo)
    // Untuk upload file sebenarnya, Anda perlu memindahkan file dari $_FILES['tmp_name'] ke folder server
    $cv_filename = $_FILES['cv']['name'] ?? '';
    $portofolio_filename = $_FILES['portofolio']['name'] ?? '';

    // Validasi dasar di sisi server
    if (empty($nama_lengkap) || empty($tanggal_lahir) || empty($email) || empty($nomor_hp) || empty($cv_filename)) {
        $message = "error|Data lamaran tidak lengkap. Pastikan semua bidang wajib diisi (termasuk CV).";
    } else {
        // Prepared statement untuk INSERT ke tabel lamaran
        $insert_query = "INSERT INTO lamaran (lowongan_id, pencari_id, nama_lengkap, tanggal_lahir, email, nomor_hp, cv, portofolio, surat_lamaran) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $insert_query);

        if ($stmt) {
            // Bind parameter: i = integer, s = string
            mysqli_stmt_bind_param($stmt, "iisssssss", 
                $lowongan_id, 
                $loggedInProfileId, // Menggunakan profile_id dari sesi
                $nama_lengkap, 
                $tanggal_lahir, 
                $email, 
                $nomor_hp, 
                $cv_filename, 
                $portofolio_filename, 
                $surat_lamaran
            );

            if (mysqli_stmt_execute($stmt)) {
                $message = "success|Lamaran berhasil dikirim! Kami akan segera meninjau aplikasi Anda.";
                // Redirect setelah sukses untuk mencegah resubmission form
                header("Location: index.php?message=" . urlencode("Lamaran berhasil dikirim!"));
                exit(); // Penting: Hentikan eksekusi script setelah header
            } else {
                // Periksa error spesifik dari MySQL
                if (mysqli_errno($conn) == 1452) { // ER_NO_REFERENCED_ROW_2 (Foreign Key Constraint Fails)
                    $message = "error|ID Pencari Kerja tidak valid. Pastikan Anda login dengan akun pencari kerja yang terdaftar di sistem.";
                } elseif (mysqli_errno($conn) == 1062) { // ER_DUP_ENTRY (Duplicate entry for unique key)
                    $message = "error|Anda sudah melamar untuk lowongan ini.";
                } else {
                    $message = "error|Terjadi kesalahan saat mengirim lamaran: " . mysqli_error($conn);
                }
                error_log("SQL Error (apply.php): " . mysqli_error($conn) . " Query: " . $insert_query);
            }
            mysqli_stmt_close($stmt);
        } else {
            $message = "error|Gagal menyiapkan statement database untuk lamaran.";
            error_log("Prepare statement error (apply.php): " . mysqli_error($conn));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Lamar Pekerjaan | BawaMap</title>
  <link rel="stylesheet" href="styles.css">
  <link rel="icon" href="Image/logo-BM.png" type="image/x-icon">
</head>
<body>
  <?php if (!empty($message)) : ?>
    <script>
      <?php 
      list($type, $text) = explode('|', $message, 2);
      echo "alert('" . htmlspecialchars($text) . "');";
      ?>
    </script>
  <?php endif; ?>

  <header class="navigasi">
    <div class="container">
      <div class="merek-navigasi">
        <div class="kepala-kartu">
          <div class="logo-perusahaan1">
            <img src="Image/logo-BM.png" alt="logo-BM">
            
          </div>
          <a href="index.php"> <h1>BawaMap</h1>
        </a>
        </div>
      </div>
      <nav class="menu-navigasi">
        <ul>
          <li><a href="index.php">Beranda</a></li> <li><a href="#">Kategori</a></li>
          <li><a href="#">Perusahaan</a></li>
          <li><a href="#">Tentang Kami</a></li>
        </ul>
      </nav>
      <div class="otentikasi-navigasi" id="auth-buttons">
        <a href="login.php"><button class="tombol-masuk">Masuk</button></a> <a href="register.php"><button class="tombol-daftar">Daftar</button></a> </div>
      <div class="selamat-datang-user" style="display: none;">
        <span id="welcome-message"></span>
        <button class="tombol-logout" id="logout-button">Logout</button>
      </div>
    </div>
  </header>

  <main>
    <div class="container">
      <div class="tautan-kembali">
        <a href="detail.php?id=<?= htmlspecialchars($job_summary['id']) ?>"> <img src="Image/back.png" alt="Panah Kiri" class="ikon-kecil">
          <span>Kembali ke Detail Lowongan</span>
        </a>
      </div>

      <div class="container-aplikasi">
        <div class="sidebar-aplikasi">
          <div class="kartu-ringkasan-kerja">
            <div class="header-ringkasan-kerja">
                  <div class="logo-perusahaan">
                    <img src="Image/<?= htmlspecialchars($job_summary['logo'] ?? 'default-logo.png') ?>" alt="Logo <?= htmlspecialchars($job_summary['nama_perusahaan'] ?? 'Perusahaan') ?>">
                  </div>
                  <div class="judul-ringkasan-kerja">
                    <h3><?= htmlspecialchars($job_summary['judul'] ?? 'Judul Lowongan') ?></h3>
                    <p class="nama-perusahaan"><?= htmlspecialchars($job_summary['nama_perusahaan'] ?? 'Nama Perusahaan') ?></p>
                    <div class="lokasi-kerja">
                      <img src="Image/logo-6.jpeg" alt="Lokasi" class="ikon-kecil">
                      <span><?= htmlspecialchars($job_summary['lokasi_kota'] ?? 'Lokasi Tidak Diketahui') ?>, <?= htmlspecialchars($job_summary['lokasi_provinsi'] ?? '') ?></span>
                    </div>
                  </div>
                </div>
                
                <div class="detail-ringkasan-kerja">
                  <div class="item-ringkasan">
                    <div class="label-ringkasan">Kategori</div>
                    <div class="nilai-ringkasan"><?= htmlspecialchars($job_summary['nama_kategori'] ?? 'Umum') ?></div>
                  </div>
                  <div class="item-ringkasan">
                    <div class="label-ringkasan">Jenis</div>
                    <div class="nilai-ringkasan"><?= htmlspecialchars($job_summary['jenis_pekerjaan'] ?? 'Full-time') ?></div>
                  </div>
                  <div class="item-ringkasan">
                    <div class="label-ringkasan">Gaji</div>
                    <?php
                    $gajiText = '';
                    if (isset($job_summary['gaji_min']) && isset($job_summary['gaji_max'])) {
                        $gajiText = 'Rp' . number_format($job_summary['gaji_min'], 0, ',', '.') . ' - ' . number_format($job_summary['gaji_max'], 0, ',', '.') . '/bulan';
                    } elseif (isset($job_summary['gaji_min'])) {
                        $gajiText = 'Rp' . number_format($job_summary['gaji_min'], 0, ',', '.') . '+/bulan';
                    } else {
                        $gajiText = 'Gaji Negosiasi';
                    }
                    ?>
                    <div class="nilai-ringkasan"><?= htmlspecialchars($gajiText) ?></div>
                  </div>
                  <div class="item-ringkasan">
                    <div class="label-ringkasan">Deadline</div>
                    <div class="nilai-ringkasan"><?= date("d M Y", strtotime($job_summary['tanggal_deadline'] ?? '')) ?></div>
                  </div>
                </div>
          </div>
        </div>

        <div class="utama-aplikasi">
          <div class="container-formulir-aplikasi">
            <div class="header-formulir-aplikasi">
              <h2>Formulir Lamaran</h2>
              <p>Lengkapi data diri Anda untuk melamar posisi ini</p>
            </div>
            
            <form class="formulir-aplikasi" id="applicationForm" method="POST" action="apply.php?id=<?= htmlspecialchars($lowongan_id) ?>" enctype="multipart/form-data">
              <input type="hidden" id="lowonganId" name="lowonganId" value="<?= htmlspecialchars($lowongan_id) ?>">
              <input type="hidden" id="pencariId" name="pencariId" value="<?= htmlspecialchars($loggedInProfileId) ?>">

              <div class="bagian-formulir">
                <h3>Informasi Pribadi</h3>
                
                <div class="baris-formulir">
                  <div class="grup-formulir">
                    <label for="namaLengkap">
                      Nama Lengkap <span class="wajib">*</span>
                    </label>
                    <input type="text" id="namaLengkap" name="namaLengkap" placeholder="Masukkan nama lengkap" required>
                  </div>
                  
                  <div class="grup-formulir">
                    <label for="tanggalLahir">
                      Tanggal Lahir <span class="wajib">*</span>
                    </label>
                    <input type="date" id="tanggalLahir" name="tanggalLahir" required>
                  </div>
                </div>
                
                <div class="baris-formulir">
                  <div class="grup-formulir">
                    <label for="surel">
                      Email <span class="wajib">*</span>
                    </label>
                    <input type="email" id="surel" name="surel" placeholder="contoh@email.com" required>
                  </div>
                  
                  <div class="grup-formulir">
                    <label for="telepon">
                      Nomor HP <span class="wajib">*</span>
                    </label>
                    <input type="tel" id="telepon" name="telepon" placeholder="contoh: 08123456789" required>
                  </div>
                </div>
              </div>
              
              <div class="bagian-formulir">
                <h3>Dokumen</h3>
                
                <div class="grup-formulir">
                  <label for="cv-input">
                    CV (PDF) <span class="wajib">*</span>
                  </label>
                  <div class="container-input-berkas">
                    <label for="cv-input" class="label-input-berkas">
                      <img src="Image/unggah.png" alt="Unggah" class="ikon-kecil">
                      <span>Pilih File</span>
                    </label>
                    <input type="file" id="cv-input" name="cv" accept=".pdf" required> 
                    <span class="nama-berkas" id="namaFileCv">Belum ada file dipilih</span>
                  </div>
                  <p class="petunjuk-bidang">Format PDF, maksimal 5MB (untuk demo, file tidak diupload, hanya nama)</p>
                </div>
                
                <div class="grup-formulir">
                  <label for="portofolio-input">
                    Portofolio (PDF, opsional)
                  </label>
                  <div class="container-input-berkas">
                    <label for="portofolio-input" class="label-input-berkas">
                      <img src="Image/unggah.png" alt="Unggah" class="ikon-kecil">
                      <span>Pilih File</span>
                    </label>
                    <input type="file" id="portofolio-input" name="portofolio" accept=".pdf"> 
                    <span class="nama-berkas" id="namaFilePortofolio">Belum ada file dipilih</span>
                  </div>
                  <p class="petunjuk-bidang">Format PDF, maksimal 5MB (untuk demo, file tidak diupload, hanya nama)</p>
                </div>
              </div>
              
              <div class="bagian-formulir">
                <h3>Surat Lamaran (Opsional)</h3>
                
                <div class="grup-formulir">
                  <label for="suratLamaran">
                    Tuliskan surat lamaran Anda
                  </label>
                  <textarea id="suratLamaran" name="suratLamaran" rows="5" placeholder="Jelaskan kenapa Anda adalah kandidat yang tepat untuk posisi ini..."></textarea>
                </div>
              </div>
              
              <div class="aksi-formulir">
                <p class="pemberitahuan-ketentuan">
                  Dengan mengirimkan lamaran ini, Anda menyetujui <a href="#">Ketentuan Layanan</a> dan <a href="#">Kebijakan Privasi</a> kami.
                </p>
                <button type="submit" class="tombol-kirim">Kirim Lamaran</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </main>

  <div class="modal" id="modalMasuk">
    <div class="konten-modal">
      <span class="tutup-modal">&times;</span>
      <h2>Masuk ke Akun Anda</h2>
      
      <div class="opsi-masuk">
        <button class="opsi-masuk-item aktif" data-target="pencari-kerja">Pencari Kerja</button>
        <button class="opsi-masuk-item" data-target="perusahaan">Perusahaan</button>
      </div>
      
      <form class="formulir-masuk">
        <div class="grup-formulir">
          <label for="namapengguna">Username atau Email</label>
          <input type="text" id="namapengguna" name="namapengguna" required>
        </div>
        
        <div class="grup-formulir">
          <label for="katasandi">Password</label>
          <input type="password" id="katasandi" name="katasandi" required>
        </div>
        
        <div class="grup-formulir grup-kotak-centang">
          <input type="checkbox" id="ingat" name="ingat">
          <label for="ingat">Ingat saya</label>
        </div>
        
        <button type="submit" class="tombol-kirim">Masuk</button>
        
        <div class="footer-formulir">
          <a href="#">Lupa password?</a>
          <p>Belum punya akun? <a href="#">Daftar sekarang</a></p>
        </div>
      </form>
    </div>
  </div>

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
      // Logic untuk tampilan tombol Masuk/Daftar atau Selamat Datang (sama seperti di index.php)
      const authButtonsContainer = document.getElementById('auth-buttons');
      const welcomeUserContainer = document.querySelector('.selamat-datang-user');
      const welcomeMessageSpan = document.getElementById('welcome-message');
      const logoutButton = document.getElementById('logout-button');

      function checkLoginStatus() {
        // Pada halaman PHP, status login sebenarnya dikelola oleh sesi PHP.
        // JavaScript di sini hanya untuk tampilan awal.
        // Untuk demo ini, kita masih mengambil dari localStorage untuk tampilan.
        const isLoggedIn = localStorage.getItem('isLoggedIn');
        const username = localStorage.getItem('username');

        if (isLoggedIn === 'true' && username) {
          if (authButtonsContainer) {
            authButtonsContainer.style.display = 'none';
          }
          if (welcomeUserContainer) {
            welcomeUserContainer.style.display = 'flex';
          }
          if (welcomeMessageSpan) {
            welcomeMessageSpan.innerHTML = `👋 Selamat Datang Kembali, <b>${username}</b>!`;
          }
        } else {
          if (authButtonsContainer) {
            authButtonsContainer.style.display = 'flex'; 
          }
          if (welcomeUserContainer) {
            welcomeUserContainer.style.display = 'none';
          }
        }
      }

      function logout() {
        // Saat tombol logout ditekan, kita akan memanggil skrip PHP untuk menghancurkan sesi.
        window.location.href = 'logout_process.php'; // Redirect ke logout_process.php
      }

      if (logoutButton) {
        logoutButton.addEventListener('click', logout);
      }
      checkLoginStatus();

      // --- Logic untuk penanganan input file (tetap di JS karena interaksi DOM) ---
      const namaFileCvSpan = document.getElementById('namaFileCv');
      const namaFilePortofolioSpan = document.getElementById('namaFilePortofolio');
      const cvInput = document.getElementById('cv-input'); 
      const portofolioInput = document.getElementById('portofolio-input');
      
      cvInput.addEventListener('change', function() {
          if (this.files && this.files.length > 0) {
              namaFileCvSpan.textContent = this.files[0].name;
          } else {
              namaFileCvSpan.textContent = 'Belum ada file dipilih';
          }
      });

      portofolioInput.addEventListener('change', function() {
          if (this.files && this.files.length > 0) {
              namaFilePortofolioSpan.textContent = this.files[0].name;
          } else {
              namaFilePortofolioSpan.textContent = 'Belum ada file dipilih';
          }
      });

      // jobSummaryCard tidak lagi membutuhkan JS untuk dimuat karena sudah di PHP
      // dan applicationForm tidak lagi membutuhkan JS submit, karena action="POST" ke PHP
    });
  </script>
</body>
</html>