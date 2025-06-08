<?php
require_once "koneksi.php";
session_start();

// Periksa apakah pengguna adalah perusahaan yang login
$loggedInProfileId = $_SESSION['profile_id'] ?? null; // Ini adalah perusahaan_id
$loggedInUserType = $_SESSION['user_type'] ?? null;
$loggedInUsername = $_SESSION['username'] ?? null;

if (!$loggedInProfileId || $loggedInUserType !== 'perusahaan') {
    header("Location: login.php?message=" . urlencode("Anda harus login sebagai PERUSAHAAN untuk menambah lowongan."));
    exit();
}

$message = ''; // Untuk menyimpan pesan feedback

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


// --- Logika Pengiriman Form Tambah Lowongan ---
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

    // Penanganan Upload Logo Perusahaan (Opsional)
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
                // Update logo perusahaan di tabel 'perusahaan'
                $update_logo_query = "UPDATE perusahaan SET logo = ? WHERE perusahaan_id = ?";
                $stmt_logo = mysqli_prepare($conn, $update_logo_query);
                if ($stmt_logo) {
                    mysqli_stmt_bind_param($stmt_logo, "si", $logo_filename, $loggedInProfileId);
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
            $tanggal_posting = date("Y-m-d");
            $status_lowongan = 'aktif';

            // Prepared statement untuk INSERT ke tabel lowongan
            $insert_query = "INSERT INTO lowongan (perusahaan_id, judul, deskripsi, kualifikasi, benefit, kategori_id, jenis_pekerjaan, lokasi_kota, lokasi_provinsi, gaji_min, gaji_max, tanggal_posting, tanggal_deadline, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $insert_query);

            if ($stmt) {
                // PERBAIKAN FINAL: String tipe parameter yang benar untuk 14 variabel: "issssisssiiiss"
                // i=perusahaan_id, s=judul, s=deskripsi, s=kualifikasi, s=benefit, i=kategori_id,
                // s=jenis_pekerjaan, s=lokasi_kota, s=lokasi_provinsi, i=gaji_min, i=gaji_max,
                // s=tanggal_posting, s=tanggal_deadline, s=status
                mysqli_stmt_bind_param($stmt, "issssisssiiiss", 
                    $loggedInProfileId, 
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
                    $tanggal_posting, 
                    $tanggal_deadline,
                    $status_lowongan
                );

                if (mysqli_stmt_execute($stmt)) {
                    $message = "success|Lowongan berhasil ditambahkan!";
                    header("Location: index.php?message=" . urlencode($message));
                    exit();
                } else {
                    $message = "error|Gagal menambahkan lowongan: " . mysqli_error($conn);
                    error_log("SQL Error (tambah_lowongan.php): " . mysqli_error($conn));
                }
                mysqli_stmt_close($stmt);
            } else {
                $message = "error|Gagal menyiapkan statement database untuk tambah lowongan.";
                error_log("Prepare statement error (tambah_lowongan.php): " . mysqli_error($conn));
            }
        }
    }
}

// Pesan feedback (sukses/gagal) untuk alert JavaScript
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
  <title>Tambah Lowongan | BawaMap</title>
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
      <div class="otentikasi-navigasi" id="auth-buttons" style="display: none;">
        <a href="login.php"><button class="tombol-masuk">Masuk</button></a>
        <a href="register.php"><button class="tombol-daftar">Daftar</button></a>
      </div>
      <div class="selamat-datang-user">
        <span id="welcome-message"></span>
        <button class="tombol-logout" id="logout-button">Logout</button>
        <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'perusahaan') : ?>
          <a href="tambah_lowongan.php"><button class="tombol-tambah-lowongan">Tambah Lowongan</button></a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <main>
    <div class="container">
      <div class="tautan-kembali">
        <a href="index.php">
          <img src="Image/back.png" alt="Panah Kiri" class="ikon-kecil">
          <span>Kembali ke Beranda</span>
        </a>
      </div>

      <div class="container-formulir-aplikasi">
        <div class="header-formulir-aplikasi">
          <h2>Tambah Lowongan Pekerjaan Baru</h2>
          <p>Isi detail lowongan yang ingin Anda publikasikan</p>
        </div>
        
        <form class="formulir-aplikasi" method="POST" action="tambah_lowongan.php" enctype="multipart/form-data">
          <div class="bagian-formulir">
            <h3>Detail Lowongan</h3>
            <div class="grup-formulir">
              <label for="judul">Judul Pekerjaan <span class="wajib">*</span></label>
              <input type="text" id="judul" name="judul" placeholder="Contoh: Senior Frontend Developer" required>
            </div>
            
            <div class="grup-formulir">
              <label for="deskripsi">Deskripsi Pekerjaan <span class="wajib">*</span></label>
              <textarea id="deskripsi" name="deskripsi" rows="5" placeholder="Jelaskan tanggung jawab utama dan lingkungan kerja..." required></textarea>
            </div>
            
            <div class="grup-formulir">
              <label for="kualifikasi">Syarat & Kualifikasi <span class="wajib">*</span></label>
              <textarea id="kualifikasi" name="kualifikasi" rows="5" placeholder="Daftar poin-poin kualifikasi (misal: Minimal 3 tahun pengalaman)" required></textarea>
            </div>
            
            <div class="grup-formulir">
              <label for="benefit">Benefit (Opsional)</label>
              <textarea id="benefit" name="benefit" rows="3" placeholder="Daftar benefit yang ditawarkan (misal: Asuransi Kesehatan, Gaji Kompetitif)"></textarea>
            </div>
          </div>

          <div class="bagian-formulir">
            <h3>Logo Perusahaan (Opsional, akan memperbarui logo profil Anda)</h3>
            <div class="grup-formulir">
              <label for="logo">Unggah Logo</label>
              <div class="container-input-berkas">
                <label for="logo" class="label-input-berkas">
                  <img src="Image/unggah.png" alt="Unggah" class="ikon-kecil">
                  <span>Pilih File</span>
                </label>
                <input type="file" id="logo" name="logo" accept="image/*">
                <span class="nama-berkas" id="namaFileLogo">Belum ada file dipilih</span>
              </div>
              <p class="petunjuk-bidang">Format Gambar (JPG, PNG, GIF), maks 5MB. Akan memperbarui logo profil perusahaan Anda.</p>
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
                    <option value="<?= htmlspecialchars($kategori['kategori_id']) ?>">
                      <?= htmlspecialchars($kategori['nama_kategori']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="grup-formulir">
                <label for="jenisPekerjaan">Jenis Pekerjaan <span class="wajib">*</span></label>
                <select id="jenisPekerjaan" name="jenisPekerjaan" required>
                  <option value="">Pilih Jenis</option>
                  <option value="full-time">Full-time</option>
                  <option value="part-time">Part-time</option>
                  <option value="remote">Remote</option>
                  <option value="freelance">Freelance</option>
                </select>
              </div>
            </div>

            <div class="baris-formulir">
              <div class="grup-formulir">
                <label for="lokasiKota">Kota Lokasi <span class="wajib">*</span></label>
                <input type="text" id="lokasiKota" name="lokasiKota" placeholder="Contoh: Jakarta Selatan" required>
              </div>
              <div class="grup-formulir">
                <label for="lokasiProvinsi">Provinsi Lokasi <span class="wajib">*</span></label>
                <input type="text" id="lokasiProvinsi" name="lokasiProvinsi" placeholder="Contoh: DKI Jakarta" required>
              </div>
            </div>

            <div class="baris-formulir">
              <div class="grup-formulir">
                <label for="gajiMin">Gaji Minimal (Rp, Opsional)</label>
                <input type="number" id="gajiMin" name="gajiMin" placeholder="Contoh: 5000000">
              </div>
              <div class="grup-formulir">
                <label for="gajiMax">Gaji Maksimal (Rp, Opsional)</label>
                <input type="number" id="gajiMax" name="gajiMax" placeholder="Contoh: 10000000">
              </div>
            </div>
            
            <div class="grup-formulir">
              <label for="tanggalDeadline">Tanggal Batas Lamaran <span class="wajib">*</span></label>
              <input type="date" id="tanggalDeadline" name="tanggalDeadline" required>
            </div>
          </div>
          
          <div class="aksi-formulir">
            <button type="submit" class="tombol-kirim">Publikasikan Lowongan</button>
          </div>
        </form>
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
          <p>Belum punya akun? <a href="register.php">Daftar sekarang</a></p>
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
      // Perhatikan: JavaScript di sini hanya untuk logika UI lokal (modal, logout, tampilan sambutan).
      // Data lowongan dari form akan dikirim langsung ke PHP via POST.

      // Ambil data dari sesi PHP (via elemen tersembunyi jika perlu, atau localStorage untuk demo)
      // Untuk demo cepat: masih ambil dari localStorage, tapi di aplikasi nyata harus dari PHP $_SESSION
      const isLoggedIn = localStorage.getItem('isLoggedIn');
      const username = localStorage.getItem('username');
      const userType = localStorage.getItem('userType');

      const authButtonsContainer = document.getElementById('auth-buttons');
      const welcomeUserContainer = document.querySelector('.selamat-datang-user');
      const welcomeMessageSpan = document.getElementById('welcome-message');
      const logoutButton = document.getElementById('logout-button');
      const tambahLowonganButtonLink = document.getElementById('tambah-lowongan-btn');

      function updateNavDisplay() {
        if (isLoggedIn === 'true' && username) {
          authButtonsContainer.style.display = 'none';
          welcomeUserContainer.style.display = 'flex';
          welcomeMessageSpan.innerHTML = `👋 Selamat Datang Kembali, <b>${username}</b>!`;

          if (tambahLowonganButtonLink) {
              if (userType === 'perusahaan') {
                  tambahLowonganButtonLink.style.display = 'block'; 
              } else {
                  tambahLowonganButtonLink.style.display = 'none';
              }
          }
        } else {
          authButtonsContainer.style.display = 'flex'; 
          welcomeUserContainer.style.display = 'none';
          if (tambahLowonganButtonLink) { tambahLowonganButtonLink.style.display = 'none'; }
        }
      }

      function logout() {
        window.location.href = 'logout_process.php'; 
      }

      if (logoutButton) {
        logoutButton.addEventListener('click', logout);
      }
      updateNavDisplay();

      // Modal logic for login form
      const modal = document.getElementById('modalMasuk');
      const tombolMasuk = document.querySelector('.navigasi .tombol-masuk');
      const tutupModal = document.querySelector('.tutup-modal');

      tombolMasuk.addEventListener('click', function(e) {
        e.preventDefault(); 
        modal.style.display = 'block';
      });

      tutupModal.addEventListener('click', function() {
        modal.style.display = 'none';
      });

      window.addEventListener('click', function(event) {
        if (event.target == modal) {
          modal.style.display = 'none';
        }
      });

      // --- Logika untuk nama file input (tetap di JS karena interaksi DOM) ---
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