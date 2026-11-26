<#
.SYNOPSIS
  Load test of the SSO's hot endpoints with Apache Bench (ships with XAMPP).

.DESCRIPTION
  Measures discovery, JWKS, the login page and (optionally) the token
  endpoint. Run it against UAT or production before go-live and after any
  server change. The token endpoint limits only failed client authentication
  (20 a minute per client and address), so use the correct secret.

  For the token test, register a temporary application in the admin console,
  give it only the users.read scope, and pass its client ID; you are asked
  for the secret (it is not echoed). Delete the application afterwards.

.EXAMPLE
  .\tests\load\run-load-test.ps1 -BaseUrl https://sso.example.com
  .\tests\load\run-load-test.ps1 -BaseUrl https://sso.example.com -ClientId 9f1c...-... -Requests 500 -Concurrency 25
#>
param(
    [Parameter(Mandatory = $true)][string]$BaseUrl,
    [string]$ClientId,
    [int]$Requests = 500,
    [int]$Concurrency = 20,
    [string]$Ab = 'C:\xampp\apache\bin\ab.exe'
)

$ErrorActionPreference = 'Stop'
$BaseUrl = $BaseUrl.TrimEnd('/')

function Measure-Endpoint([string]$Label, [string[]]$AbArgs) {
    $out = & $Ab -q -n $Requests -c $Concurrency @AbArgs 2>&1 | Out-String
    $rps = [regex]::Match($out, 'Requests per second:\s+([\d.]+)').Groups[1].Value
    $mean = [regex]::Match($out, 'Time per request:\s+([\d.]+) \[ms\] \(mean\)').Groups[1].Value
    $p95 = [regex]::Match($out, '\s95%\s+(\d+)').Groups[1].Value
    $p99 = [regex]::Match($out, '\s99%\s+(\d+)').Groups[1].Value
    $non2xx = [regex]::Match($out, 'Non-2xx responses:\s+(\d+)').Groups[1].Value
    [pscustomobject]@{
        Endpoint = $Label; 'Req/s' = $rps; 'Mean ms' = $mean; 'p95 ms' = $p95; 'p99 ms' = $p99
        Errors = $(if ($non2xx) { $non2xx } else { 0 })
    }
}

$results = @(
    Measure-Endpoint 'GET discovery' @("$BaseUrl/.well-known/openid-configuration")
    Measure-Endpoint 'GET jwks' @("$BaseUrl/oauth/jwks")
    Measure-Endpoint 'GET login page' @("$BaseUrl/login")
)

if ($ClientId) {
    $secure = Read-Host -AsSecureString "Client secret for $ClientId"
    $secret = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure))
    $body = New-TemporaryFile
    try {
        "grant_type=client_credentials&client_id=$ClientId&client_secret=$([uri]::EscapeDataString($secret))&scope=users.read" |
            Set-Content -NoNewline -Encoding ascii $body
        $results += Measure-Endpoint 'POST token (client credentials)' @('-p', $body.FullName, '-T', 'application/x-www-form-urlencoded', "$BaseUrl/oauth/token")
    } finally {
        Remove-Item $body -Force
    }
}

$results | Format-Table -AutoSize
Write-Host 'Targets (see docs/deployment.md): p95 under 500 ms and no errors at 20 concurrent users.'
