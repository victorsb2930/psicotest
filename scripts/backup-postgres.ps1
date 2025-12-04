param(
    [string]$OutputDir = "backups",
    [string]$FilePrefix = "psicoguia"
)

$ErrorActionPreference = 'Stop'
$repoRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
$envPath = Join-Path $repoRoot '.env'
$dotenv = @{}
if (Test-Path $envPath) {
    foreach ($line in Get-Content $envPath) {
        if (-not $line -or $line.Trim().StartsWith('#')) { continue }
        if ($line -match '^\s*([^=]+)=(.*)$') {
            $key = $Matches[1].Trim()
            $val = $Matches[2].Trim()
            if ($val.StartsWith('"') -and $val.EndsWith('"')) { $val = $val.Trim('"') }
            elseif ($val.StartsWith('\'') -and $val.EndsWith('\'')) { $val = $val.Trim('\'') }
            $dotenv[$key] = $val
        }
    }
}
function Get-DotEnvValue {
    param($Key, $Default)
    if ($dotenv.ContainsKey($Key) -and $dotenv[$Key]) { return $dotenv[$Key] }
    return $Default
}
$dbName = Get-DotEnvValue 'DB_DATABASE' 'laravel_db'
$dbUser = Get-DotEnvValue 'DB_USERNAME' 'laravel_user'
$dbPassword = Get-DotEnvValue 'DB_PASSWORD' 'password'
if (-not (Test-Path $OutputDir)) { New-Item -ItemType Directory -Path $OutputDir | Out-Null }
$timestamp = Get-Date -Format 'yyyyMMdd_HHmmss'
$dumpPath = Join-Path (Resolve-Path $OutputDir) ("$FilePrefix-$timestamp.sql")
$previousLocation = Get-Location
try {
    Set-Location $repoRoot
    $arguments = @('compose','exec','-T','db','env',"PGPASSWORD=$dbPassword",'pg_dump','-U',$dbUser,$dbName)
    $process = Start-Process -FilePath 'docker' -ArgumentList $arguments -RedirectStandardOutput $dumpPath -NoNewWindow -PassThru -Wait
    if ($process.ExitCode -ne 0) {
        throw "pg_dump failed with exit code $($process.ExitCode)."
    }
    Write-Host "Backup generado: $dumpPath" -ForegroundColor Green
}
finally {
    Set-Location $previousLocation
}
