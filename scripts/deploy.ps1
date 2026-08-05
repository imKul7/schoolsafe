$ErrorActionPreference = 'Stop'

param(
    [switch]$ValidateOnly
)

$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $projectRoot

function Write-Info {
    param([string]$Message)
    Write-Host "[deploy] $Message"
}

function Assert-Command {
    param([string]$CommandName)

    if (-not (Get-Command $CommandName -ErrorAction SilentlyContinue)) {
        throw "Command yang dibutuhkan tidak tersedia: $CommandName"
    }
}

function Assert-NonEmptyEnv {
    param([string]$Name)

    $value = [Environment]::GetEnvironmentVariable($Name)
    if ([string]::IsNullOrWhiteSpace($value)) {
        throw "Environment $Name tidak boleh kosong"
    }
}

function Ensure-WorkingTreeClean {
    $status = git status --porcelain
    if ($status) {
        throw 'Working tree belum bersih. Lakukan commit atau stash sebelum deployment.'
    }
}

function Ensure-PhpArtisanAvailable {
    Assert-Command -CommandName 'php'
    Assert-Command -CommandName 'composer'
    Assert-Command -CommandName 'npm'
}

Write-Info 'Memulai validasi deployment SchoolSafe'

if ($ValidateOnly) {
    Write-Info 'Mode ValidateOnly aktif; hanya memvalidasi persyaratan.'
}

Ensure-PhpArtisanAvailable

$envName = [Environment]::GetEnvironmentVariable('APP_ENV')
if ($envName -ne 'production') {
    throw 'APP_ENV harus bernilai production untuk deployment.'
}

Assert-NonEmptyEnv -Name 'APP_KEY'
Assert-NonEmptyEnv -Name 'APP_URL'

if (-not [Environment]::GetEnvironmentVariable('APP_URL').StartsWith('https://')) {
    throw 'APP_URL harus menggunakan HTTPS.'
}

$storageModelDir = Join-Path $projectRoot 'public/models/human'
if (-not (Test-Path $storageModelDir)) {
    throw 'Model biometrik tidak ditemukan di public/models/human.'
}

Ensure-WorkingTreeClean

if ($ValidateOnly) {
    Write-Info 'Validasi selesai. Tidak ada perubahan yang diterapkan.'
    exit 0
}

Write-Info 'Memeriksa backup database sebelum migrasi'
$backupScript = Join-Path $projectRoot 'scripts/backup-database.ps1'
if (-not (Test-Path $backupScript)) {
    throw 'Script backup database tidak ditemukan.'
}
& $backupScript

Write-Info 'Mengaktifkan maintenance mode'
php artisan down --message='Deployment sedang berlangsung. Harap tunggu.' --retry=60

try {
    Write-Info 'Melakukan git pull fast-forward'
    git pull --ff-only

    Write-Info 'Menginstall dependency produksi'
    composer install --no-interaction --prefer-dist --no-progress --optimize-autoloader --no-dev

    Write-Info 'Menginstall dependency frontend'
    npm ci

    Write-Info 'Membangun aset produksi'
    npm run build

    Write-Info 'Membersihkan cache Laravel'
    php artisan cache:clear
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear

    Write-Info 'Menjalankan migrasi database'
    php artisan migrate --force

    Write-Info 'Menghubungkan storage'
    php artisan storage:link

    Write-Info 'Optimasi Laravel'
    php artisan optimize

    Write-Info 'Memulai ulang queue worker'
    php artisan queue:restart

    Write-Info 'Menjalankan status migrasi'
    php artisan migrate:status

    Write-Info 'Mengambil health check'
    $healthResponse = Invoke-WebRequest -Uri "$([Environment]::GetEnvironmentVariable('APP_URL'))/up" -Method Get -MaximumRedirection 0 -ErrorAction SilentlyContinue
    if ($healthResponse.StatusCode -ne 200) {
        throw "Health check /up gagal dengan status $($healthResponse.StatusCode)"
    }
}
catch {
    Write-Error $_
    throw 'Deployment gagal. Kembalikan aplikasi ke mode normal setelah perbaikan dilakukan.'
}
finally {
    Write-Info 'Melepas maintenance mode'
    php artisan up
}

Write-Info 'Deployment selesai.'
