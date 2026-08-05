# SchoolSafe Architecture

## Boundary modul

- Auth dan user management: autentikasi, sesi, role, status akun, status sekolah.
- School domain: sekolah, kelas, siswa, penjemput, relasi siswa-penjemput.
- Gate domain: verifikasi wajah, challenge liveness, pembentukan transaksi penjemputan, konfirmasi, pembatalan.
- Dashboard: agregasi tenant-aware berdasarkan sekolah pengguna yang sedang login.

## Tenant isolation

Setiap entitas bisnis yang terkait sekolah harus menyimpan school_id dan memeriksa ownership di level backend. Middleware aktif memblokir akun tidak aktif atau sekolah tidak aktif sebelum request dilanjutkan.

## Authorization flow

- Middleware role membatasi akses menu dan endpoint.
- Controller melakukan verifikasi tenant dan ownership tambahan sebelum mengakses resource.
- Request classes serta controller guards memastikan pembatalan dan detail transaksi hanya tersedia untuk role yang diizinkan.

## Biometric verification flow

1. Gate officer membuka halaman face verification.
2. Challenge liveness dibuat dengan TTL terbatas dan session binding.
3. Probe wajah dikirim ke backend.
4. Backend memvalidasi tenant, attempt, liveness, kualitas, dan status akun.
5. Hasil verifikasi digunakan untuk mempersiapkan transaksi penjemputan.

## Pickup transaction state machine

Status transaksi mengikuti alur confirmed, cancelled, dan status pending yang diturunkan dari operasi tertentu. Pembatalan parsial dan penuh menggunakan audit trail untuk menjaga integritas sejarah.

## Database transaction strategy

Transaksi utama menggunakan database transaction, unique constraint, dan lock yang sesuai untuk mencegah race condition. Idempotency key digunakan pada konfirmasi untuk mencegah double submit.

## Cache policy

Respons sensitif menggunakan cache prevention headers seperti private, no-store, no-cache, max-age=0, must-revalidate, Pragma: no-cache, dan Expires: 0 melalui middleware yang ada.

## Security decisions

- Public registration dinonaktifkan.
- Data biometrik sensitif disimpan pada storage private.
- Password, token, dan data sensitif tidak boleh masuk ke log.
- Production deployment memerlukan HTTPS, secure cookie, dan APP_KEY yang valid.
