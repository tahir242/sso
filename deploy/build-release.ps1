<#
.SYNOPSIS
    Builds the release zip: a complete copy of SSO for a server without
    internet, Composer or Node.js.

.DESCRIPTION
    Copies only what the server needs into a release folder, installs the
    production PHP dependencies there and builds the front-end. The source
    project is not changed (apart from public/build, the compiled CSS/JS).

    Left out of the release:
      - .env and every other environment file (the server keeps its own .env)
      - storage contents: logs, uploads, backups, signing keys
      - tests, node_modules, .github and developer config (phpunit, vite, npm, editor)
      - bootstrap/cache (rebuilt on the server by `php artisan optimize`)

    Requires PHP 8.4, Composer, Node.js 20+ and npm on the build machine.
    GitHub Actions runs this script for every tagged release
    (.github/workflows/release.yml).

.EXAMPLE
    .\deploy\build-release.ps1 -Version v1.2.0
    Builds ..\sso-release and ..\sso-v1.2.0.zip, the file to copy to the server.
#>
param(
    [string]$Version,
    [string]$Output,
    [switch]$NoZip,
    [switch]$SkipAssets
)

$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
$parent = Split-Path $root -Parent
if (-not $Version) {
    $Version = (git -C $root describe --tags --always 2>$null)
    if (-not $Version) { $Version = 'dev-' + (Get-Date -Format 'yyyyMMdd-HHmm') }
}
if (-not $Output) { $Output = Join-Path $parent 'sso-release' }
$Output = [System.IO.Path]::GetFullPath($Output)

function Step($text) { Write-Host "`n==> $text" -ForegroundColor Cyan }
function Run($exe, [string[]]$arguments) {
    & $exe @arguments
    if ($LASTEXITCODE -ne 0) { throw "$exe $($arguments -join ' ') failed (exit $LASTEXITCODE)" }
}

# Only ever delete a folder this script created.
if (Test-Path $Output) {
    if (-not (Test-Path (Join-Path $Output 'RELEASE.txt'))) {
        throw "$Output exists and is not a release built by this script. Choose another -Output."
    }
    Step "Removing the previous release in $Output"
    Remove-Item $Output -Recurse -Force
}

if (-not $SkipAssets) {
    Step 'Building the front-end (public/build)'
    Push-Location $root
    try {
        # The Filament theme imports CSS from vendor/.
        if (-not (Test-Path 'vendor')) { Run 'composer' @('install', '--no-interaction', '--no-progress') }
        if (-not (Test-Path 'node_modules')) { Run 'npm' @('ci', '--no-audit', '--no-fund') }
        Run 'npm' @('run', 'build')
    } finally { Pop-Location }
}
if (-not (Test-Path (Join-Path $root 'public\build\manifest.json'))) {
    throw 'public/build is missing. Run without -SkipAssets.'
}

Step "Copying the application to $Output"
# Full paths: a bare name such as "vendor" would also skip resources\views\vendor.
$excludeDirs = @(
    '.git', '.github', '.idea', '.vscode', '.claude', 'node_modules', 'vendor', 'tests', 'storage', 'deploy',
    'public\hot', 'public\storage', 'packages\sso-client\tests', 'packages\sso-client\vendor'
) | ForEach-Object { Join-Path $root $_ }
$excludeFiles = @(
    '.env', '.env.backup', '.env.production', '.env.testing*', '.phpunit.result.cache',
    'phpunit*.xml', 'vite.config.js', 'package.json', 'package-lock.json', '.npmrc',
    '.editorconfig', '.gitattributes', '.gitignore',
    '*.log', 'Thumbs.db', '.DS_Store', '_ide_helper.php'
)
robocopy $root $Output /E /NFL /NDL /NJH /NJS /NP /XD @excludeDirs /XF @excludeFiles | Out-Null
if ($LASTEXITCODE -ge 8) { throw "robocopy failed (exit $LASTEXITCODE)" }
$global:LASTEXITCODE = 0

# The server gets the cmd scripts (it has no PowerShell), not this build script.
New-Item -ItemType Directory -Force -Path (Join-Path $Output 'deploy') | Out-Null
Copy-Item (Join-Path $root 'deploy\*.cmd') (Join-Path $Output 'deploy\')

# Cached config/routes/packages from the build machine must not ship.
Get-ChildItem (Join-Path $Output 'bootstrap\cache') -Filter '*.php' | Remove-Item -Force

Step 'Creating an empty storage folder'
foreach ($dir in @(
    'storage\app\private', 'storage\app\public', 'storage\app\media',
    'storage\framework\cache\data', 'storage\framework\sessions', 'storage\framework\views',
    'storage\logs'
)) { New-Item -ItemType Directory -Force -Path (Join-Path $Output $dir) | Out-Null }

Step 'Installing production PHP dependencies'
Push-Location $Output
try {
    Run 'composer' @('install', '--no-dev', '--optimize-autoloader', '--no-interaction', '--no-progress')
} finally { Pop-Location }

$stamp = Get-Date -Format 'yyyy-MM-dd HH:mm'
$commit = (git -C $root rev-parse --short HEAD 2>$null)
@"
SSO $Version
Built:  $stamp (commit $commit)

First install: deploy\install.cmd. New version: deploy\update.cmd <zip>.
See docs\deployment.md (sections 3 and 4).
Not included: .env, storage contents (keys, uploads, logs), tests.
"@ | Set-Content -Encoding UTF8 (Join-Path $Output 'RELEASE.txt')

if (-not $NoZip) {
    $zipPath = Join-Path (Split-Path $Output -Parent) ("sso-{0}.zip" -f $Version)
    if (Test-Path $zipPath) { Remove-Item $zipPath -Force }
    Step "Zipping to $zipPath"
    # tar (built into Windows) so the server can extract it from cmd: tar -xf <zip> -C <folder>
    tar.exe -a -c -f $zipPath -C $Output .
    if ($LASTEXITCODE -ne 0) { throw "tar failed (exit $LASTEXITCODE)" }
}

Step 'Done'
Write-Host "Release: $Output"
Write-Host 'Next: copy the zip to the server and run deploy\install.cmd (first time) or deploy\update.cmd <zip>; see docs/deployment.md (sections 3 and 4).'
