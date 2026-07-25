# SchoolSafe Production Deployment Runbook

Dokumen ini menjadi panduan deployment aplikasi SchoolSafe ke lingkungan
production.

Seluruh perintah harus dijalankan dari root project SchoolSafe.

> Jangan pernah menjalankan `php artisan migrate:fresh` pada server
> production.

---

## 1. Tujuan

Runbook ini mencakup:

- persiapan environment production;
- instalasi dependency;
- build frontend;
- migrasi database;
- pembuatan cache Laravel;
- konfigurasi storage;
- queue worker;
- scheduler;
- health check;
- verifikasi deployment;
- pemulihan apabila deployment gagal.

---

## 2. Persyaratan Server

Server production minimal harus menyediakan:

- PHP yang kompatibel dengan versi project;
- Composer;
- Node.js dan npm untuk proses build, atau artifact frontend yang sudah dibangun;
- MySQL atau database yang digunakan SchoolSafe;
- web server seperti Nginx atau Apache;
- HTTPS aktif;
- akses tulis ke folder `storage` dan `bootstrap/cache`;
- process manager untuk queue worker;
- cron atau scheduler sistem;
- mekanisme backup database.

Periksa versi runtime:

```bash
php --version
composer --version
node --version
npm --version