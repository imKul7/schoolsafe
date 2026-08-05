#requires -Version 5.1

[CmdletBinding()]
param(
    [string] $AppPath = "",

    [string] $EnvironmentFile = ".env",

    [string] $OutputDirectory = "storage\app\backups\database",

    [string] $MySqlDumpPath = "",

    [ValidateRange(1, 3650)]
    [int] $RetentionDays = 14,

    [switch] $SkipRetentionCleanup,

    [switch] $ValidateOnly
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

function Resolve-ApplicationPath {
    param(
        [AllowEmptyString()]
        [string] $RequestedPath
    )

    $candidate = $RequestedPath

    if ([string]::IsNullOrWhiteSpace($candidate)) {
        $candidate = Split-Path -Parent $PSScriptRoot
    }

    if (-not (Test-Path -LiteralPath $candidate -PathType Container)) {
        throw "Application path tidak ditemukan: $candidate"
    }

    return (Resolve-Path -LiteralPath $candidate).Path
}

function Resolve-ApplicationFile {
    param(
        [Parameter(Mandatory)]
        [string] $ApplicationRoot,

        [Parameter(Mandatory)]
        [string] $Path
    )

    if ([System.IO.Path]::IsPathRooted($Path)) {
        return [System.IO.Path]::GetFullPath($Path)
    }

    return [System.IO.Path]::GetFullPath(
        (Join-Path $ApplicationRoot $Path)
    )
}

function Read-DotEnvFile {
    param(
        [Parameter(Mandatory)]
        [string] $Path
    )

    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) {
        throw "Environment file tidak ditemukan: $Path"
    }

    $values = @{}

    foreach ($rawLine in [System.IO.File]::ReadAllLines($Path)) {
        $line = $rawLine.Trim()

        if (
            [string]::IsNullOrWhiteSpace($line) -or
            $line.StartsWith("#")
        ) {
            continue
        }

        $match = [regex]::Match(
            $line,
            '^(?<name>[A-Z][A-Z0-9_]*)=(?<value>.*)$'
        )

        if (-not $match.Success) {
            throw "Format environment tidak valid: $rawLine"
        }

        $name = $match.Groups["name"].Value
        $value = $match.Groups["value"].Value.Trim()

        if (
            $value.Length -ge 2 -and
            (
                (
                    $value.StartsWith('"') -and
                    $value.EndsWith('"')
                ) -or
                (
                    $value.StartsWith("'") -and
                    $value.EndsWith("'")
                )
            )
        ) {
            $value = $value.Substring(1, $value.Length - 2)
        }

        $values[$name] = $value
    }

    return $values
}

function Get-EnvironmentValue {
    param(
        [Parameter(Mandatory)]
        [hashtable] $Values,

        [Parameter(Mandatory)]
        [string] $Name,

        [AllowEmptyString()]
        [string] $Default = ""
    )

    if ($Values.ContainsKey($Name)) {
        return [string] $Values[$Name]
    }

    return $Default
}

function Get-RequiredEnvironmentValue {
    param(
        [Parameter(Mandatory)]
        [hashtable] $Values,

        [Parameter(Mandatory)]
        [string] $Name
    )

    $value = Get-EnvironmentValue -Values $Values -Name $Name

    if ([string]::IsNullOrWhiteSpace($value)) {
        throw "Environment variable wajib belum diisi: $Name"
    }

    return $value
}

function Resolve-DatabaseDumpExecutable {
    param(
        [AllowEmptyString()]
        [string] $RequestedPath
    )

    if (-not [string]::IsNullOrWhiteSpace($RequestedPath)) {
        if (Test-Path -LiteralPath $RequestedPath -PathType Leaf) {
            return (Resolve-Path -LiteralPath $RequestedPath).Path
        }

        $requestedCommand = Get-Command $RequestedPath -ErrorAction SilentlyContinue

        if ($requestedCommand) {
            return $requestedCommand.Source
        }

        throw "Database dump executable tidak ditemukan: $RequestedPath"
    }

    $fileCandidates = @(
        "C:\xampp\mysql\bin\mysqldump.exe",
        "C:\xampp\mysql\bin\mariadb-dump.exe"
    )

    foreach ($candidate in $fileCandidates) {
        if (Test-Path -LiteralPath $candidate -PathType Leaf) {
            return (Resolve-Path -LiteralPath $candidate).Path
        }
    }

    foreach ($commandName in @(
        "mysqldump.exe",
        "mysqldump",
        "mariadb-dump.exe",
        "mariadb-dump"
    )) {
        $command = Get-Command $commandName -ErrorAction SilentlyContinue

        if ($command) {
            return $command.Source
        }
    }

    throw "mysqldump atau mariadb-dump tidak ditemukan."
}

$applicationRoot = Resolve-ApplicationPath -RequestedPath $AppPath

$environmentPath = Resolve-ApplicationFile `
    -ApplicationRoot $applicationRoot `
    -Path $EnvironmentFile

$environmentValues = Read-DotEnvFile -Path $environmentPath

$databaseConnection = Get-EnvironmentValue `
    -Values $environmentValues `
    -Name "DB_CONNECTION" `
    -Default "mysql"

if ($databaseConnection -notin @("mysql", "mariadb")) {
    throw "Backup hanya mendukung MySQL/MariaDB. Connection=$databaseConnection"
}

$databaseHost = Get-EnvironmentValue `
    -Values $environmentValues `
    -Name "DB_HOST" `
    -Default "127.0.0.1"

$databasePort = Get-EnvironmentValue `
    -Values $environmentValues `
    -Name "DB_PORT" `
    -Default "3306"

$databaseName = Get-RequiredEnvironmentValue `
    -Values $environmentValues `
    -Name "DB_DATABASE"

$databaseUsername = Get-RequiredEnvironmentValue `
    -Values $environmentValues `
    -Name "DB_USERNAME"

$databasePassword = Get-EnvironmentValue `
    -Values $environmentValues `
    -Name "DB_PASSWORD"

$dumpExecutable = Resolve-DatabaseDumpExecutable `
    -RequestedPath $MySqlDumpPath

Write-Output "Application : $applicationRoot"
Write-Output "Environment : $environmentPath"
Write-Output "Database    : $databaseName"
Write-Output "Host        : ${databaseHost}:${databasePort}"
Write-Output "Dump tool   : $dumpExecutable"

if ($ValidateOnly) {
    Write-Output ""
    Write-Output "PASS: konfigurasi backup database valid."
    Write-Output "VALIDATION ONLY: database tidak dibaca dan backup tidak dibuat."
    return
}

$outputPath = Resolve-ApplicationFile `
    -ApplicationRoot $applicationRoot `
    -Path $OutputDirectory

New-Item `
    -Path $outputPath `
    -ItemType Directory `
    -Force |
    Out-Null

$safeDatabaseName = [regex]::Replace(
    $databaseName,
    '[^A-Za-z0-9_-]',
    '_'
)

$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"

$temporarySqlPath = Join-Path `
    $outputPath `
    "$safeDatabaseName-$timestamp.sql.tmp"

$backupArchivePath = Join-Path `
    $outputPath `
    "$safeDatabaseName-$timestamp.zip"

$dumpArguments = @(
    "--host=$databaseHost",
    "--port=$databasePort",
    "--user=$databaseUsername",
    "--single-transaction",
    "--quick",
    "--routines",
    "--triggers",
    "--events",
    "--hex-blob",
    "--default-character-set=utf8mb4",
    "--result-file=$temporarySqlPath",
    $databaseName
)

$previousMySqlPassword = [Environment]::GetEnvironmentVariable(
    "MYSQL_PWD",
    "Process"
)

try {
    if ([string]::IsNullOrEmpty($databasePassword)) {
        [Environment]::SetEnvironmentVariable(
            "MYSQL_PWD",
            $null,
            "Process"
        )
    } else {
        [Environment]::SetEnvironmentVariable(
            "MYSQL_PWD",
            $databasePassword,
            "Process"
        )
    }

    Write-Output ""
    Write-Output "Membuat database dump..."

    & $dumpExecutable @dumpArguments
    $dumpExitCode = $LASTEXITCODE

    if ($dumpExitCode -ne 0) {
        throw "Database dump gagal dengan exit code $dumpExitCode."
    }

    if (
        -not (Test-Path -LiteralPath $temporarySqlPath -PathType Leaf) -or
        (Get-Item -LiteralPath $temporarySqlPath).Length -le 0
    ) {
        throw "Database dump kosong atau tidak ditemukan."
    }

    Write-Output "Mengompresi database dump..."

    Compress-Archive `
        -LiteralPath $temporarySqlPath `
        -DestinationPath $backupArchivePath `
        -CompressionLevel Optimal

    if (
        -not (Test-Path -LiteralPath $backupArchivePath -PathType Leaf) -or
        (Get-Item -LiteralPath $backupArchivePath).Length -le 0
    ) {
        throw "Backup archive kosong atau tidak ditemukan."
    }
} finally {
    [Environment]::SetEnvironmentVariable(
        "MYSQL_PWD",
        $previousMySqlPassword,
        "Process"
    )

    if (Test-Path -LiteralPath $temporarySqlPath -PathType Leaf) {
        Remove-Item `
            -LiteralPath $temporarySqlPath `
            -Force
    }
}

$backupFile = Get-Item -LiteralPath $backupArchivePath

$backupHash = Get-FileHash `
    -LiteralPath $backupArchivePath `
    -Algorithm SHA256

Write-Output ""
Write-Output "BACKUP BERHASIL"
Write-Output "File   : $($backupFile.FullName)"
Write-Output "Size   : $($backupFile.Length) byte"
Write-Output "SHA256 : $($backupHash.Hash)"

if (-not $SkipRetentionCleanup) {
    $retentionBoundary = (Get-Date).AddDays(-$RetentionDays)

    $expiredBackups = @(
        Get-ChildItem `
            -Path $outputPath `
            -File `
            -Filter "$safeDatabaseName-*.zip" |
            Where-Object {
                $_.LastWriteTime -lt $retentionBoundary -and
                $_.FullName -ne $backupFile.FullName
            }
    )

    foreach ($expiredBackup in $expiredBackups) {
        Remove-Item `
            -LiteralPath $expiredBackup.FullName `
            -Force

        Write-Output "REMOVED EXPIRED: $($expiredBackup.Name)"
    }

    Write-Output "Retention: $RetentionDays hari."
}
