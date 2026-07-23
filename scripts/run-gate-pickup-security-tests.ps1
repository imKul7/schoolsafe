[CmdletBinding()]
param(
    [switch] $ValidateOnly,
    [switch] $SkipOptimizeClear,
    [switch] $SkipMigration
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$scriptRoot =
    Split-Path `
        -Parent `
        $MyInvocation.MyCommand.Path

$projectRoot =
    Split-Path `
        -Parent `
        $scriptRoot

Set-Location $projectRoot

$logDirectory =
    Join-Path `
        $projectRoot `
        'storage\logs\gate-pickup-tests'

New-Item `
    -ItemType Directory `
    -Force `
    -Path $logDirectory |
    Out-Null

$timestamp =
    Get-Date `
        -Format 'yyyyMMdd-HHmmss'

$logPath =
    Join-Path `
        $logDirectory `
        "gate-pickup-security-$timestamp.log"

function Write-Log {
    param(
        [AllowEmptyString()]
        [string] $Message = ''
    )

    $Message |
        Tee-Object `
            -FilePath $logPath `
            -Append
}

function Stop-WithFailure {
    param(
        [Parameter(Mandatory)]
        [string] $Message
    )

    Write-Log ''
    Write-Log "GAGAL: $Message"
    Write-Log "Log: $logPath"

    throw $Message
}

function Invoke-LoggedCommand {
    param(
        [Parameter(Mandatory)]
        [string] $Label,

        [Parameter(Mandatory)]
        [string] $FilePath,

        [Parameter(Mandatory)]
        [string[]] $Arguments
    )

    Write-Log ''
    Write-Log "================================================================"
    Write-Log $Label
    Write-Log "================================================================"
    Write-Log (
        '{0} {1}' -f
            $FilePath,
            ($Arguments -join ' ')
    )

    & $FilePath @Arguments 2>&1 |
        ForEach-Object {
            Write-Log ([string] $_)
        }

    $exitCode =
        $LASTEXITCODE

    if ($exitCode -ne 0) {
        Stop-WithFailure (
            'Perintah gagal dengan exit code {0}: {1}' -f
                $exitCode,
                $Label
        )
    }
}

function Get-EnvironmentValue {
    param(
        [Parameter(Mandatory)]
        [AllowEmptyCollection()]
        [AllowEmptyString()]
        [string[]] $Lines,

        [Parameter(Mandatory)]
        [ValidateNotNullOrEmpty()]
        [string] $Name
    )

    $escapedName =
        [Regex]::Escape(
            $Name
        )

    $matchingLine =
        $Lines |
            Where-Object {
                $_ -match "^\s*$escapedName\s*="
            } |
            Select-Object `
                -Last 1

    if ($null -eq $matchingLine) {
        return $null
    }

    $value =
        (
            $matchingLine -replace
                "^\s*$escapedName\s*=\s*",
            ''
        ).Trim()

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
        $value =
            $value.Substring(
                1,
                $value.Length - 2
            )
    }

    return $value
}

Write-Log 'SchoolSafe Gate Pickup Security Regression'
Write-Log "Waktu mulai: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
Write-Log "Project root: $projectRoot"
Write-Log "Log: $logPath"

$phpCommand =
    Get-Command `
        php `
        -ErrorAction SilentlyContinue

if ($null -eq $phpCommand) {
    Stop-WithFailure 'Executable PHP tidak ditemukan pada PATH.'
}

$environmentFile =
    Join-Path `
        $projectRoot `
        '.env.testing'

if (-not (Test-Path -LiteralPath $environmentFile -PathType Leaf)) {
    Stop-WithFailure '.env.testing tidak ditemukan.'
}

$environmentLines =
    Get-Content `
        -LiteralPath $environmentFile

$appEnvironment =
    Get-EnvironmentValue `
        -Lines $environmentLines `
        -Name 'APP_ENV'

$databaseName =
    Get-EnvironmentValue `
        -Lines $environmentLines `
        -Name 'DB_DATABASE'

$appKey =
    Get-EnvironmentValue `
        -Lines $environmentLines `
        -Name 'APP_KEY'

if ($appEnvironment -ne 'testing') {
    Stop-WithFailure (
        'APP_ENV pada .env.testing wajib bernilai [testing]. Nilai aktual: [{0}]' -f
            $appEnvironment
    )
}

if (
    [string]::IsNullOrWhiteSpace(
        $databaseName
    ) -or
    -not $databaseName.EndsWith(
        '_test',
        [System.StringComparison]::OrdinalIgnoreCase
    )
) {
    Stop-WithFailure (
        'DB_DATABASE pada .env.testing wajib berakhiran [_test]. Nilai aktual: [{0}]' -f
            $databaseName
    )
}

if ([string]::IsNullOrWhiteSpace($appKey)) {
    Stop-WithFailure 'APP_KEY pada .env.testing tidak boleh kosong.'
}

Write-Log ''
Write-Log 'Environment testing tervalidasi.'
Write-Log "APP_ENV: $appEnvironment"
Write-Log "DB_DATABASE: $databaseName"
Write-Log 'APP_KEY: tersedia'

$requiredFiles = @(
    'artisan',
    'routes\web.php',
    'app\Http\Controllers\GatePickupEventController.php',
    'app\Http\Middleware\PreventSensitiveResponseCaching.php',
    'tests\Feature\GatePickupEventSecurityTest.php',
    'tests\Feature\GatePickupEventDatabaseIntegrityTest.php',
    'tests\Feature\GateRouteIntegrityTest.php',
    'tests\Feature\GatePickupEventParallelConfirmationTest.php',
    'tests\Feature\GatePickupEventParallelCancellationTest.php',
    'tests\Support\GatePickupEventParallelWorker.php',
    'tests\Support\GatePickupEventParallelCancellationWorker.php',
    'database\migrations\2026_07_22_163228_add_school_confirmed_at_index_to_pickup_events_table.php'
)

foreach ($relativePath in $requiredFiles) {
    $absolutePath =
        Join-Path `
            $projectRoot `
            $relativePath

    if (-not (Test-Path -LiteralPath $absolutePath -PathType Leaf)) {
        Stop-WithFailure "File wajib tidak ditemukan: $relativePath"
    }
}

Write-Log ''
Write-Log 'Seluruh file wajib ditemukan.'

$phpLintFiles = @(
    'routes\web.php',
    'app\Http\Controllers\GatePickupEventController.php',
    'app\Http\Middleware\PreventSensitiveResponseCaching.php',
    'tests\Feature\GatePickupEventSecurityTest.php',
    'tests\Feature\GatePickupEventDatabaseIntegrityTest.php',
    'tests\Feature\GateRouteIntegrityTest.php',
    'tests\Feature\GatePickupEventParallelConfirmationTest.php',
    'tests\Feature\GatePickupEventParallelCancellationTest.php',
    'tests\Support\GatePickupEventParallelWorker.php',
    'tests\Support\GatePickupEventParallelCancellationWorker.php',
    'database\migrations\2026_07_22_163228_add_school_confirmed_at_index_to_pickup_events_table.php'
)

foreach ($relativePath in $phpLintFiles) {
    Invoke-LoggedCommand `
        -Label "PHP lint: $relativePath" `
        -FilePath $phpCommand.Source `
        -Arguments @(
            '-l',
            $relativePath
        )
}

if ($ValidateOnly) {
    Write-Log ''
    Write-Log '================================================================'
    Write-Log 'VALIDASI SCRIPT, ENVIRONMENT, FILE, DAN PHP LINT LULUS'
    Write-Log '================================================================'
    Write-Log "Waktu selesai: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
    Write-Log "Log: $logPath"

    exit 0
}

if (-not $SkipOptimizeClear) {
    Invoke-LoggedCommand `
        -Label 'Laravel optimize:clear' `
        -FilePath $phpCommand.Source `
        -Arguments @(
            'artisan',
            'optimize:clear',
            '--env=testing'
        )
}

if (-not $SkipMigration) {
    Invoke-LoggedCommand `
        -Label 'Laravel migration status testing' `
        -FilePath $phpCommand.Source `
        -Arguments @(
            'artisan',
            'migrate:status',
            '--env=testing'
        )

    Invoke-LoggedCommand `
        -Label 'Laravel migrate testing' `
        -FilePath $phpCommand.Source `
        -Arguments @(
            'artisan',
            'migrate',
            '--env=testing',
            '--force'
        )
}

$testGroups = @(
    @{
        Label = 'Gate route integrity'
        File = 'tests\Feature\GateRouteIntegrityTest.php'
    },
    @{
        Label = 'Gate pickup database integrity'
        File = 'tests\Feature\GatePickupEventDatabaseIntegrityTest.php'
    },
    @{
        Label = 'Gate pickup security'
        File = 'tests\Feature\GatePickupEventSecurityTest.php'
    },
    @{
        Label = 'Gate pickup parallel confirmation'
        File = 'tests\Feature\GatePickupEventParallelConfirmationTest.php'
    },
    @{
        Label = 'Gate pickup parallel cancellation'
        File = 'tests\Feature\GatePickupEventParallelCancellationTest.php'
    }
)

foreach ($testGroup in $testGroups) {
    Invoke-LoggedCommand `
        -Label $testGroup.Label `
        -FilePath $phpCommand.Source `
        -Arguments @(
            'artisan',
            'test',
            $testGroup.File,
            '--stop-on-failure'
        )
}

Write-Log ''
Write-Log '================================================================'
Write-Log 'SEMUA REGRESI KEAMANAN TRANSAKSI GERBANG LULUS'
Write-Log '================================================================'
Write-Log "Waktu selesai: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
Write-Log "Log: $logPath"
