@echo off
setlocal EnableExtensions
rem ---------------------------------------------------------------------------
rem  SSO - FIRST installation on the server (cmd only, no internet).
rem
rem  1. Extract the release zip into the web folder, e.g.
rem       mkdir C:\xampp\htdocs\sso
rem       tar -xf sso-vX.Y.Z.zip -C C:\xampp\htdocs\sso
rem  2. Run this script once from anywhere:
rem       C:\xampp\htdocs\sso\deploy\install.cmd
rem     The first run creates .env from .env.example and stops: fill it in
rem     with Notepad, then run the script again.
rem
rem  PHP: C:\xampp\php84\php.exe, or set PHP=<path to php.exe> before running.
rem  For later versions use deploy\update.cmd, never this script.
rem ---------------------------------------------------------------------------

for %%I in ("%~dp0..") do set "ROOT=%%~fI"
if not defined PHP if exist "C:\xampp\php84\php.exe" set "PHP=C:\xampp\php84\php.exe"
if not defined PHP set "PHP=php"
cd /d "%ROOT%" || exit /b 1

echo SSO - first installation in %ROOT%
echo Using PHP: %PHP%
"%PHP%" -r "exit(PHP_VERSION_ID >= 80400 ? 0 : 1);" || (echo PHP 8.4 or later is required. & exit /b 1)
"%PHP%" -r "exit(extension_loaded('pdo_sqlsrv') && extension_loaded('ldap') && extension_loaded('sodium') ? 0 : 1);" || (echo php.ini must enable pdo_sqlsrv, ldap and sodium: see docs\deployment.md section 1. & exit /b 1)

if not exist ".env" (
    copy ".env.example" ".env" >nul
    echo.
    echo Created .env from the template. Open it with Notepad, fill in APP_URL,
    echo DB_* and SSO_BACKUP_*, then run this script again.
    exit /b 1
)

rem The application key encrypts stored secrets: generate it once, never again.
findstr /r /c:"^APP_KEY=base64:" ".env" >nul
if errorlevel 1 (
    echo.
    echo == Generating the application key
    "%PHP%" artisan key:generate --force || exit /b 1
)

rem Token signing keys: only when missing. New keys sign everyone out of every application.
if not exist "storage\oauth-private.key" (
    echo.
    echo == Generating the token signing keys
    "%PHP%" artisan passport:keys || exit /b 1
)

echo.
echo == Creating the database tables
"%PHP%" artisan migrate --force || exit /b 1

echo.
echo == Loading roles, occasions, quotes and helpdesk categories
"%PHP%" artisan db:seed --force || exit /b 1

echo.
echo == Caching configuration, routes and views
"%PHP%" artisan optimize || exit /b 1
"%PHP%" artisan filament:optimize || exit /b 1

echo.
echo == Readiness check
"%PHP%" artisan sso:check

echo.
echo Next:
echo   1. Ask IT to run deploy\scheduled-task.cmd once (as administrator).
echo   2. Restart Apache.
echo   3. Create the first administrator: "%PHP%" artisan sso:create-admin
echo   4. Back up storage\oauth-private.key, storage\oauth-public.key and .env to the IT safe.
exit /b 0
