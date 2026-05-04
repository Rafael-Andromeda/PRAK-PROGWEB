-- ============================================================
-- Kindnesia — Database Schema (Mini Project #2)
-- Jalankan di phpMyAdmin: Import file ini, atau copy-paste ke SQL tab
-- ============================================================

CREATE DATABASE IF NOT EXISTS kindnesia
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE kindnesia;

-- ============================================================
-- TABEL DONATUR
-- Menyimpan: nama, email, no_telepon (sesuai rubrik)
-- ============================================================
CREATE TABLE IF NOT EXISTS donatur (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username    VARCHAR(100)  NOT NULL UNIQUE,
  nama        VARCHAR(200)  NOT NULL,
  email       VARCHAR(200)  NOT NULL UNIQUE,
  no_telepon  VARCHAR(20)   DEFAULT NULL,
  password    VARCHAR(255)  NOT NULL,
  created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABEL PENGELOLA (Penyelenggara Kampanye)
-- Menyimpan: nama_pengelola (kantor), email, no_telepon, alamat (sesuai rubrik)
-- ============================================================
CREATE TABLE IF NOT EXISTS pengelola (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username        VARCHAR(100)  NOT NULL UNIQUE,
  nama_pengelola  VARCHAR(200)  NOT NULL,   -- Nama kantor/organisasi
  email           VARCHAR(200)  NOT NULL UNIQUE,
  no_telepon      VARCHAR(20)   DEFAULT NULL,
  alamat          TEXT          DEFAULT NULL,
  password        VARCHAR(255)  NOT NULL,
  created_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABEL KAMPANYE
-- (Dibutuhkan Mini Project #2 — data dari DB)
-- ============================================================
CREATE TABLE IF NOT EXISTS kampanye (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pengelola_id      INT UNSIGNED NOT NULL,
  judul             VARCHAR(300) NOT NULL,
  kategori          ENUM('Lingkungan','Kesehatan','Pendidikan','Bencana','Fasilitas Umum') NOT NULL,
  lokasi            VARCHAR(200) DEFAULT NULL,
  deskripsi         TEXT         DEFAULT NULL,
  gambar            VARCHAR(300) DEFAULT NULL,   -- path file di server
  target_dana       DECIMAL(15,2) NOT NULL DEFAULT 0,
  dana_terkumpul    DECIMAL(15,2) NOT NULL DEFAULT 0,
  deadline          DATE         NOT NULL,
  metode_donasi     VARCHAR(200) DEFAULT 'Transfer Bank, E-Wallet, QRIS',
  created_at        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (pengelola_id) REFERENCES pengelola(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABEL DONASI
-- Status: pending → verified / rejected oleh pengelola
-- ============================================================
CREATE TABLE IF NOT EXISTS donasi (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kampanye_id   INT UNSIGNED NOT NULL,
  donatur_id    INT UNSIGNED NOT NULL,
  nominal       DECIMAL(15,2) NOT NULL,
  metode        VARCHAR(100)  NOT NULL,
  bukti_file    VARCHAR(300)  DEFAULT NULL,   -- path file bukti transfer
  pesan         TEXT          DEFAULT NULL,
  status        ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  verified_at   TIMESTAMP     DEFAULT NULL,
  FOREIGN KEY (kampanye_id)  REFERENCES kampanye(id)  ON DELETE CASCADE,
  FOREIGN KEY (donatur_id)   REFERENCES donatur(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- DATA CONTOH — Donatur
-- password plain text: "donatur123"
-- Untuk produksi ganti dengan: password_hash('donatur123', PASSWORD_DEFAULT)
-- ============================================================
INSERT IGNORE INTO donatur (username, nama, email, no_telepon, password) VALUES
  ('budi_s',   'Budi Santoso',   'budi@email.com',   '081234567890', 'donatur123'),
  ('sari_d',   'Sari Dewi',      'sari@email.com',   '082345678901', 'donatur123'),
  ('andi_p',   'Andi Pratama',   'andi@email.com',   '083456789012', 'donatur123');

-- ============================================================
-- DATA CONTOH — Pengelola
-- password plain text: "pengelola123"
-- ============================================================
INSERT IGNORE INTO pengelola (username, nama_pengelola, email, no_telepon, alamat, password) VALUES
  ('greenearth',  'Green Earth',  'greenearth@email.com',  '021-1234567', 'Jl. Lingkungan No.1, Jakarta',  'pengelola123'),
  ('ecocity',     'Eco City',     'ecocity@email.com',     '021-2345678', 'Jl. Hijau No.2, Bandung',       'pengelola123'),
  ('saveriver',   'Save River',   'saveriver@email.com',   '021-3456789', 'Jl. Sungai No.3, Surabaya',     'pengelola123');

-- ============================================================
-- DATA CONTOH — Kampanye
-- ============================================================
INSERT IGNORE INTO kampanye (pengelola_id, judul, kategori, lokasi, deskripsi, target_dana, dana_terkumpul, deadline) VALUES
  (1, 'Reboisasi Hutan Jawa Barat',
      'Lingkungan', 'Jawa Barat, Indonesia',
      'Menanam kembali ribuan pohon di kawasan hutan kritis Jawa Barat.',
      50000000, 25000000, DATE_ADD(CURDATE(), INTERVAL 20 DAY)),

  (2, '1000 Pohon Kota Sehat',
      'Lingkungan', 'Jakarta, Indonesia',
      'Penghijauan kota dengan menanam 1000 pohon di area urban Jakarta.',
      30000000, 10000000, DATE_ADD(CURDATE(), INTERVAL 34 DAY)),

  (3, 'Bersih Sungai Nasional',
      'Lingkungan', 'Ciliwung, Jakarta',
      'Aksi bersih sungai nasional untuk mengurangi polusi air.',
      20000000, 15000000, DATE_ADD(CURDATE(), INTERVAL 47 DAY));
