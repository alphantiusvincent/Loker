// Loker/server/server.js

const express = require('express');
const mysql = require('mysql2/promise'); // Menggunakan mysql2/promise untuk async/await
const cors = require('cors'); // Untuk mengatasi masalah CORS

const app = express();
const port = 3000; // Port untuk server backend Anda

// Middleware
app.use(cors()); // Izinkan semua permintaan dari domain lain (untuk pengembangan)
app.use(express.json()); // Izinkan server untuk membaca JSON dari body request

// Konfigurasi koneksi database
const dbConfig = {
    host: 'localhost',
    user: 'root', // <<< GANTI DENGAN USER DATABASE ANDA (misalnya 'root')
    password: '', // <<< GANTI DENGAN PASSWORD DATABASE ANDA (misalnya '' jika kosong)
    database: 'bawamap' // Nama database yang Anda buat dari bawamap.sql
};

// Route untuk login
app.post('/api/login', async (req, res) => {
    const { username, password } = req.body; // Ambil username dan password dari request body

    if (!username || !password) {
        return res.status(400).json({ message: 'Username dan password harus diisi.' });
    }

    let connection;
    try {
        connection = await mysql.createConnection(dbConfig);
        // Peringatan: Ini adalah contoh sederhana untuk DEMO.
        // Password HARUS di-hash (bcrypt) di database dan diverifikasi dengan library hashing di backend.
        // Saat ini, query ini akan mencoba mencocokkan password plain text yang Anda kirim dengan hash di DB.
        // Ini TIDAK AKAN BERHASIL jika password di DB masih hash "$2y$10$..." kecuali Anda ubah password di DB menjadi plain text seperti 'password'
        // Untuk DEMO, Anda bisa mengubah password 'john_doe' di tabel users menjadi 'password' (plain text) di phpMyAdmin untuk mudahnya.
        const [rows] = await connection.execute(
            'SELECT user_id, username, user_type FROM users WHERE username = ? AND password = ?',
            [username, password]
        );

        if (rows.length > 0) {
            const user = rows[0];
            res.status(200).json({
                message: 'Login berhasil!',
                isLoggedIn: true,
                username: user.username,
                userType: user.user_type,
                userId: user.user_id
            });
        } else {
            res.status(401).json({ message: 'Username atau password salah.' });
        }
    } catch (error) {
        console.error('Error saat login:', error);
        res.status(500).json({ message: 'Terjadi kesalahan server.' });
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
        res.status(500).json({ message: 'Terjadi kesalahan server.' });
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
        res.status(500).json({ message: 'Terjadi kesalahan server.' });
    } finally {
        if (connection) connection.end();
    }
});

// Contoh Route untuk Registrasi (Pencari Kerja) - Anda bisa kembangkan ini
// Ini adalah dasar untuk register. Anda perlu membuat form register.html yang sesuai.
app.post('/api/register/pencari_kerja', async (req, res) => {
    const { namaLengkap, email, password, konfirmasiPassword } = req.body;

    if (!namaLengkap || !email || !password || password !== konfirmasiPassword) {
        return res.status(400).json({ message: 'Data tidak lengkap atau password tidak cocok.' });
    }

    // Dalam aplikasi nyata: hash password dengan bcrypt sebelum disimpan!
    // const bcrypt = require('bcrypt'); // Anda perlu npm install bcrypt
    // const hashedPassword = await bcrypt.hash(password, 10);

    let connection;
    try {
        connection = await mysql.createConnection(dbConfig);
        await connection.beginTransaction(); // Mulai transaksi

        // 1. Masukkan ke tabel users
        // Untuk demo, username diambil dari bagian email sebelum '@'
        const [userResult] = await connection.execute(
            'INSERT INTO users (username, email, password, user_type) VALUES (?, ?, ?, ?)',
            [email.split('@')[0], email, password, 'pencari_kerja'] // Menggunakan password plain text untuk demo
        );
        const newUserId = userResult.insertId;

        // 2. Masukkan ke tabel pencari_kerja
        await connection.execute(
            'INSERT INTO pencari_kerja (user_id, nama_lengkap) VALUES (?, ?)',
            [newUserId, namaLengkap]
        );

        await connection.commit(); // Commit transaksi
        res.status(201).json({ message: 'Registrasi pencari kerja berhasil!' });

    } catch (error) {
        if (connection) await connection.rollback(); // Rollback jika ada error
        console.error('Error saat registrasi pencari kerja:', error);
        if (error.code === 'ER_DUP_ENTRY') { // Error jika username/email duplikat
            return res.status(409).json({ message: 'Email atau username sudah terdaftar.' });
        }
        res.status(500).json({ message: 'Terjadi kesalahan server saat registrasi.' });
    } finally {
        if (connection) connection.end();
    }
});


// Jalankan server
app.listen(port, () => {
    console.log(`Server backend berjalan di http://localhost:${port}`);
});
// --- Route untuk Registrasi Pencari Kerja ---
app.post('/api/register/pencari_kerja', async (req, res) => {
    const { namaLengkap, email, password, konfirmasiPassword } = req.body;

    if (!namaLengkap || !email || !password || password !== konfirmasiPassword) {
        return res.status(400).json({ message: 'Data tidak lengkap atau password tidak cocok.' });
    }

    // PENTING: Dalam aplikasi nyata, password HARUS di-hash (misalnya dengan bcrypt) sebelum disimpan!
    // Untuk demo ini, kita akan menyimpan password plain text untuk memudahkan pengujian.
    // const bcrypt = require('bcrypt'); // Anda perlu npm install bcrypt
    // const hashedPassword = await bcrypt.hash(password, 10);

    let connection;
    try {
        connection = await mysql.createConnection(dbConfig);
        await connection.beginTransaction(); // Mulai transaksi

        // 1. Masukkan ke tabel users
        // Username diambil dari bagian email sebelum '@'
        const username = email.split('@')[0];
        const [userResult] = await connection.execute(
            'INSERT INTO users (username, email, password, user_type) VALUES (?, ?, ?, ?)',
            [username, email, password, 'pencari_kerja'] // Menggunakan password plain text untuk demo
        );
        const newUserId = userResult.insertId;

        // 2. Masukkan ke tabel pencari_kerja
        await connection.execute(
            'INSERT INTO pencari_kerja (user_id, nama_lengkap) VALUES (?, ?)',
            [newUserId, namaLengkap]
        );

        await connection.commit(); // Commit transaksi
        res.status(201).json({ message: 'Registrasi pencari kerja berhasil! Silakan login.' });

    } catch (error) {
        if (connection) await connection.rollback(); // Rollback jika ada error
        console.error('Error saat registrasi pencari kerja:', error);
        if (error.code === 'ER_DUP_ENTRY') { // Error jika username/email duplikat
            return res.status(409).json({ message: 'Email atau username sudah terdaftar.' });
        }
        res.status(500).json({ message: 'Terjadi kesalahan server saat registrasi.' });
    } finally {
        if (connection) connection.end();
    }
});

// --- Route untuk Registrasi Perusahaan ---
app.post('/api/register/perusahaan', async (req, res) => {
    const { namaPerusahaan, emailPerusahaan, password, konfirmasiPassword, industri, alamat } = req.body;

    if (!namaPerusahaan || !emailPerusahaan || !password || password !== konfirmasiPassword) {
        return res.status(400).json({ message: 'Data tidak lengkap atau password tidak cocok.' });
    }

    // PENTING: Dalam aplikasi nyata, password HARUS di-hash!
    // const bcrypt = require('bcrypt');
    // const hashedPassword = await bcrypt.hash(password, 10);

    let connection;
    try {
        connection = await mysql.createConnection(dbConfig);
        await connection.beginTransaction(); // Mulai transaksi

        // 1. Masukkan ke tabel users
        // Username diambil dari bagian email perusahaan sebelum '@'
        const username = emailPerusahaan.split('@')[0];
        const [userResult] = await connection.execute(
            'INSERT INTO users (username, email, password, user_type) VALUES (?, ?, ?, ?)',
            [username, emailPerusahaan, password, 'perusahaan'] // Menggunakan password plain text untuk demo
        );
        const newUserId = userResult.insertId;

        // 2. Masukkan ke tabel perusahaan
        await connection.execute(
            'INSERT INTO perusahaan (user_id, nama_perusahaan, email, industri, alamat) VALUES (?, ?, ?, ?, ?)',
            [newUserId, namaPerusahaan, emailPerusahaan, industri, alamat]
        );

        await connection.commit(); // Commit transaksi
        res.status(201).json({ message: 'Registrasi perusahaan berhasil! Silakan login.' });

    } catch (error) {
        if (connection) await connection.rollback(); // Rollback jika ada error
        console.error('Error saat registrasi perusahaan:', error);
        if (error.code === 'ER_DUP_ENTRY') { // Error jika username/email duplikat
            return res.status(409).json({ message: 'Email atau username sudah terdaftar.' });
        }
        res.status(500).json({ message: 'Terjadi kesalahan server saat registrasi.' });
    } finally {
        if (connection) connection.end();
    }
});