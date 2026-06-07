-- ============================================================
-- Kindnesia — Database Mini Project #2
-- Import utama untuk penilaian praktikum.
-- Database: kindnesia
-- ============================================================

CREATE DATABASE IF NOT EXISTS kindnesia
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE kindnesia;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS donasi;
DROP TABLE IF EXISTS kampanye;
DROP TABLE IF EXISTS pengelola;
DROP TABLE IF EXISTS donatur;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- TABEL DONATUR
-- Minimal menyimpan: nama, email, nomor telepon.
-- ============================================================
CREATE TABLE donatur (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username    VARCHAR(100)  NOT NULL UNIQUE,
  nama        VARCHAR(200)  NOT NULL,
  email       VARCHAR(200)  NOT NULL UNIQUE,
  no_telepon  VARCHAR(20)   DEFAULT NULL,
  password    VARCHAR(255)  NOT NULL,
  created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABEL PENGELOLA / PENYELENGGARA
-- Minimal menyimpan: nama kantor, email, nomor telepon, alamat.
-- ============================================================
CREATE TABLE pengelola (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username        VARCHAR(100)  NOT NULL UNIQUE,
  nama_pengelola  VARCHAR(200)  NOT NULL,
  email           VARCHAR(200)  NOT NULL UNIQUE,
  no_telepon      VARCHAR(20)   DEFAULT NULL,
  alamat          TEXT          DEFAULT NULL,
  password        VARCHAR(255)  NOT NULL,
  qris_image      VARCHAR(300)  DEFAULT NULL,
  no_ewallet      VARCHAR(100)  DEFAULT NULL,
  nama_ewallet    VARCHAR(100)  DEFAULT NULL,
  no_rekening     VARCHAR(100)  DEFAULT NULL,
  nama_bank       VARCHAR(100)  DEFAULT NULL,
  atas_nama       VARCHAR(200)  DEFAULT NULL,
  created_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABEL KAMPANYE
-- Gambar hanya menyimpan nama/lokasi file, bukan BLOB.
-- ============================================================
CREATE TABLE kampanye (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pengelola_id      INT UNSIGNED NOT NULL,
  judul             VARCHAR(300) NOT NULL,
  kategori          ENUM('Lingkungan','Kesehatan','Pendidikan','Bencana','Fasilitas Umum') NOT NULL,
  lokasi            VARCHAR(200) DEFAULT NULL,
  deskripsi         TEXT         DEFAULT NULL,
  gambar            VARCHAR(300) DEFAULT NULL,
  target_dana       DECIMAL(15,2) NOT NULL DEFAULT 0,
  dana_terkumpul    DECIMAL(15,2) NOT NULL DEFAULT 0,
  deadline          DATE         NOT NULL,
  metode_donasi     VARCHAR(200) DEFAULT 'Transfer Bank, E-Wallet, QRIS',
  created_at        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_deadline (deadline),
  INDEX idx_pengelola (pengelola_id),
  CONSTRAINT fk_kampanye_pengelola
    FOREIGN KEY (pengelola_id) REFERENCES pengelola(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABEL DONASI
-- Status: pending -> verified / rejected oleh pengelola.
-- Bukti transfer hanya menyimpan nama/lokasi file, bukan BLOB.
-- ============================================================
CREATE TABLE donasi (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kampanye_id   INT UNSIGNED NOT NULL,
  donatur_id    INT UNSIGNED NOT NULL,
  nominal       DECIMAL(15,2) NOT NULL,
  metode        VARCHAR(100)  NOT NULL,
  bukti_file    VARCHAR(300)  DEFAULT NULL,
  pesan         TEXT          DEFAULT NULL,
  status        ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  verified_at   DATETIME      DEFAULT NULL,
  INDEX idx_kampanye_status (kampanye_id, status),
  INDEX idx_donatur (donatur_id),
  CONSTRAINT fk_donasi_kampanye
    FOREIGN KEY (kampanye_id) REFERENCES kampanye(id) ON DELETE CASCADE,
  CONSTRAINT fk_donasi_donatur
    FOREIGN KEY (donatur_id) REFERENCES donatur(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- AKUN DEMO
-- Password donatur:   donatur123
-- Password pengelola: pengelola123
-- ============================================================
INSERT INTO donatur (username, nama, email, no_telepon, password) VALUES
  ('budi_s', 'Budi Santoso', 'budi@email.com', '081234567890', 'donatur123'),
  ('sari_d', 'Sari Dewi', 'sari@email.com', '082345678901', 'donatur123'),
  ('andi_p', 'Andi Pratama', 'andi@email.com', '083456789012', 'donatur123');

INSERT INTO pengelola (username, nama_pengelola, email, no_telepon, alamat, password, qris_image, no_ewallet, nama_ewallet, no_rekening, nama_bank, atas_nama) VALUES
  ('greenearth', 'Green Earth', 'greenearth@email.com', '021-1234567', 'Jl. Lingkungan No.1, Jakarta', 'pengelola123', 'qris/qris_default.png', '081234567890', 'GoPay / OVO / Dana', '1234567890', 'BCA', 'Green Earth'),
  ('ecocity', 'Eco City Foundation', 'ecocity@email.com', '021-2345678', 'Jl. Hijau No.2, Bandung', 'pengelola123', NULL, '082345678901', 'GoPay / Dana', '0987654321', 'Mandiri', 'Eco City Foundation'),
  ('saveriver', 'Save River Indonesia', 'saveriver@email.com', '021-3456789', 'Jl. Sungai No.3, Surabaya', 'pengelola123', NULL, '083456789012', 'OVO / Dana', '1122334455', 'BNI', 'Save River Indonesia');

-- ============================================================
-- DATA KAMPANYE DEMO
-- Deadline memakai CURDATE() agar tetap tampil saat dinilai.
-- ============================================================
INSERT INTO kampanye
  (pengelola_id, judul, kategori, lokasi, deskripsi, gambar, target_dana, dana_terkumpul, deadline, metode_donasi)
VALUES
  (1, 'Reboisasi Hutan Jawa Barat', 'Lingkungan', 'Jawa Barat, Indonesia',
   'Menanam kembali ribuan pohon di kawasan hutan kritis Jawa Barat untuk mengurangi risiko banjir dan menjaga ekosistem.',
   'kampanye_1778754384_1.jpeg', 50000000, 25000000, DATE_ADD(CURDATE(), INTERVAL 20 DAY), 'Transfer Bank, E-Wallet, QRIS'),

  (2, '1000 Pohon Kota Sehat', 'Lingkungan', 'Jakarta, Indonesia',
   'Program penghijauan kota dengan menanam 1000 pohon di area urban Jakarta.',
   'kampanye_1778757219_1.jpeg', 30000000, 10000000, DATE_ADD(CURDATE(), INTERVAL 34 DAY), 'Transfer Bank, QRIS'),

  (3, 'Bersih Sungai Nasional', 'Lingkungan', 'Ciliwung, Jakarta',
   'Aksi bersih sungai nasional untuk mengurangi polusi air dan meningkatkan kepedulian masyarakat.',
   NULL, 20000000, 15000000, DATE_ADD(CURDATE(), INTERVAL 47 DAY), 'Transfer Bank, E-Wallet'),

  (1, 'Bantuan Alat Sekolah Anak Desa', 'Pendidikan', 'Garut, Jawa Barat',
   'Penggalangan dana untuk membeli buku, tas, dan alat tulis bagi anak-anak desa.',
   NULL, 12000000, 0, DATE_ADD(CURDATE(), INTERVAL 25 DAY), 'Transfer Bank, QRIS');

-- ============================================================
-- DATA DONASI DEMO
-- Ada pending, verified, dan rejected untuk memudahkan demo rubrik.
-- Dana verified sudah tercermin dalam dana_terkumpul kampanye demo.
-- ============================================================
INSERT INTO donasi
  (kampanye_id, donatur_id, nominal, metode, bukti_file, pesan, status, created_at, verified_at)
VALUES
  (1, 1, 100000, 'Transfer Bank', 'bukti_1778570849_1.png', 'Semoga hutannya kembali hijau.', 'verified', DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
  (1, 2, 75000, 'QRIS', 'bukti_1778753681_1.png', 'Dukung program lingkungan.', 'pending', DATE_SUB(NOW(), INTERVAL 1 DAY), NULL),
  (2, 3, 50000, 'QRIS', 'bukti_1778753684_1.png', 'Semoga bermanfaat.', 'rejected', DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY));

-- ============================================================
-- MIGRASI: Tambah kolom pembayaran ke tabel pengelola
-- Jalankan jika database sudah ada sebelumnya
-- ============================================================
-- ALTER TABLE pengelola ADD COLUMN IF NOT EXISTS qris_image   VARCHAR(300) DEFAULT NULL;
-- ALTER TABLE pengelola ADD COLUMN IF NOT EXISTS no_ewallet   VARCHAR(100) DEFAULT NULL;
-- ALTER TABLE pengelola ADD COLUMN IF NOT EXISTS nama_ewallet VARCHAR(100) DEFAULT NULL;
-- ALTER TABLE pengelola ADD COLUMN IF NOT EXISTS no_rekening  VARCHAR(100) DEFAULT NULL;
-- ALTER TABLE pengelola ADD COLUMN IF NOT EXISTS nama_bank    VARCHAR(100) DEFAULT NULL;
-- ALTER TABLE pengelola ADD COLUMN IF NOT EXISTS atas_nama    VARCHAR(200) DEFAULT NULL;
