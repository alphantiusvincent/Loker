<?php
require_once "koneksi.php";
session_start();

$message = ''; // Untuk pesan sukses/gagal registrasi

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $current_form_type = $_POST['currentFormType'] ?? 'pencari-kerja'; // Hidden input dari JS
    
    if ($current_form_type === 'pencari-kerja') {
        $nama_lengkap = $_POST['namaLengkap'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $konfirmasiPassword = $_POST['konfirmasiPassword'] ?? '';

        if (empty($nama_lengkap) || empty($email) || empty($password) || $password !== $konfirmasiPassword) {
            $message = "error|Data pencari kerja tidak lengkap atau password tidak cocok.";
        } else {
            // PENTING: Dalam aplikasi nyata, password HARUS di-hash (misalnya dengan password_hash())
            // Untuk demo, kita simpan plain text.
            // $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Periksa apakah email atau username sudah ada
            $check_user_query = "SELECT COUNT(*) FROM users WHERE email = ? OR username = ?";
            $stmt_check = mysqli_prepare($conn, $check_user_query);
            $username = explode('@', $email)[0];
            mysqli_stmt_bind_param($stmt_check, "ss", $email, $username);
            mysqli_stmt_execute($stmt_check);
            mysqli_stmt_bind_result($stmt_check, $count);
            mysqli_stmt_fetch($stmt_check);
            mysqli_stmt_close($stmt_check);

            if ($count > 0) {
                $message = "error|Email atau username sudah terdaftar.";
            } else {
                // Mulai transaksi
                mysqli_begin_transaction($conn);
                try {
                    // Masukkan ke tabel users
                    $insert_user_query = "INSERT INTO users (username, email, password, user_type) VALUES (?, ?, ?, 'pencari_kerja')";
                    $stmt_user = mysqli_prepare($conn, $insert_user_query);
                    mysqli_stmt_bind_param($stmt_user, "sss", $username, $email, $password);
                    mysqli_stmt_execute($stmt_user);
                    $newUserId = mysqli_insert_id($conn);
                    mysqli_stmt_close($stmt_user);

                    // Masukkan ke tabel pencari_kerja
                    $insert_pencari_query = "INSERT INTO pencari_kerja (user_id, nama_lengkap) VALUES (?, ?)";
                    $stmt_pencari = mysqli_prepare($conn, $insert_pencari_query);
                    mysqli_stmt_bind_param($stmt_pencari, "is", $newUserId, $nama_lengkap);
                    mysqli_stmt_execute($stmt_pencari);
                    mysqli_stmt_close($stmt_pencari);

                    mysqli_commit($conn);
                    $message = "success|Registrasi pencari kerja berhasil! Silakan login.";
                    header("Location: login.php?message=" . urlencode($message));
                    exit();
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $message = "error|Terjadi kesalahan saat registrasi: " . $e->getMessage();
                    error_log("Registrasi Pencari Kerja Error: " . $e->getMessage());
                }
            }
        }
    } elseif ($current_form_type === 'perusahaan') {
        $nama_perusahaan = $_POST['namaPerusahaan'] ?? '';
        $email_perusahaan = $_POST['emailPerusahaan'] ?? '';
        $password = $_POST['password'] ?? '';
        $konfirmasiPassword = $_POST['konfirmasiPassword'] ?? '';
        $industri = $_POST['industri'] ?? '';
        $alamat = $_POST['alamat'] ?? '';

        if (empty($nama_perusahaan) || empty($email_perusahaan) || empty($password) || $password !== $konfirmasiPassword) {
            $message = "error|Data perusahaan tidak lengkap atau password tidak cocok.";
        } else {
            // PENTING: Dalam aplikasi nyata, password HARUS di-hash!
            // $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Periksa apakah email atau username sudah ada
            $check_user_query = "SELECT COUNT(*) FROM users WHERE email = ? OR username = ?";
            $stmt_check = mysqli_prepare($conn, $check_user_query);
            $username = explode('@', $email_perusahaan)[0];
            mysqli_stmt_bind_param($stmt_check, "ss", $email_perusahaan, $username);
            mysqli_stmt_execute($stmt_check);
            mysqli_stmt_bind_result($stmt_check, $count);
            mysqli_stmt_fetch($stmt_check);
            mysqli_stmt_close($stmt_check);

            if ($count > 0) {
                $message = "error|Email atau username sudah terdaftar.";
            } else {
                // Mulai transaksi
                mysqli_begin_transaction($conn);
                try {
                    // Masukkan ke tabel users
                    $insert_user_query = "INSERT INTO users (username, email, password, user_type) VALUES (?, ?, ?, 'perusahaan')";
                    $stmt_user = mysqli_prepare($conn, $insert_user_query);
                    mysqli_stmt_bind_param($stmt_user, "sss", $username, $email_perusahaan, $password);
                    mysqli_stmt_execute($stmt_user);
                    $newUserId = mysqli_insert_id($conn);
                    mysqli_stmt_close($stmt_user);

                    // Masukkan ke tabel perusahaan
                    $insert_perusahaan_query = "INSERT INTO perusahaan (user_id, nama_perusahaan, industri, alamat) VALUES (?, ?, ?, ?)";
                    $stmt_perusahaan = mysqli_prepare($conn, $insert_perusahaan_query);
                    mysqli_stmt_bind_param($stmt_perusahaan, "isss", $newUserId, $nama_perusahaan, $industri, $alamat);
                    mysqli_stmt_execute($stmt_perusahaan);
                    mysqli_stmt_close($stmt_perusahaan);

                    mysqli_commit($conn);
                    $message = "success|Registrasi perusahaan berhasil! Silakan login.";
                    header("Location: login.php?message=" . urlencode($message));
                    exit();
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $message = "error|Terjadi kesalahan saat registrasi: " . $e->getMessage();
                    error_log("Registrasi Perusahaan Error: " . $e->getMessage());
                }
            }
        }
    }
}

// Pesan feedback (sukses/gagal)
$feedback_message = '';
if (!empty($message)) {
    list($type, $text) = explode('|', $message, 2);
    $feedback_message = "<script>alert('" . htmlspecialchars($text) . "');</script>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Daftar Akun - BawaMap</title>
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
          <a href="index.php">
            <h1>BawaMap</h1>
          </a>
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
      <div class="container-formulir-aplikasi">
        <div class="header-formulir-aplikasi">
          <h2>Daftar Akun Baru</h2>
          <p>Pilih jenis akun yang ingin Anda daftarkan</p>
        </div>
        
        <form class="formulir-aplikasi" id="registerForm" method="POST" action="register.php">
          <input type="hidden" id="currentFormType" name="currentFormType" value="pencari-kerja">

          <div class="opsi-masuk" style="margin-bottom: 20px;">
            <button type="button" class="opsi-masuk-item aktif" data-target="pencari-kerja">Pencari Kerja</button>
            <button type="button" class="opsi-masuk-item" data-target="perusahaan">Perusahaan</button>
          </div>

          <div id="form-pencari-kerja" class="bagian-formulir">
            <h3>Informasi Akun Pencari Kerja</h3>
            <div class="baris-formulir">
              <div class="grup-formulir">
                <label for="reg-js-namaLengkap">Nama Lengkap <span class="wajib">*</span></label>
                <input type="text" id="reg-js-namaLengkap" name="namaLengkap" placeholder="Masukkan nama lengkap" required>
              </div>
              <div class="grup-formulir">
                <label for="reg-js-email">Email <span class="wajib">*</span></label>
                <input type="email" id="reg-js-email" name="email" placeholder="contoh@email.com" required>
              </div>
            </div>
            <div class="baris-formulir">
              <div class="grup-formulir">
                <label for="reg-js-password">Password <span class="wajib">*</span></label>
                <input type="password" id="reg-js-password" name="password" placeholder="Minimal 8 karakter" required>
              </div>
              <div class="grup-formulir">
                <label for="reg-js-konfirmasiPassword">Konfirmasi Password <span class="wajib">*</span></label>
                <input type="password" id="reg-js-konfirmasiPassword" name="konfirmasiPassword" placeholder="Ulangi password" required>
              </div>
            </div>
          </div>

          <div id="form-perusahaan" class="bagian-formulir" style="display:none;">
            <h3>Informasi Akun Perusahaan</h3>
            <div class="baris-formulir">
              <div class="grup-formulir">
                <label for="reg-comp-namaPerusahaan">Nama Perusahaan <span class="wajib">*</span></label>
                <input type="text" id="reg-comp-namaPerusahaan" name="namaPerusahaan" placeholder="Masukkan nama perusahaan" required>
              </div>
              <div class="grup-formulir">
                <label for="reg-comp-emailPerusahaan">Email Perusahaan <span class="wajib">*</span></label>
                <input type="email" id="reg-comp-emailPerusahaan" name="emailPerusahaan" placeholder="contoh@perusahaan.com" required>
              </div>
            </div>
            <div class="baris-formulir">
              <div class="grup-formulir">
                <label for="reg-comp-password">Password <span class="wajib">*</span></label>
                <input type="password" id="reg-comp-password" name="password" placeholder="Minimal 8 karakter" required>
              </div>
              <div class="grup-formulir">
                <label for="reg-comp-konfirmasiPassword">Konfirmasi Password <span class="wajib">*</span></label>
                <input type="password" id="reg-comp-konfirmasiPassword" name="konfirmasiPassword" placeholder="Ulangi password" required>
              </div>
            </div>
             <div class="grup-formulir">
                <label for="reg-comp-industri">Industri</label>
                <input type="text" id="reg-comp-industri" name="industri" placeholder="Contoh: IT, Manufaktur, dll.">
            </div>
            <div class="grup-formulir">
                <label for="reg-comp-alamat">Alamat Perusahaan</label>
                <textarea id="reg-comp-alamat" name="alamat" rows="3" placeholder="Masukkan alamat lengkap perusahaan"></textarea>
            </div>
          </div>
          
          <div class="aksi-formulir">
            <p class="pemberitahuan-ketentuan">
              Dengan mendaftar, Anda menyetujui <a href="#">Ketentuan Layanan</a> dan <a href="#">Kebijakan Privasi</a> kami.
            </p>
            <button type="submit" class="tombol-kirim">Daftar Sekarang</button>
          </div>
          <div class="footer-formulir" style="margin-top: 15px;">
            <p>Sudah punya akun? <a href="login.php">Masuk di sini</a></p>
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

      const opsiMasukItems = document.querySelectorAll('.opsi-masuk-item');
      const formPencariKerja = document.getElementById('form-pencari-kerja');
      const formPerusahaan = document.getElementById('form-perusahaan');
      const registerForm = document.getElementById('registerForm');
      const currentFormTypeHidden = document.getElementById('currentFormType'); // Hidden input untuk jenis form

      let currentFormType = 'pencari-kerja'; // Default form

      opsiMasukItems.forEach(item => {
        item.addEventListener('click', function() {
          opsiMasukItems.forEach(btn => btn.classList.remove('aktif'));
          this.classList.add('aktif');

          currentFormType = this.getAttribute('data-target');
          currentFormTypeHidden.value = currentFormType; // Update hidden input value

          if (currentFormType === 'pencari-kerja') {
            formPencariKerja.style.display = 'block';
            formPerusahaan.style.display = 'none';
            // Set required attributes for job seeker form
            formPencariKerja.querySelectorAll('input').forEach(input => input.setAttribute('required', ''));
            formPerusahaan.querySelectorAll('input, textarea').forEach(input => input.removeAttribute('required'));
          } else {
            formPencariKerja.style.display = 'none';
            formPerusahaan.style.display = 'block';
            // Set required attributes for company form
            formPerusahaan.querySelectorAll('input, textarea').forEach(input => input.setAttribute('required', ''));
            formPencariKerja.querySelectorAll('input').forEach(input => input.removeAttribute('required'));
          }
        });
      });

      // Initialize form display based on active tab
      if (currentFormTypeHidden.value === 'pencari-kerja') { // Gunakan nilai dari hidden input untuk init
        formPencariKerja.style.display = 'block';
        formPerusahaan.style.display = 'none';
        formPencariKerja.querySelectorAll('input').forEach(input => input.setAttribute('required', ''));
        formPerusahaan.querySelectorAll('input, textarea').forEach(input => input.removeAttribute('required'));
      } else {
         formPencariKerja.style.display = 'none';
         formPerusahaan.style.display = 'block';
         formPerusahaan.querySelectorAll('input, textarea').forEach(input => input.setAttribute('required', ''));
         formPencariKerja.querySelectorAll('input').forEach(input => input.removeAttribute('required'));
      }

      // Hapus event listener submit JS lama karena form sekarang POST ke PHP
      // registerForm.removeEventListener('submit', async function(event) { ... });
    });
  </script>
</body>
</html>