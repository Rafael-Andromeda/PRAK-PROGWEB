-- Seed akun dummy untuk database Kindnesia
USE kindnesia;

INSERT IGNORE INTO donatur (username, nama, email, no_telepon, password) VALUES
  ('budi_s', 'Budi Santoso', 'budi@email.com', '081234567890', 'donatur123'),
  ('sari_d', 'Sari Dewi', 'sari@email.com', '082345678901', 'donatur123'),
  ('andi_p', 'Andi Pratama', 'andi@email.com', '083456789012', 'donatur123');

INSERT IGNORE INTO pengelola (username, nama_pengelola, email, no_telepon, alamat, password) VALUES
  ('greenearth', 'Green Earth', 'greenearth@email.com', '021-1234567', 'Jl. Lingkungan No.1, Jakarta', 'pengelola123'),
  ('ecocity', 'Eco City', 'ecocity@email.com', '021-2345678', 'Jl. Hijau No.2, Bandung', 'pengelola123'),
  ('saveriver', 'Save River', 'saveriver@email.com', '021-3456789', 'Jl. Sungai No.3, Surabaya', 'pengelola123');
