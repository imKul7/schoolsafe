$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $projectRoot

$backupDir = Join-Path $projectRoot 'storage/app/backups'
if (-not (Test-Path $backupDir)) {
    New-Item -ItemType Directory -Path $backupDir -Force | Out-Null
}

$timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$dumpPath = Join-Path $backupDir "schoolsafe-$timestamp.sql"
$archivePath = Join-Path $backupDir "schoolsafe-$timestamp.sql.gz"
$shaPath = Join-Path $backupDir "schoolsafe-$timestamp.sha256"
$tempSqlPath = Join-Path $backupDir "schoolsafe-$timestamp.tmp.sql"

try {
    $dbHost = [Environment]::GetEnvironmentVariable('DB_HOST')
    $dbPort = [Environment]::GetEnvironmentVariable('DB_PORT')
    $dbName = [Environment]::GetEnvironmentVariable('DB_DATABASE')
    $dbUser = [Environment]::GetEnvironmentVariable('DB_USERNAME')
    $dbPassword = [Environment]::GetEnvironmentVariable('DB_PASSWORD')

    if ([string]::IsNullOrWhiteSpace($dbHost)) { $dbHost = '127.0.0.1' }
    if ([string]::IsNullOrWhiteSpace($dbName)) { throw 'DB_DATABASE harus diisi.' }
    if ([string]::IsNullOrWhiteSpace($dbUser)) { throw 'DB_USERNAME harus diisi.' }

    $dumpCommand = $null
    $dumpBinary = $null

    foreach ($candidate in @('mariadb-dump', 'mysqldump')) {
        $found = Get-Command $candidate -ErrorAction SilentlyContinue
        if ($found) {
            $dumpBinary = $candidate
            break
        }
    }

    if (-not $dumpBinary) {
        throw 'Tidak ditemukan mysqldump atau mariadb-dump.'
    }

    $arguments = @(
        '--single-transaction',
        '--routines',
        '--events',
        '--hex-blob',
        '--host=' + $dbHost,
        '--port=' + ($dbPort ?? '3306'),
        '--user=' + $dbUser,
        '--result-file=' + $tempSqlPath,
        $dbName
    )

    $env:MYSQL_PWD = $dbPassword
    & $dumpBinary @arguments
    if ($LASTEXITCODE -ne 0) {
        throw "Dump database gagal dengan exit code $LASTEXITCODE"
    }

    if (-not (Test-Path $tempSqlPath)) {
        throw 'File dump tidak dibuat.'
    }

    $tempInfo = Get-Item $tempSqlPath
    if ($tempInfo.Length -le 0) {
        throw 'File dump kosong.'
    }

    & gzip -c $tempSqlPath | Set-Content -Path $archivePath -Encoding Byte
    if (-not (Test-Path $archivePath)) {
        throw 'Archive backup gagal dibuat.'
    }

    $hash = (Get-FileHash -Algorithm SHA256 -Path $archivePath).Hash.ToLowerInvariant()
    Set-Content -Path $shaPath -Value "$hash  $(Split-Path -Leaf $archivePath)"

    $retentionDays = [Environment]::GetEnvironmentVariable('BACKUP_RETENTION_DAYS')
    if ([string]::IsNullOrWhiteSpace($retentionDays)) { $retentionDays = '30' }

    $cutoff = (Get-Date).AddDays(-[int]$retentionDays)
    Get-ChildItem -Path $backupDir -File | Where-Object { $_.LastWriteTime -lt $cutoff } | Remove-Item -Force

    Write-Host "Backup dibuat: $archivePath"
    Write-Host "SHA256: $hash"
}
finally {
    Remove-Item -Path $tempSqlPath -Force -ErrorAction SilentlyContinue
    Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue
}
