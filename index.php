<?php
require_once "koneksi.php";
session_start();

// Inisialisasi variabel untuk status login dari sesi
$isLoggedIn = $_SESSION['is_logged_in'] ?? false;
$username = $_SESSION['username'] ?? '';
$userType = $_SESSION['user_type'] ?? '';
$profileId = $_SESSION['profile_id'] ?? null;

// Ambil data lowongan dari database
$query = "SELECT 
            l.lowongan_id AS id, 
            l.judul, 
            l.jenis_pekerjaan AS jenis, 
            l.gaji_min, 
            l.gaji_max, 
            l.tanggal_deadline AS deadline,
            l.lokasi_kota AS lokasi,
            p.nama_perusahaan AS perusahaan, 
            p.logo,
            k.nama_kategori AS kategori
          FROM lowongan l
          JOIN perusahaan p ON l.perusahaan_id = p.perusahaan_id
          JOIN kategori k ON l.kategori_id = k.kategori_id
          ORDER BY l.tanggal_posting DESC LIMIT 6";

$result = mysqli_query($conn, $query);

if (!$result) {
    die("Query lowongan gagal: " . mysqli_error($conn));
}

// Pesan feedback dari redirect (misal dari apply.php atau register.php)
$feedback_message = '';
if (isset($_GET['message'])) {
    $feedback_message = "<script>alert('" . htmlspecialchars($_GET['message']) . "');</script>";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BawaMap</title>
  <link rel="stylesheet" href="styles.css">
  <link rel="icon" href="Image/logo-BM.png" type="image/x-icon">
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
      <div class="otentikasi-navigasi" id="auth-buttons">
        <a href="login.php"><button class="tombol-masuk">Masuk</button></a>
        <a href="register.php"><button class="tombol-daftar">Daftar</button></a>
      </div>
      <div class="selamat-datang-user" style="display: none;">
        <span id="welcome-message"></span>
        <button class="tombol-logout" id="logout-button">Logout</button>
        <a href="tambah_lowongan.php" id="tambah-lowongan-btn" style="display: none;">
          <button class="tombol-tambah-lowongan">Tambah Lowongan</button>
        </a>
      </div>
    </div>
  </header>

  <main>
    <div class="container">
      <section class="hero">
        <h1>Temukan Pekerjaan Impian Anda</h1>
        <p>Lebih dari 10.000 lowongan pekerjaan dari berbagai industri tersedia untuk Anda</p>
      </section>

      <section class="bagian-pencarian">
        <div class="kartu-pencarian">
          <div class="kepala-pencarian">
            <h2>Cari Lowongan</h2>
          </div>
          <div class="badan-pencarian">
            <form class="formulir-pencarian">
              <div class="baris-pencarian">
                <div class="kolom-pencarian">
                  <label for="kataKunci">Kata Kunci</label>
                  <input type="text" id="kataKunci" name="kataKunci" placeholder="Posisi, jabatan, atau keahlian">
                </div>
                <div class="kolom-pencarian">
                  <label for="kategori">Kategori</label>
                  <select id="kategori" name="kategori">
                    <option value="">Semua Kategori</option>
                    <option value="it">IT & Teknologi</option>
                    <option value="keuangan">Keuangan</option>
                    <option value="pendidikan">Pendidikan</option>
                    <option value="kesehatan">Kesehatan</option>
                    <option value="pemasaran">Pemasaran</option>
                  </select>
                </div>
              </div>
              <div class="baris-pencarian">
                <div class="kolom-pencarian">
                  <label for="lokasi">Lokasi</label>
                  <select id="lokasi" name="lokasi">
                    <option value="">Semua Lokasi</option>
                    <option value="jakarta">Jakarta</option>
                    <option value="bandung">Bandung</option>
                    <option value="surabaya">Surabaya</option>
                    <option value="yogyakarta">Yogyakarta</option>
                    <option value="medan">Medan</option>
                  </select>
                </div>
                <div class="kolom-pencarian">
                  <label for="jenisPekerjaan">Jenis Pekerjaan</label>
                  <select id="jenisPekerjaan" name="jenisPekerjaan">
                    <option value="">Semua Jenis</option>
                    <option value="full-time">Full-time</option>
                    <option value="part-time">Part-time</option>
                    <option value="remote">Remote</option>
                    <option value="freelance">Freelance</option>
                  </select>
                </div>
              </div>
              <div class="baris-pencarian">
                <div class="kolom-pencarian">
                  <label for="gaji">Rentang Gaji</label>
                  <select id="gaji" name="gaji">
                    <option value="">Semua Gaji</option>
                    <option value="0-5">Rp0 - Rp5 juta</option>
                    <option value="5-10">Rp5 - Rp10 juta</option>
                    <option value="10-15">Rp10 - Rp15 juta</option>
                    <option value="15-20">Rp15 - Rp20 juta</option>
                    <option value="20+">Rp20 juta+</option>
                  </select>
                </div>
                <div class="kolom-pencarian kolom-pencarian-tombol">
                  <button type="submit" class="tombol-cari">Cari Lowongan</button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </section>

      <section class="bagian-daftar-pekerjaan">
        <div class="kepala-daftar">
          <h2>Lowongan Pekerjaan Terbaru</h2>
          <p>Daftar lowongan pekerjaan yang baru ditambahkan di platform kami</p>
        </div>

        <div class="daftar-pekerjaan">
          <?php if (mysqli_num_rows($result) > 0) : ?>
            <?php while ($row = mysqli_fetch_assoc($result)) : ?>
              <div class="kartu-pekerjaan">
                <div class="kepala-kartu">
                  <div class="logo-perusahaan">
                    <img src="Image/<?= htmlspecialchars($row['logo'] ?? 'default-logo.png') ?>" alt="Logo <?= htmlspecialchars($row['perusahaan'] ?? 'Perusahaan') ?>">
                  </div>
                  <div class="tag-pekerjaan">
                    <?php 
                    $kategoriClass = strtolower(str_replace([' ', '&'], ['', '_'], $row['kategori'] ?? ''));
                    if (empty($kategoriClass)) $kategoriClass = 'umum';
                    $kategoriClass = 'kategori-pekerjaan-' . $kategoriClass;
                    ?>
                    <span class="kategori-pekerjaan <?= $kategoriClass ?>"> <?= htmlspecialchars($row['kategori'] ?? 'Umum') ?> </span>
                    <?php 
                    $jenisClass = strtolower($row['jenis'] ?? '');
                    if (empty($jenisClass)) $jenisClass = 'full-time';
                    $jenisClass = 'jenis-pekerjaan-' . $jenisClass;
                    ?>
                    <span class="jenis-pekerjaan <?= $jenisClass ?>"> <?= htmlspecialchars($row['jenis'] ?? 'Full-time') ?> </span>
                  </div>
                </div>
                <div class="konten-kartu">
                  <h3 class="judul-pekerjaan">
                    <a href="detail.php?id=<?= htmlspecialchars($row['id']) ?>"><?= htmlspecialchars($row['judul']) ?></a>
                  </h3>
                  <p class="nama-perusahaan"><?= htmlspecialchars($row['perusahaan'] ?? 'Nama Perusahaan Tidak Diketahui') ?></p>
                  <div class="lokasi-pekerjaan">
                    <img src="Image/logo-6.jpeg" alt="Lokasi" class="ikon-kecil">
                    <span><?= htmlspecialchars($row['lokasi'] ?? 'Lokasi Tidak Diketahui') ?></span>
                  </div>
                  <div class="footer-kartu">
                    <?php
                    $gajiText = '';
                    if (isset($row['gaji_min']) && isset($row['gaji_max'])) {
                        $gajiText = 'Rp' . number_format($row['gaji_min'], 0, ',', '.') . ' - ' . number_format($row['gaji_max'], 0, ',', '.') . '/bulan';
                    } elseif (isset($row['gaji_min'])) {
                        $gajiText = 'Rp' . number_format($row['gaji_min'], 0, ',', '.') . '+/bulan';
                    } else {
                        $gajiText = 'Gaji Negosiasi';
                    }
                    ?>
                    <div class="gaji-pekerjaan"><?= htmlspecialchars($gajiText) ?></div>
                    <div class="deadline-pekerjaan">Deadline: <?= date("d M Y", strtotime($row['deadline'])) ?></div>
                  </div>
                </div>
              </div>
            <?php endwhile; ?>
          <?php else : ?>
            <p style="text-align: center; width: 100%; grid-column: 1 / -1;">Tidak ada lowongan pekerjaan terbaru saat ini.</p>
          <?php endif; ?>
        </div>
        <div class="muat-lagi">
          <button class="tombol-muat-lagi">Muat Lebih Banyak</button>
        </div>
      </section>
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
      // Ambil data dari sesi PHP yang sudah ada di halaman (jika ada)
      // Ini adalah cara untuk mengambil data dari PHP ke JavaScript
      const isLoggedInPhp = <?= json_encode($_SESSION['is_logged_in'] ?? false) ?>;
      const usernamePhp = <?= json_encode($_SESSION['username'] ?? '') ?>;
      const userTypePhp = <?= json_encode($_SESSION['user_type'] ?? '') ?>;

      const authButtonsContainer = document.getElementById('auth-buttons');
      const welcomeUserContainer = document.querySelector('.selamat-datang-user');
      const welcomeMessageSpan = document.getElementById('welcome-message');
      const logoutButton = document.getElementById('logout-button');
      const tambahLowonganButtonLink = document.getElementById('tambah-lowongan-btn');


      function checkLoginStatus() {
        if (isLoggedInPhp === true && usernamePhp !== '') {
          if (authButtonsContainer) {
            authButtonsContainer.style.display = 'none';
          }
          if (welcomeUserContainer) {
            welcomeUserContainer.style.display = 'flex';
          }
          if (welcomeMessageSpan) {
            welcomeMessageSpan.innerHTML = `👋 Selamat Datang Kembali, <b>${usernamePhp}</b>!`;
          }
          
          if (tambahLowonganButtonLink) { // Pastikan tombol ada
              if (userTypePhp === 'perusahaan') {
                  tambahLowonganButtonLink.style.display = 'block'; 
              } else {
                  tambahLowonganButtonLink.style.display = 'none';
              }
          }
        } else {
          if (authButtonsContainer) {
            authButtonsContainer.style.display = 'flex'; 
          }
          if (welcomeUserContainer) {
            welcomeUserContainer.style.display = 'none';
          }
           if (tambahLowonganButtonLink) { tambahLowonganButtonLink.style.display = 'none'; }
        }
      }

      function logout() {
        // Panggil skrip PHP untuk menghancurkan sesi
        window.location.href = 'logout_process.php'; 
      }

      if (logoutButton) {
        logoutButton.addEventListener('click', logout);
      }
      checkLoginStatus(); // Panggil saat DOM dimuat

      // Modal logic for login form (tetap ada karena form login muncul di modal)
      const modal = document.getElementById('modalMasuk');
      const tombolMasuk = document.querySelector('.navigasi .tombol-masuk');
      const tutupModal = document.querySelector('.tutup-modal');

      tombolMasuk.addEventListener('click', function(e) {
        e.preventDefault(); // Prevent default link behavior
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
    });
  </script>
</body>
</html>