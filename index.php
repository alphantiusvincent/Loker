<?php
require_once "koneksi.php";
session_start();

// Inisialisasi variabel untuk status login dari sesi
$isLoggedIn = $_SESSION['is_logged_in'] ?? false;
$username = $_SESSION['username'] ?? ''; // Username dari tabel users
$userType = $_SESSION['user_type'] ?? '';
$profileId = $_SESSION['profile_id'] ?? null;

$namaTampilanUser = $username; // Default: username dari tabel users
if ($userType === 'pencari_kerja') {
    $namaTampilanUser = $_SESSION['username'] ?? $username; // Ambil dari username atau nama_lengkap (jika ada di sesi)
} elseif ($userType === 'perusahaan') {
    $namaTampilanUser = $_SESSION['nama_perusahaan'] ?? $username; // Ambil nama perusahaan dari sesi
}


// Query dasar untuk mengambil lowongan
$query = "SELECT 
            l.lowongan_id AS id, 
            l.judul, 
            l.jenis_pekerjaan AS jenis, 
            l.gaji_min, 
            l.gaji_max, 
            l.tanggal_deadline AS deadline,
            l.lokasi_kota AS lokasi,
            l.lokasi_provinsi,
            p.nama_perusahaan AS perusahaan, 
            p.logo,
            k.nama_kategori AS kategori,
            l.tanggal_posting
          FROM lowongan l
          JOIN perusahaan p ON l.perusahaan_id = p.perusahaan_id
          JOIN kategori k ON l.kategori_id = k.kategori_id";

$conditions = ["l.status = 'aktif'", "l.tanggal_deadline >= CURDATE()"]; // Kondisi default
$params = [];
$param_types = '';

// --- Logika Fungsi Pencarian (Search) ---
if (!empty($_GET['kataKunci'])) {
    $keyword = '%' . $_GET['kataKunci'] . '%';
    $conditions[] = "(l.judul LIKE ? OR l.deskripsi LIKE ? OR l.kualifikasi LIKE ? OR p.nama_perusahaan LIKE ?)";
    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;
    $param_types .= 'ssss';
}

if (!empty($_GET['kategori'])) {
    $conditions[] = "l.kategori_id = ?";
    $params[] = $_GET['kategori'];
    $param_types .= 'i';
}

if (!empty($_GET['lokasi'])) {
    $location_keyword = '%' . $_GET['lokasi'] . '%';
    $conditions[] = "(l.lokasi_kota LIKE ? OR l.lokasi_provinsi LIKE ?)";
    $params[] = $location_keyword;
    $params[] = $location_keyword;
    $param_types .= 'ss';
}

if (!empty($_GET['jenisPekerjaan'])) {
    $conditions[] = "l.jenis_pekerjaan = ?";
    $params[] = $_GET['jenisPekerjaan'];
    $param_types .= 's';
}

if (!empty($_GET['gaji'])) {
    $gaji_range_val = $_GET['gaji'];
    
    if ($gaji_range_val === '20+') {
        $conditions[] = "l.gaji_min >= ?";
        $params[] = 20000000;
        $param_types .= 'i';
    } else {
        $gaji_parts = explode('-', $gaji_range_val);
        if (count($gaji_parts) == 2) {
            $min_gaji_str = $gaji_parts[0];
            $max_gaji_str = $gaji_parts[1];
            
            $min_gaji = (int)$min_gaji_str * 1000000; // Mengubah '0-5' menjadi 0jt-5jt
            $max_gaji = (int)$max_gaji_str * 1000000;
            
            $conditions[] = "l.gaji_min >= ? AND l.gaji_max <= ?";
            $params[] = $min_gaji;
            $params[] = $max_gaji;
            $param_types .= 'ii';
        }
    }
}

// Gabungkan semua kondisi WHERE
if (!empty($conditions)) {
    $query .= " WHERE " . implode(' AND ', $conditions);
}

$query .= " ORDER BY l.tanggal_posting DESC LIMIT 6"; // Urutkan dan batasi hasil

// --- Eksekusi Query dengan Prepared Statement ---
$stmt = mysqli_prepare($conn, $query);

if ($stmt) {
    if (!empty($params)) {
        // ...$params digunakan untuk "unpack" array $params ke argumen terpisah
        mysqli_stmt_bind_param($stmt, $param_types, ...$params); 
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
} else {
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
      <div class="otentikasi-navigasi" <?= ($isLoggedIn) ? 'style="display: none;"' : '' ?>>
        <a href="login.php"><button class="tombol-masuk">Masuk</button></a>
        <a href="register.php"><button class="tombol-daftar">Daftar</button></a>
      </div>
      <div class="selamat-datang-user" <?= ($isLoggedIn) ? 'style="display: flex;"' : 'style="display: none;"' ?>>
        <span id="welcome-message">👋 Selamat Datang Kembali, <b><?= htmlspecialchars($namaTampilanUser) ?></b>!</span>
        <button class="tombol-logout" id="logout-button">Logout</button>
        <?php if ($isLoggedIn && $userType === 'perusahaan') : ?>
          <a href="tambah_lowongan.php">
            <button class="tombol-tambah-lowongan">Tambah Lowongan</button>
          </a>
          <a href="dashboard_perusahaan.php">
            <button class="tombol-dashboard">Dashboard</button>
          </a>
        <?php endif; ?>
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
            <form class="formulir-pencarian" method="GET" action="index.php">
              <div class="baris-pencarian">
                <div class="kolom-pencarian">
                  <label for="kataKunci">Kata Kunci</label>
                  <input type="text" id="kataKunci" name="kataKunci" placeholder="Posisi, jabatan, atau keahlian" value="<?= htmlspecialchars($_GET['kataKunci'] ?? '') ?>">
                </div>
                <div class="kolom-pencarian">
                  <label for="kategori">Kategori</label>
                  <select id="kategori" name="kategori">
                    <option value="">Semua Kategori</option>
                    <?php 
                    $query_kategori_select = "SELECT kategori_id, nama_kategori FROM kategori ORDER BY nama_kategori ASC";
                    $result_kategori_select = mysqli_query($conn, $query_kategori_select);
                    while ($k = mysqli_fetch_assoc($result_kategori_select)) {
                        $selected = (isset($_GET['kategori']) && $_GET['kategori'] == $k['kategori_id']) ? 'selected' : '';
                        echo '<option value="' . htmlspecialchars($k['kategori_id']) . '" ' . $selected . '>' . htmlspecialchars($k['nama_kategori']) . '</option>';
                    }
                    ?>
                  </select>
                </div>
              </div>
              <div class="baris-pencarian">
                <div class="kolom-pencarian">
                  <label for="lokasi">Lokasi</label>
                  <select id="lokasi" name="lokasi">
                    <option value="">Semua Lokasi</option>
                    <?php 
                    $locations_query = "SELECT DISTINCT lokasi_kota FROM lowongan ORDER BY lokasi_kota ASC";
                    $locations_result = mysqli_query($conn, $locations_query);
                    while ($loc = mysqli_fetch_assoc($locations_result)) {
                        $selected = (isset($_GET['lokasi']) && $_GET['lokasi'] == $loc['lokasi_kota']) ? 'selected' : '';
                        echo '<option value="' . htmlspecialchars($loc['lokasi_kota']) . '" ' . $selected . '>' . htmlspecialchars($loc['lokasi_kota']) . '</option>';
                    }
                    ?>
                  </select>
                </div>
                <div class="kolom-pencarian">
                  <label for="jenisPekerjaan">Jenis Pekerjaan</label>
                  <select id="jenisPekerjaan" name="jenisPekerjaan">
                    <option value="">Semua Jenis</option>
                    <option value="full-time" <?= (isset($_GET['jenisPekerjaan']) && $_GET['jenisPekerjaan'] == 'full-time') ? 'selected' : '' ?>>Full-time</option>
                    <option value="part-time" <?= (isset($_GET['jenisPekerjaan']) && $_GET['jenisPekerjaan'] == 'part-time') ? 'selected' : '' ?>>Part-time</option>
                    <option value="remote" <?= (isset($_GET['jenisPekerjaan']) && $_GET['jenisPekerjaan'] == 'remote') ? 'selected' : '' ?>>Remote</option>
                    <option value="freelance" <?= (isset($_GET['jenisPekerjaan']) && $_GET['jenisPekerjaan'] == 'freelance') ? 'selected' : '' ?>>Freelance</option>
                  </select>
                </div>
              </div>
              <div class="baris-pencarian">
                <div class="kolom-pencarian">
                  <label for="gaji">Rentang Gaji</label>
                  <select id="gaji" name="gaji">
                    <option value="">Semua Gaji</option>
                    <option value="0-5" <?= (isset($_GET['gaji']) && $_GET['gaji'] == '0-5') ? 'selected' : '' ?>>Rp0 - Rp5 juta</option>
                    <option value="5-10" <?= (isset($_GET['gaji']) && $_GET['gaji'] == '5-10') ? 'selected' : '' ?>>Rp5 - Rp10 juta</option>
                    <option value="10-15" <?= (isset($_GET['gaji']) && $_GET['gaji'] == '10-15') ? 'selected' : '' ?>>Rp10 - Rp15 juta</option>
                    <option value="15-20" <?= (isset($_GET['gaji']) && $_GET['gaji'] == '15-20') ? 'selected' : '' ?>>Rp15 - Rp20 juta</option>
                    <option value="20+" <?= (isset($_GET['gaji']) && $_GET['gaji'] == '20+') ? 'selected' : '' ?>>Rp20 juta+</option>
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