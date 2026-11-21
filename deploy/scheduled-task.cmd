@echo off
setlocal EnableExtensions
rem ---------------------------------------------------------------------------
rem  SSO - creates the ONE Windows scheduled task the SSO needs.
rem  Run once, in a cmd window opened "as administrator":
rem
rem    C:\xampp\htdocs\sso\deploy\scheduled-task.cmd [account]
rem
rem  Every minute it runs `php artisan schedule:run`, which sends emails,
rem  WhatsApp and push messages, passes sign-outs to applications, escalates
rem  approvals, runs access reviews and helpdesk SLAs, and makes the nightly
rem  backup. No other service or worker is needed.
rem
rem  [account] is the Windows account the task runs as; use the same account
rem  as Apache so both can write to storage\. Default: SYSTEM.
rem  PHP: C:\xampp\php84\php.exe, or set PHP=<path to php.exe> before running.
rem ---------------------------------------------------------------------------

for %%I in ("%~dp0..") do set "ROOT=%%~fI"
if not defined PHP if exist "C:\xampp\php84\php.exe" set "PHP=C:\xampp\php84\php.exe"
if not defined PHP (echo Set PHP=^<full path to php.exe^> first. & exit /b 1)
set "ACCOUNT=%~1"
if "%ACCOUNT%"=="" set "ACCOUNT=SYSTEM"
set "TASK=SSO scheduler"

net session >nul 2>&1 || (echo Run this from a cmd window opened as administrator. & exit /b 1)

echo Task:    %TASK%
echo Runs:    "%PHP%" "%ROOT%\artisan" schedule:run   (every minute)
echo Account: %ACCOUNT%

if /i "%ACCOUNT%"=="SYSTEM" (
    schtasks /Create /F /TN "%TASK%" /SC MINUTE /MO 1 /RU SYSTEM /RL HIGHEST /TR "\"%PHP%\" \"%ROOT%\artisan\" schedule:run"
) else (
    schtasks /Create /F /TN "%TASK%" /SC MINUTE /MO 1 /RU "%ACCOUNT%" /RP * /RL HIGHEST /TR "\"%PHP%\" \"%ROOT%\artisan\" schedule:run"
)
if errorlevel 1 exit /b 1

echo.
echo Created. Within two minutes `php artisan sso:check` should show
echo "Scheduler ran in the last 5 minutes" and, within 15, the queue line.
echo To remove it:  schtasks /Delete /TN "%TASK%" /F
exit /b 0
