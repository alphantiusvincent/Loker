// Loker/server/server.js

const express = require('express');
const mysql = require('mysql2/promise');
const cors = require('cors');

const app = express();
const port = 3000;

// Middleware
app.use(cors());
app.use(express.json());

// Konfigurasi koneksi database
const dbConfig = {
    host: 'localhost',
    user: 'root', // <<< GANTI DENGAN USER DATABASE ANDA (misalnya 'root')
    password: '', // <<< GANTI DENGAN PASSWORD DATABASE ANDA (misalnya '' jika kosong)
    database: 'bawamap',
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0
};

// Test koneksi database saat startup
async function testDbConnection() {
    try {
        const connection = await mysql.createConnection(dbConfig);
        console.log('Koneksi ke database MySQL/MariaDB berhasil!');
        await connection.end();
    } catch (error) {
        console.error('ERROR: Gagal terhubung ke database. Periksa dbConfig:', error.message);
        // Mungkin perlu exit aplikasi jika koneksi database esensial
        // process.exit(1); // Nonaktifkan ini untuk demo agar server tetap bisa start meskipun DB down
    }
}
testDbConnection(); // Panggil saat server start

// Route untuk login
app.post('/api/login', async (req, res) => {
    const { username, password } = req.body;

    if (!username || !password) {
        return res.status(400).json({ message: 'Username dan password harus diisi.' });
    }

    let connection;
    try {
        connection = await mysql.createConnection(dbConfig);
        const [userRows] = await connection.execute(
            'SELECT user_id, username, password, user_type FROM users WHERE username = ?',
            [username]
        );

        if (userRows.length === 0) {
            console.log(`Attempted login for non-existent user: ${username}`);
            return res.status(401).json({ message: 'Username atau password salah.' });
        }

        const user = userRows[0];
        // PENTING: Untuk DEMO, kita asumsikan password plain text dari form cocok dengan password di DB.
        // Anda HARUS MENGGUNAKAN library hashing (misal: bcrypt) untuk memverifikasi password di aplikasi nyata.
        if (password !== user.password) { // Di sini 'user.password' adalah plain text dari DB
            console.log(`Failed login for user ${username}: Incorrect password.`);
            return res.status(401).json({ message: 'Username atau password salah.' });
        }

        let profileId = null;
        if (user.user_type === 'pencari_kerja') {
            const [pencariRows] = await connection.execute(
                'SELECT pencari_id FROM pencari_kerja WHERE user_id = ?',
                [user.user_id]
            );
            if (pencariRows.length > 0) {
                profileId = pencariRows[0].pencari_id;
            } else {
                // Ini penting: Jika user ada tapi profil pencari_kerja tidak ada
                console.warn(`User ${user.username} (ID: ${user.user_id}) is 'pencari_kerja' but no matching entry in 'pencari_kerja' table.`);
                return res.status(404).json({ message: 'Profil pencari kerja tidak ditemukan untuk user ini. Silakan daftar ulang atau hubungi admin.' });
            }
        } else if (user.user_type === 'perusahaan') {
            const [perusahaanRows] = await connection.execute(
                'SELECT perusahaan_id FROM perusahaan WHERE user_id = ?',
                [user.user_id]
            );
            if (perusahaanRows.length > 0) {
                profileId = perusahaanRows[0].perusahaan_id;
            } else {
                 console.warn(`User ${user.username} (ID: ${user.user_id}) is 'perusahaan' but no matching entry in 'perusahaan' table.`);
                return res.status(404).json({ message: 'Profil perusahaan tidak ditemukan untuk user ini. Silakan daftar ulang atau hubungi admin.' });
            }
        }
        
        res.status(200).json({
            message: 'Login berhasil!',
            isLoggedIn: true,
            username: user.username,
            userType: user.user_type,
            userId: user.user_id,
            profileId: profileId
        });

    } catch (error) {
        console.error('Error saat login:', error);
        res.status(500).json({ message: 'Terjadi kesalahan server saat login.' });
    } finally {
        if (connection) connection.end();
    }
});

// Route untuk mendapatkan semua lowongan
app.get('/api/lowongan', async (req, res) => {
    let connection;
    try {
        connection = await mysql.createConnection(dbConfig);
        const [rows] = await connection.execute(
            `SELECT 
                l.*, 
                p.nama_perusahaan, p.logo, p.deskripsi AS deskripsi_perusahaan, p.website AS website_perusahaan,
                k.nama_kategori 
             FROM lowongan l 
             JOIN perusahaan p ON l.perusahaan_id = p.perusahaan_id 
             JOIN kategori k ON l.kategori_id = k.kategori_id 
             ORDER BY l.tanggal_posting DESC`
        );
        res.status(200).json(rows);
    } catch (error) {
        console.error('Error saat mengambil lowongan:', error);
        res.status(500).json({ message: 'Terjadi kesalahan server saat mengambil lowongan.' });
    } finally {
        if (connection) connection.end();
    }
});

// Route untuk mendapatkan detail lowongan berdasarkan ID
app.get('/api/lowongan/:id', async (req, res) => {
    const lowonganId = req.params.id;
    let connection;
    try {
        connection = await mysql.createConnection(dbConfig);
        const [rows] = await connection.execute(
            `SELECT 
                l.*, 
                p.nama_perusahaan, p.logo, p.deskripsi AS deskripsi_perusahaan, p.website AS website_perusahaan,
                k.nama_kategori 
             FROM lowongan l 
             JOIN perusahaan p ON l.perusahaan_id = p.perusahaan_id 
             JOIN kategori k ON l.kategori_id = k.kategori_id 
             WHERE l.lowongan_id = ?`,
            [lowonganId]
        );

        if (rows.length > 0) {
            res.status(200).json(rows[0]);
        } else {
            res.status(404).json({ message: 'Lowongan tidak ditemukan.' });
        }
    } catch (error) {
        console.error('Error saat mengambil detail lowongan:', error);
        res.status(500).json({ message: 'Terjadi kesalahan server saat mengambil detail lowongan.' });
    } finally {
        if (connection) connection.end();
    }
});

// Route untuk Registrasi Pencari Kerja
app.post('/api/register/pencari_kerja', async (req, res) => {
    const { namaLengkap, email, password, konfirmasiPassword } = req.body;

    if (!namaLengkap || !email || !password || password !== konfirmasiPassword) {
        return res.status(400).json({ message: 'Data tidak lengkap atau password tidak cocok.' });
    }

    let connection;
    try {
        connection = await mysql.createConnection(dbConfig);
        await connection.beginTransaction();

        const username = email.split('@')[0];
        const [userResult] = await connection.execute(
            'INSERT INTO users (username, email, password, user_type) VALUES (?, ?, ?, ?)',
            [username, email, password, 'pencari_kerja']
        );
        const newUserId = userResult.insertId;

        await connection.execute(
            'INSERT INTO pencari_kerja (user_id, nama_lengkap) VALUES (?, ?)',
            [newUserId, namaLengkap]
        );

        await connection.commit();
        res.status(201).json({ message: 'Registrasi pencari kerja berhasil! Silakan login.' });

    } catch (error) {
        if (connection) await connection.rollback();
        console.error('Error saat registrasi pencari kerja:', error);
        if (error.code === 'ER_DUP_ENTRY') {
            return res.status(409).json({ message: 'Email atau username sudah terdaftar.' });
        }
        res.status(500).json({ message: 'Terjadi kesalahan server saat registrasi pencari kerja.' });
    } finally {
        if (connection) connection.end();
    }
});

// Route untuk Registrasi Perusahaan
app.post('/api/register/perusahaan', async (req, res) => {
    const { namaPerusahaan, emailPerusahaan, password, konfirmasiPassword, industri, alamat } = req.body;

    if (!namaPerusahaan || !emailPerusahaan || !password || password !== konfirmasiPassword) {
        return res.status(400).json({ message: 'Data tidak lengkap atau password tidak cocok.' });
    }

    let connection;
    try {
        connection = await mysql.createConnection(dbConfig);
        await connection.beginTransaction();

        const username = emailPerusahaan.split('@')[0];
        const [userResult] = await connection.execute(
            'INSERT INTO users (username, email, password, user_type) VALUES (?, ?, ?, ?)',
            [username, emailPerusahaan, password, 'perusahaan']
        );
        const newUserId = userResult.insertId;

        // PERBAIKAN: Kolom 'email' dihapus dari daftar kolom INSERT di tabel perusahaan,
        // karena email perusahaan sudah tersimpan di tabel 'users'.
        await connection.execute(
            'INSERT INTO perusahaan (user_id, nama_perusahaan, industri, alamat) VALUES (?, ?, ?, ?)',
            [newUserId, namaPerusahaan, industri, alamat]
        );

        await connection.commit();
        res.status(201).json({ message: 'Registrasi perusahaan berhasil! Silakan login.' });

    } catch (error) {
        if (connection) await connection.rollback();
        console.error('Error saat registrasi perusahaan:', error);
        if (error.code === 'ER_DUP_ENTRY') {
            return res.status(409).json({ message: 'Email atau username sudah terdaftar.' });
        }
        res.status(500).json({ message: 'Terjadi kesalahan server saat registrasi perusahaan.' });
    } finally {
        if (connection) connection.end();
    }
});

// Route untuk Pengajuan Lamaran
// Route untuk Pengajuan Lamaran
app.post('/api/lamar', async (req, res) => {
    const { 
        lowongan_id, 
        pencari_id, // Ini adalah profileId yang akan kita ambil dari localStorage di frontend
        nama_lengkap, 
        tanggal_lahir, 
        email, 
        nomor_hp, 
        cv, 
        portofolio, 
        surat_lamaran 
    } = req.body;

    // Tambahkan logging untuk melihat data yang diterima backend
    console.log('Menerima lamaran dengan data:', req.body); 

    // Validasi dasar
    if (!lowongan_id || !pencari_id || !nama_lengkap || !tanggal_lahir || !email || !nomor_hp || !cv) {
        console.error('Validasi Lamaran Gagal: Data tidak lengkap', req.body);
        return res.status(400).json({ message: 'Data lamaran tidak lengkap. Pastikan semua bidang wajib diisi (termasuk CV).' });
    }

    // Pastikan pencari_id adalah integer yang valid
    const parsedPencariId = parseInt(pencari_id, 10);
    if (isNaN(parsedPencariId) || parsedPencariId <= 0) {
        console.error(`Invalid pencari_id received: ${pencari_id}`);
        return res.status(400).json({ message: 'ID Pencari Kerja tidak valid. Mohon login ulang dengan akun pencari kerja yang terdaftar.' });
    }

    let connection;
    try {
        connection = await mysql.createConnection(dbConfig);
        
        await connection.execute(
            'INSERT INTO lamaran (lowongan_id, pencari_id, nama_lengkap, tanggal_lahir, email, nomor_hp, cv, portofolio, surat_lamaran) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [lowongan_id, parsedPencariId, nama_lengkap, tanggal_lahir, email, nomor_hp, cv, portofolio, surat_lamaran]
        );

        res.status(201).json({ 
            message: 'Lamaran berhasil dikirim!', 
        });

    } catch (error) {
        console.error('Error saat mengirim lamaran:', error); // Log error secara lengkap
        if (error.code === 'ER_DUP_ENTRY') {
             return res.status(409).json({ message: 'Anda sudah melamar untuk lowongan ini.' });
        }
        if (error.code === 'ER_NO_REFERENCED_ROW_2') {
             // Ini adalah error Foreign Key Constraint yang sering muncul
             return res.status(400).json({ message: 'ID Pencari Kerja tidak ditemukan dalam database atau tidak valid. Pastikan Anda login dengan akun pencari kerja yang terdaftar.' });
        }
        res.status(500).json({ message: 'Terjadi kesalahan server saat mengirim lamaran. Silakan coba lagi nanti.' });
    } finally {
        if (connection) connection.end();
    }
});

// Jalankan server
app.listen(port, () => {
    console.log(`Server backend berjalan di http://localhost:${port}`);
});