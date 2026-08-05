# SchoolSafe

SchoolSafe adalah aplikasi keamanan penjemputan siswa multi-tenant untuk sekolah. Aplikasi ini memadukan manajemen sekolah, siswa, penjemput, profil wajah, verifikasi gerbang, transaksi penjemputan, dan audit aktivitas dengan otorisasi yang ketat serta isolasi tenant.

## Fitur inti

- Manajemen sekolah, akun, kelas, siswa, dan penjemput.
- Pendaftaran dan revokasi profil wajah yang terkontrol.
- Verifikasi wajah gerbang dengan challenge liveness dan attempt yang aman.
- Konfirmasi dan pembatalan transaksi penjemputan dengan idempotency dan audit.
- Dashboard tenant-aware dengan statistik sekolah yang sedang login.
- Middleware otorisasi berbasis role dan pemeliharaan sesi untuk akun tidak aktif atau sekolah tidak aktif.

## Persyaratan lokal

- PHP 8.2+
- Composer
- Node.js 20+ dan npm
- MySQL/MariaDB untuk lingkungan pengembangan dan testing

## Instalasi lokal

```bash
cp .env.example .env
composer install
npm ci
php artisan key:generate
php artisan migrate
php artisan db:seed
npm run dev
```

## Konfigurasi database testing

Gunakan database terpisah untuk testing. Contoh:

```bash
cp .env.example .env.testing
php artisan test
```

## Menjalankan pemeriksaan

```bash
vendor/bin/pint --test
php artisan test
npm run test:frontend
npm run types:check
npm run lint:check
npm run format:check
npm audit --omit=dev
npm run build
```

## Peran demo

- super_admin: kontrol platform dan observabilitas lintas tenant.
- school_admin: administrasi sekolah, siswa, penjemput, dan transaksi.
- gate_officer: operasi gerbang dan verifikasi wajah.
- teacher: akses terbatas untuk kebutuhan sekolah.
- parent: akses data yang secara eksplisit diizinkan.

## Batasan fitur

- Registrasi publik dinonaktifkan; pembuatan akun dilakukan melalui alur administratif.
- Data biometrik sensitif disimpan di storage private dan tidak dipublikasikan secara terbuka.
- Deploy production harus menggunakan template .env.production.example dan script deployment.
