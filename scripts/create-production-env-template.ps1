[CmdletBinding()]
param()

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSScriptRoot

if ([string]::IsNullOrWhiteSpace($projectRoot)) {
    $projectRoot = (Get-Location).Path
}

$sourcePath = Join-Path $projectRoot '.env.example'
$targetPath = Join-Path $projectRoot '.env.production.example'

if (-not (Test-Path -LiteralPath $sourcePath -PathType Leaf)) {
    throw "File sumber tidak ditemukan: $sourcePath"
}

$productionValues = [ordered]@{
    APP_NAME                 = 'SchoolSafe'
    APP_ENV                  = 'production'
    APP_KEY                  = ''
    APP_DEBUG                = 'false'
    APP_URL                  = 'https://schoolsafe.example.com'
    ASSET_URL                = ''
    APP_MAINTENANCE_DRIVER   = 'file'

    LOG_CHANNEL              = 'stack'
    LOG_STACK                = 'daily'
    LOG_DEPRECATIONS_CHANNEL = 'null'
    LOG_LEVEL                = 'warning'

    DB_CONNECTION            = 'mysql'
    DB_HOST                  = '127.0.0.1'
    DB_PORT                  = '3306'
    DB_DATABASE              = 'schoolsafe'
    DB_USERNAME              = 'schoolsafe'
    DB_PASSWORD              = ''

    SESSION_DRIVER           = 'database'
    SESSION_LIFETIME         = '120'
    SESSION_EXPIRE_ON_CLOSE  = 'false'
    SESSION_ENCRYPT          = 'true'
    SESSION_PATH             = '/'
    SESSION_DOMAIN           = 'null'
    SESSION_SECURE_COOKIE    = 'true'
    SESSION_HTTP_ONLY        = 'true'
    SESSION_SAME_SITE        = 'lax'
    SESSION_PARTITIONED      = 'false'
    SESSION_TABLE            = 'sessions'

    BROADCAST_CONNECTION     = 'log'
    FILESYSTEM_DISK          = 'public'
    QUEUE_CONNECTION         = 'database'
    CACHE_STORE              = 'database'

    MAIL_MAILER              = 'smtp'
    MAIL_HOST                = 'smtp.example.com'
    MAIL_PORT                = '587'
    MAIL_USERNAME            = ''
    MAIL_PASSWORD            = ''
    MAIL_ENCRYPTION          = 'tls'
    MAIL_FROM_ADDRESS        = 'noreply@schoolsafe.example.com'
    MAIL_FROM_NAME           = '"${APP_NAME}"'

    BCRYPT_ROUNDS            = '12'
}

$lines = @(Get-Content -LiteralPath $sourcePath)

foreach ($entry in $productionValues.GetEnumerator()) {
    $name = [string] $entry.Key
    $value = [string] $entry.Value
    $pattern = '^\s*#?\s*' + [Regex]::Escape($name) + '\s*=.*$'
    $replacement = '{0}={1}' -f $name, $value
    $found = $false
    $updatedLines = @()

    foreach ($line in $lines) {
        $currentLine = [string] $line

        if ($currentLine -match $pattern) {
            if (-not $found) {
                $updatedLines += $replacement
                $found = $true
            }

            continue
        }

        $updatedLines += $currentLine
    }

    if (-not $found) {
        if ($updatedLines.Count -gt 0) {
            $lastLine = [string] $updatedLines[$updatedLines.Count - 1]

            if ($lastLine -ne '') {
                $updatedLines += ''
            }
        }

        $updatedLines += $replacement
    }

    $lines = @($updatedLines)
}

$keyCounts = @{}

foreach ($line in $lines) {
    $trimmedLine = ([string] $line).Trim()

    if ($trimmedLine -eq '') {
        continue
    }

    if ($trimmedLine.StartsWith('#')) {
        continue
    }

    if (-not $trimmedLine.Contains('=')) {
        continue
    }

    $key = $trimmedLine.Split('=', 2)[0].Trim()

    if ([string]::IsNullOrWhiteSpace($key)) {
        continue
    }

    if (-not $keyCounts.ContainsKey($key)) {
        $keyCounts[$key] = 0
    }

    $keyCounts[$key] = ([int] $keyCounts[$key]) + 1
}

$duplicateKeys = @(
    $keyCounts.GetEnumerator() |
        Where-Object { $_.Value -gt 1 } |
        ForEach-Object { [string] $_.Key } |
        Sort-Object
)

if ($duplicateKeys.Count -gt 0) {
    throw ('Ditemukan key environment aktif yang duplikat: {0}' -f ($duplicateKeys -join ', '))
}

$requiredProductionKeys = @(
    'APP_NAME',
    'APP_ENV',
    'APP_KEY',
    'APP_DEBUG',
    'APP_URL',
    'LOG_CHANNEL',
    'LOG_STACK',
    'LOG_LEVEL',
    'DB_CONNECTION',
    'DB_HOST',
    'DB_PORT',
    'DB_DATABASE',
    'DB_USERNAME',
    'DB_PASSWORD',
    'SESSION_DRIVER',
    'SESSION_ENCRYPT',
    'SESSION_SECURE_COOKIE',
    'SESSION_HTTP_ONLY',
    'SESSION_SAME_SITE',
    'CACHE_STORE',
    'QUEUE_CONNECTION',
    'FILESYSTEM_DISK',
    'MAIL_MAILER',
    'MAIL_HOST',
    'MAIL_PORT',
    'MAIL_USERNAME',
    'MAIL_PASSWORD',
    'MAIL_ENCRYPTION',
    'MAIL_FROM_ADDRESS',
    'MAIL_FROM_NAME'
)

$missingRequiredKeys = @(
    $requiredProductionKeys |
        Where-Object { -not $keyCounts.ContainsKey($_) }
)

if ($missingRequiredKeys.Count -gt 0) {
    throw ('Key production wajib belum tersedia: {0}' -f ($missingRequiredKeys -join ', '))
}

$utf8WithoutBom = New-Object System.Text.UTF8Encoding($false)

[System.IO.File]::WriteAllLines(
    $targetPath,
    [string[]] $lines,
    $utf8WithoutBom
)

Write-Host ''
Write-Host '============================================================'
Write-Host 'TEMPLATE PRODUCTION BERHASIL DIBUAT'
Write-Host '============================================================'
Write-Host "Sumber : $sourcePath"
Write-Host "Target : $targetPath"
Write-Host ''

$safeSummaryKeys = @(
    'APP_ENV',
    'APP_DEBUG',
    'APP_URL',
    'LOG_CHANNEL',
    'LOG_STACK',
    'LOG_LEVEL',
    'SESSION_DRIVER',
    'SESSION_ENCRYPT',
    'SESSION_SECURE_COOKIE',
    'SESSION_HTTP_ONLY',
    'SESSION_SAME_SITE',
    'CACHE_STORE',
    'QUEUE_CONNECTION',
    'FILESYSTEM_DISK',
    'MAIL_MAILER'
)

foreach ($safeKey in $safeSummaryKeys) {
    $safePattern = '^' + [Regex]::Escape($safeKey) + '=(.*)$'
    $matchingLine = $lines | Where-Object { $_ -match $safePattern } | Select-Object -First 1

    if ($null -ne $matchingLine) {
        Write-Host $matchingLine
    }
}

Write-Host ''
Write-Host 'Credential tetap kosong dan harus diisi hanya pada server production:'
Write-Host '- APP_KEY'
Write-Host '- DB_PASSWORD'
Write-Host '- MAIL_USERNAME'
Write-Host '- MAIL_PASSWORD'
Write-Host '- token atau secret layanan eksternal'
Write-Host ''
