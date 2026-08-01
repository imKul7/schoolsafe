# SchoolSafe Security

## Reporting process

Laporkan temuan keamanan melalui saluran resmi tim atau kanal internal yang ditetapkan. Jangan mengungkapkan detail secara publik sebelum penanganan selesai.

## Threat model summary

- Tenant isolation dan authorization yang salah dapat mengekspos data sekolah lain.
- Challenge face verification dapat disalahgunakan oleh replay atau double submit.
- File media dan data biometrik sensitif perlu dipisahkan dari media publik.

## Secret policy

- Jangan mengunggah .env, token, kunci, password, atau secret ke repository.
- Gunakan placeholder pada template environment.

## Biometric privacy

- Foto wajah, embedding, dan metadata sensitif disimpan di storage private.
- Akses file private harus melalui otorisasi yang sesuai.
- Data biometrik tidak dipublikasikan ke log, frontend, atau endpoint yang tidak perlu.

## Retention and incident response

- Retensi audit biometrik diatur melalui konfigurasi aplikasi.
- Semua perubahan kritis harus dicatat dalam audit trail.
- Insiden keamanan harus ditangani melalui prosedur rollback, pemulihan, dan review.
