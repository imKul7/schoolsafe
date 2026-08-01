$ErrorActionPreference = "Stop"

$expectedBranch = "chore/production-readiness"
$currentBranch = (git branch --show-current).Trim()

if ($currentBranch -ne $expectedBranch) {
    throw "Branch aktif harus $expectedBranch. Saat ini: $currentBranch"
}

$filesToCreate = @(
    "DEPLOYMENT.md",
    "scripts\deploy.ps1",
    "scripts\backup-database.ps1"
)

foreach ($path in $filesToCreate) {
    if (Test-Path $path) {
        throw "File sudah tersedia dan tidak akan ditimpa: $path"
    }
}

New-Item `
    -Path "scripts" `
    -ItemType Directory `
    -Force |
    Out-Null

function Write-Utf8LfFile {
    param(
        [Parameter(Mandatory)]
        [string] $Path,

        [Parameter(Mandatory)]
        [AllowEmptyString()]
        [string] $Content
    )

    $normalizedContent =
        $Content.Replace("`r`n", "`n").Replace("`r", "`n")

    $normalizedContent =
        $normalizedContent.TrimEnd("`n") + "`n"

    $utf8WithoutBom =
        New-Object System.Text.UTF8Encoding($false)

    [System.IO.File]::WriteAllText(
        (Join-Path (Get-Location) $Path),
        $normalizedContent,
        $utf8WithoutBom
    )

    Write-Output "CREATED: $Path"
}

$deploymentDocumentation = @'
# SchoolSafe Production Deployment

Dokumen ini menjelaskan deployment, backup, health check, scheduler, queue worker, dan rollback aplikasi SchoolSafe.

## 1. Prinsip deployment

Deployment production harus memenuhi ketentuan berikut:

- branch yang digunakan adalah `main`;
- working tree server bersih;
- `.env` production tidak dilacak Git;
- `APP_ENV=production`;
- `APP_DEBUG=false`;
- `APP_KEY` sudah dibuat dan tidak kosong;
- koneksi database production sudah diuji;
- backup database dibuat sebelum perubahan aplikasi;
- web server diarahkan ke folder `public`;
- HTTPS aktif;
- model biometrik tersedia di `public/models/human`;
- endpoint `/up` dapat diakses setelah deployment.

Script `scripts/deploy.ps1` menggunakan deployment in-place. Aplikasi akan masuk maintenance mode selama kode, dependency, migration, build, dan cache diperbarui.

Apabila deployment gagal setelah maintenance mode aktif, aplikasi tetap berada dalam maintenance mode. Lakukan rollback atau perbaikan sebelum menjalankan:

```powershell
php artisan up