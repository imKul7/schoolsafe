# SchoolSafe Deployment Record

## Informasi Deployment

| Informasi | Nilai |
|---|---|
| Deployment ID | |
| Tanggal dan waktu | |
| Zona waktu | |
| Operator | |
| Environment | |
| Domain | |
| Branch | |
| Commit sebelum deployment | |
| Commit setelah deployment | |
| Tag atau release | |

## Persetujuan

| Informasi | Nilai |
|---|---|
| Pemohon deployment | |
| Pemberi persetujuan | |
| Waktu persetujuan | |
| Nomor tiket atau perubahan | |

## Backup Database

| Informasi | Nilai |
|---|---|
| Backup dibuat | Ya / Tidak |
| Nama file backup | |
| Lokasi backup | |
| Ukuran backup | |
| SHA-256 | |
| Restore pernah diuji | Ya / Tidak |
| Keterangan | |

Jangan mencatat password, APP_KEY, token, atau credential lain.

## Dependency PHP

| Pemeriksaan | Hasil |
|---|---|
| `composer.lock` tersedia | |
| `composer validate --strict` | |
| `composer install` | |
| Platform requirements | |
| Optimized autoloader | |

## Build Frontend

| Pemeriksaan | Hasil |
|---|---|
| `package-lock.json` tersedia | |
| `npm ci` | |
| `npm run build` | |
| `public/build/manifest.json` | |
| `public/hot` tidak tersedia | |

## Database

| Pemeriksaan | Hasil |
|---|---|
| Status migration sebelum deployment | |
| Migration dijalankan | |
| Status migration setelah deployment | |
| Migration gagal | Ya / Tidak |
| Keterangan perubahan schema | |

## Cache Laravel

| Pemeriksaan | Hasil |
|---|---|
| Config cache | |
| Route cache | |
| View cache | |
| Event cache | |

## Storage dan Permission

| Pemeriksaan | Hasil |
|---|---|
| `public/storage` linked | |
| Target storage link | |
| `storage` writable | |
| `bootstrap/cache` writable | |

## Queue Worker

| Pemeriksaan | Hasil |
|---|---|
| Queue connection | |
| Pending jobs sebelum deployment | |
| Failed jobs sebelum deployment | |
| Queue restart | |
| Pending jobs setelah deployment | |
| Failed jobs setelah deployment | |
| Process manager aktif | |

## Scheduler

| Pemeriksaan | Hasil |
|---|---|
| Scheduler terdaftar | |
| `schedule:list` | |
| `schedule:run` | |
| Cron atau Task Scheduler aktif | |

## Health Check

| Pemeriksaan | Hasil |
|---|---|
| Maintenance mode sebelum deployment | |
| Maintenance mode setelah deployment | |
| `GET /up` | |
| `HEAD /up` | |
| Login | |
| Dashboard | |
| Asset frontend | |
| Storage publik | |

## Halaman Error

| Kode | Hasil |
|---|---|
| 403 | |
| 404 | |
| 419 | |
| 429 | |
| 500 | |
| 503 | |

Pastikan halaman error tidak menampilkan stack trace, credential, query
database, atau path internal server.

## Logging

| Pemeriksaan | Hasil |
|---|---|
| Log dapat ditulis | |
| Error baru setelah deployment | |
| Credential ditemukan dalam log | Ya / Tidak |
| Keterangan | |

## Hasil Deployment

| Informasi | Nilai |
|---|---|
| Status akhir | Berhasil / Gagal / Rollback |
| Waktu selesai | |
| Downtime | |
| Insiden | |
| Tindakan perbaikan | |
| Rollback diperlukan | Ya / Tidak |
| Release yang dipulihkan | |

## Catatan Tambahan

Tuliskan kondisi khusus, peringatan, perubahan konfigurasi, dan tindakan
lanjutan tanpa menyertakan informasi rahasia.

## Penutupan

| Informasi | Nilai |
|---|---|
| Diverifikasi oleh | |
| Waktu verifikasi | |
| Disetujui selesai oleh | |
| Waktu penutupan | |