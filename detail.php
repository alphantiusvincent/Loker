<?php
// Pastikan koneksi.php ada di direktori yang sama
require_once "koneksi.php"; 
session_start(); // Memulai sesi PHP

// Ambil ID lowongan dari URL
$lowongan_id = $_GET['id'] ?? null; // Menggunakan operator null coalescing untuk PHP 7+

if (!$lowongan_id) {
    // Redirect atau tampilkan pesan error jika ID lowongan tidak ada
    header("Location: index.php");
    exit();
}

// Ambil detail lowongan dari database
// PERBAIKAN: Mengganti 'p.lokasi' dengan 'p.kota' dan 'p.provinsi'
$query = "SELECT 
            l.lowongan_id AS id, 
            l.judul, 
            l.deskripsi, 
            l.kualifikasi, 
            l.benefit, 
            l.jenis_pekerjaan, 
            l.lokasi_kota, 
            l.lokasi_provinsi, 
            l.gaji_min, 
            l.gaji_max, 
            l.tanggal_posting, 
            l.tanggal_deadline,
            p.nama_perusahaan, 
            p.logo, 
            p.deskripsi AS deskripsi_perusahaan, 
            p.website AS website_perusahaan,
            k.nama_kategori AS kategori_nama
          FROM lowongan l 
          JOIN perusahaan p ON l.perusahaan_id = p.perusahaan_id 
          JOIN kategori k ON l.kategori_id = k.kategori_id 
          WHERE l.lowongan_id = ?";

// Menggunakan prepared statement untuk keamanan dan menghindari SQL Injection
$stmt = mysqli_prepare($conn, $query);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $lowongan_id); // "i" berarti integer
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $job = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt); // Tutup statement setelah selesai
} else {
    die("Error menyiapkan statement: " . mysqli_error($conn));
}

// Jika lowongan tidak ditemukan setelah query
if (!$job) {
    header("Location: index.php?error=notfound"); // Redirect ke index dengan pesan error
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($job['judul'] ?? 'Lowongan Tidak Ditemukan') ?> - <?= htmlspecialchars($job['nama_perusahaan'] ?? 'BawaMap') ?> | BawaMap</title>
  <link rel="stylesheet" href="styles.css">
  <link rel="icon" href="Image/logo-BM.png" type="image/x-icon">
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
      <div class="otentikasi-navigasi" id="auth-buttons">
        <a href="login.php"><button class="tombol-masuk">Masuk</button></a>
        <a href="register.php"><button class="tombol-daftar">Daftar</button></a>
      </div>
      <div class="selamat-datang-user" style="display: none;">
        <span id="welcome-message"></span>
        <button class="tombol-logout" id="logout-button">Logout</button>
      </div>
    </div>
  </header>

  <main>
    <div class="container">
      <div class="tautan-kembali">
        <a href="javascript:history.back()">
          <img src="Image/back.png" alt="Panah Kiri" class="ikon-kecil">
          <span>Kembali ke Daftar Lowongan</span>
        </a>
      </div>

      <div class="container-detail-pekerjaan">
        <div class="utama-detail-pekerjaan">
          <div class="header-detail-pekerjaan">
            <div class="logo-perusahaan-besar">
              <img src="Image/<?= htmlspecialchars($job['logo'] ?? 'default-logo.png') ?>" alt="Logo <?= htmlspecialchars($job['nama_perusahaan'] ?? 'Perusahaan') ?>">
            </div>
            
            <div class="judul-detail-pekerjaan">
              <h1><?= htmlspecialchars($job['judul'] ?? 'Judul Lowongan') ?></h1>
              <div class="info-perusahaan">
                <h2><?= htmlspecialchars($job['nama_perusahaan'] ?? 'Nama Perusahaan') ?></h2>
                <div class="lokasi-pekerjaan">
                  <img src="Image/logo-6.jpeg" alt="Lokasi" class="ikon-kecil">
                  <span><?= htmlspecialchars($job['lokasi_kota'] ?? 'Tidak Diketahui') ?>, <?= htmlspecialchars($job['lokasi_provinsi'] ?? '') ?></span>
                </div>
              </div>
              
              <div class="tag-pekerjaan-besar">
                <?php 
                $kategoriClass = strtolower(str_replace([' ', '&'], ['', '_'], $job['kategori_nama'] ?? ''));
                if (empty($kategoriClass)) $kategoriClass = 'umum';
                $kategoriClass = 'kategori-pekerjaan-' . $kategoriClass;
                ?>
                <span class="kategori-pekerjaan <?= $kategoriClass ?>"><?= htmlspecialchars($job['kategori_nama'] ?? 'Umum') ?></span>
                <?php 
                $jenisClass = strtolower($job['jenis_pekerjaan'] ?? '');
                if (empty($jenisClass)) $jenisClass = 'full-time'; 
                $jenisClass = 'jenis-pekerjaan-' . $jenisClass;
                ?>
                <span class="jenis-pekerjaan <?= $jenisClass ?>"><?= htmlspecialchars($job['jenis_pekerjaan'] ?? 'Full-time') ?></span>
              </div>
            </div>
          </div>
          
          <div class="container-tombol-lamar">
            <a href="apply.php?id=<?= htmlspecialchars($job['id']) ?>" class="tombol-lamar">Lamar Sekarang</a>
            <button class="tombol-simpan">Simpan</button>
          </div>
          
          <div class="konten-detail-pekerjaan">
            <section class="bagian-pekerjaan">
              <h3>Deskripsi Pekerjaan</h3>
              <div class="deskripsi-pekerjaan">
                <p><?= nl2br(htmlspecialchars($job['deskripsi'] ?? '')) ?></p>
              </div>
            </section>
            
            <section class="bagian-pekerjaan">
              <h3>Kualifikasi</h3>
              <div class="kualifikasi-pekerjaan">
                <ul>
                  <?php 
                  $kualifikasi_items = explode("\r\n", $job['kualifikasi'] ?? '');
                  foreach ($kualifikasi_items as $kualifikasi_item) : 
                    if (trim($kualifikasi_item) !== '') : ?>
                      <li><?= htmlspecialchars(ltrim($kualifikasi_item, '- ')) ?></li>
                    <?php endif; 
                  endforeach; ?>
                </ul>
              </div>
            </section>
            
            <section class="bagian-pekerjaan">
              <h3>Benefit</h3>
              <div class="manfaat-pekerjaan">
                <ul>
                  <?php 
                  $benefit_items = explode("\r\n", $job['benefit'] ?? '');
                  foreach ($benefit_items as $benefit_item) : 
                    if (trim($benefit_item) !== '') : ?>
                      <li><?= htmlspecialchars(ltrim($benefit_item, '- ')) ?></li>
                    <?php endif; 
                  endforeach; ?>
                </ul>
              </div>
            </section>
          </div>
        </div>
        
        <div class="sidebar-detail-pekerjaan">
          <div class="ringkasan-pekerjaan">
            <h3>Ringkasan Lowongan</h3>
            
            <div class="item-ringkasan">
              <div class="label-ringkasan">Tanggal Posting</div>
              <div class="nilai-ringkasan"><?= date("d M Y", strtotime($job['tanggal_posting'] ?? '')) ?></div>
            </div>
            
            <div class="item-ringkasan">
              <div class="label-ringkasan">Deadline</div>
              <div class="nilai-ringkasan"><?= date("d M Y", strtotime($job['tanggal_deadline'] ?? '')) ?></div>
            </div>
            
            <div class="item-ringkasan">
              <div class="label-ringkasan">Kategori</div>
              <div class="nilai-ringkasan"><?= htmlspecialchars($job['kategori_nama'] ?? 'Umum') ?></div>
            </div>
            
            <div class="item-ringkasan">
              <div class="label-ringkasan">Jenis Pekerjaan</div>
              <div class="nilai-ringkasan"><?= htmlspecialchars($job['jenis_pekerjaan'] ?? 'Full-time') ?></div>
            </div>
            
            <div class="item-ringkasan">
              <div class="label-ringkasan">Gaji</div>
              <?php
              $gajiText = '';
              if (isset($job['gaji_min']) && isset($job['gaji_max'])) {
                  $gajiText = 'Rp' . number_format($job['gaji_min'], 0, ',', '.') . ' - ' . number_format($job['gaji_max'], 0, ',', '.') . '/bulan';
              } elseif (isset($job['gaji_min'])) {
                  $gajiText = 'Rp' . number_format($job['gaji_min'], 0, ',', '.') . '+/bulan';
              } else {
                  $gajiText = 'Gaji Negosiasi';
              }
              ?>
              <div class="nilai-ringkasan"><?= htmlspecialchars($gajiText) ?></div>
            </div>
            
            <div class="item-ringkasan">
              <div class="label-ringkasan">Lokasi</div>
              <div class="nilai-ringkasan"><?= htmlspecialchars($job['lokasi_kota'] ?? 'Tidak Diketahui') ?>, <?= htmlspecialchars($job['lokasi_provinsi'] ?? '') ?></div>
            </div>
          </div>
          
          <div class="ringkasan-perusahaan">
            <h3>Tentang Perusahaan</h3>
            <p><?= nl2br(htmlspecialchars($job['deskripsi_perusahaan'] ?? 'Deskripsi perusahaan tidak tersedia.')) ?></p>
            <a href="<?= htmlspecialchars($job['website_perusahaan'] ?? '#') ?>" class="tautan-perusahaan" target="_blank">Lihat Profil Perusahaan</a>
          </div>
          
          <div class="aksi-pekerjaan">
            <a href="apply.php?id=<?= htmlspecialchars($job['id']) ?>" class="tombol-lamar tombol-penuh">Lamar Sekarang</a>
            <button class="tombol-bagikan">Bagikan Lowongan</button>
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
      const authButtonsContainer = document.getElementById('auth-buttons');
      const welcomeUserContainer = document.querySelector('.selamat-datang-user');
      const welcomeMessageSpan = document.getElementById('welcome-message');
      const logoutButton = document.getElementById('logout-button');

      function checkLoginStatus() {
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
        localStorage.removeItem('isLoggedIn');
        localStorage.removeItem('username');
        localStorage.removeItem('userType');
        localStorage.removeItem('userId');
        localStorage.removeItem('profileId');
        window.location.href = 'login.php'; 
      }

      if (logoutButton) {
        logoutButton.addEventListener('click', logout);
      }
      checkLoginStatus();

      // Modal logic for login form (still in JS for now)
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