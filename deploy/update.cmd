@echo off
setlocal EnableExtensions
rem ---------------------------------------------------------------------------
rem  SSO - install a NEW VERSION over the running one (cmd only).
rem
rem    C:\xampp\htdocs\sso\deploy\update.cmd D:\Releases\sso-vX.Y.Z.zip
rem
rem  Keeps .env and storage\ (keys, uploads, logs). Removes the old code first,
rem  so files deleted in the new version do not linger. The site shows a
rem  maintenance page for the minute or two this takes.
rem
rem  PHP: C:\xampp\php84\php.exe, or set PHP=<path to php.exe> before running.
rem ---------------------------------------------------------------------------

rem This script is replaced by the new version while it runs, and cmd reads
rem scripts as it goes: continue from a copy in TEMP. (The block below is read
rem in one go, so returning into the replaced file is safe.)
if not defined SSO_UPDATE_COPY (
    set "SSO_UPDATE_COPY=1"
    for %%I in ("%~dp0..") do set "SSO_ROOT=%%~fI"
    copy /y "%~f0" "%TEMP%\sso-update.cmd" >nul
    call "%TEMP%\sso-update.cmd" %*
    exit /b
)

set "ROOT=%SSO_ROOT%"
set "ZIP=%~f1"
if not defined PHP if exist "C:\xampp\php84\php.exe" set "PHP=C:\xampp\php84\php.exe"
if not defined PHP set "PHP=php"

if "%~1"=="" (echo Usage: deploy\update.cmd ^<release zip^> & exit /b 1)
if not exist "%ZIP%" (echo Release not found: %ZIP% & exit /b 1)
if not exist "%ROOT%\.env" (echo No .env in %ROOT%: this is not an installed SSO. Use deploy\install.cmd. & exit /b 1)
if not exist "%ROOT%\storage\oauth-private.key" (echo storage\oauth-private.key is missing in %ROOT%. Stopping. & exit /b 1)

cd /d "%ROOT%" || exit /b 1
echo Updating SSO in %ROOT%
echo from %ZIP%
echo Using PHP: %PHP%
choice /m "Continue"
if errorlevel 2 exit /b 1

echo.
echo == Maintenance page on
if exist "vendor\autoload.php" "%PHP%" artisan down --retry=60

echo.
echo == Removing the old code (.env and storage are kept)
for %%D in (app bootstrap\cache config database deploy docs resources routes vendor public\build public\css public\js public\fonts) do (
    if exist "%%D" rmdir /s /q "%%D"
)

echo.
echo == Extracting the new version
tar -xf "%ZIP%" -C "%ROOT%"
if errorlevel 1 (
    echo Extraction failed. The site stays in maintenance mode: extract the zip
    echo again by hand with  tar -xf "%ZIP%" -C "%ROOT%"  and rerun this script.
    exit /b 1
)

echo.
echo == Database changes and caches
"%PHP%" artisan optimize:clear >nul
"%PHP%" artisan migrate --force || (echo Migration failed: the site stays in maintenance mode. See storage\logs. & exit /b 1)
"%PHP%" artisan optimize || exit /b 1
"%PHP%" artisan filament:optimize || exit /b 1

echo.
echo == Maintenance page off
"%PHP%" artisan up

echo.
"%PHP%" artisan sso:check
echo.
echo Done. Restart Apache so PHP picks up the new code (OPcache).
exit /b 0
