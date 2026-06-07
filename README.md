# Kindnesia — Mini Project 2 ProgWeb

Kindnesia adalah website crowdfunding sosial yang dikembangkan dari Mini Project 1 menjadi website dinamis berbasis **PHP + MySQL + JavaScript**.

Versi ini mempertahankan file HTML/CSS dari Mini Project 1 sebagai halaman dasar/tampilan awal, lalu Mini Project 2 menambahkan halaman PHP dinamis yang mengambil data dari database.

## Entry Point Penilaian Mini Project 2

Gunakan halaman PHP berikut saat demo/penilaian:

```text
http://localhost/Kindnesia_Siap_Penilaian/index.php
```

`.htaccess` sudah diatur dengan:

```text
DirectoryIndex index.php
```

Jadi saat folder dibuka tanpa nama file, server akan memprioritaskan `index.php`, bukan `index.html`.

## Cara Menjalankan

1. Copy folder `Kindnesia_Siap_Penilaian` ke `htdocs`.
2. Import database dari `database/kindnesia.sql` lewat phpMyAdmin.
3. Pastikan konfigurasi database di `config.php` sesuai:
   - host: `localhost`
   - user: `root`
   - password: kosong
   - database: `kindnesia`
4. Buka:

```text
http://localhost/Kindnesia_Siap_Penilaian/index.php
```

## Akun Demo

Donatur:

```text
username: budi_s
password: donatur123
```

Pengelola:

```text
username: admin
password: admin123
```

## Struktur Folder

```text
Kindnesia_Siap_Penilaian/
├── index.html                # Mini Project 1: halaman statis awal
├── details.html              # Mini Project 1: halaman statis awal
├── donasi.html               # Mini Project 1: halaman statis awal
├── dashboard.html            # Mini Project 1: halaman statis awal
├── login.html                # Mini Project 1 view + dipakai sebagai halaman login
├── index.php                 # Mini Project 2: halaman utama dinamis dari DB
├── details.php               # Mini Project 2: detail kampanye dari DB
├── donasi.php                # Mini Project 2: form donasi dinamis + insert DB
├── dashboard.php             # Mini Project 2: dashboard pengelola + CRUD/verifikasi
├── donatur_kampanye.php      # Mini Project 2: daftar donatur per kampanye
├── riwayat_donasi.php        # Mini Project 2: riwayat donasi donatur
├── login.php                 # Handler login JSON untuk JS
├── logout.php
├── config.php
├── assets/
│   ├── css/                  # CSS dari Mini Project 1, dipakai ulang
│   ├── js/                   # JavaScript untuk interaksi Mini Project 2
│   └── img/
├── database/
│   ├── kindnesia.sql
│   └── seed/
└── uploads/
    ├── bukti/
    ├── kampanye/
    └── legacy/
```

## Catatan Perbaikan untuk Penilaian

- Halaman utama PHP menampilkan kampanye dari database dan hanya kampanye yang belum melewati deadline.
- Search berdasarkan judul, kategori, lokasi, dan tanggal/deadline kampanye.
- Data diurutkan dari deadline terdekat dan dana terkumpul paling kecil.
- Pagination tersedia.
- Detail kampanye diambil dari database.
- Donasi wajib login sebagai donatur.
- Data donasi masuk database dengan status awal `pending`.
- Bukti transfer disimpan di folder server, database hanya menyimpan lokasi file.
- Pengelola hanya melihat kampanye dan donasi miliknya sendiri.
- Verifikasi donasi hanya bisa dilakukan oleh pengelola pemilik kampanye.
- Jika donasi diverifikasi, `dana_terkumpul` bertambah.
- Riwayat donasi donatur menampilkan status `verified`, `pending`, dan `rejected` beserta ringkasan nominal.
