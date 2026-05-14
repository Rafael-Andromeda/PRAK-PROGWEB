-- Seed data dummy untuk database Kindnesia
USE kindnesia;

-- Jika belum ada pengelola, tambahkan terlebih dahulu.
INSERT INTO pengelola (username, nama_pengelola, email, no_telepon, alamat, password)
VALUES
  ('dummy_org', 'Dummy Organisasi', 'dummy@kindnesia.test', '081234567890', 'Jl. Contoh No.1, Bandung', 'dummy123');

SET @pengelola_id = LAST_INSERT_ID();

INSERT INTO kampanye (pengelola_id, judul, kategori, lokasi, deskripsi, target_dana, dana_terkumpul, deadline, metode_donasi)
VALUES
  (@pengelola_id,
   'Donasi Sampel Penanaman Pohon Sekolah',
   'Lingkungan',
   'Bandung, Indonesia',
   'Mendukung penghijauan sekolah dengan menanam pohon dan merawat taman belajar.',
   20000000,
   5000000,
   DATE_ADD(CURDATE(), INTERVAL 30 DAY),
   'Transfer Bank, E-Wallet, QRIS');
