@echo off
setlocal
cd /d "%~dp0"
set "FINDIT_PHP="
for /f "delims=" %%P in ('where php 2^>nul') do if not defined FINDIT_PHP set "FINDIT_PHP=%%P"
if not defined FINDIT_PHP if exist "F:\wamp64\bin\php" for /d %%P in (F:\wamp64\bin\php\php8*) do set "FINDIT_PHP=%%P\php.exe"
if not defined FINDIT_PHP if exist "C:\wamp64\bin\php" for /d %%P in (C:\wamp64\bin\php\php8*) do set "FINDIT_PHP=%%P\php.exe"
if not defined FINDIT_PHP if exist "C:\xampp\php\php.exe" set "FINDIT_PHP=C:\xampp\php\php.exe"
if not defined FINDIT_PHP (
    echo PHP was not found. Install PHP 8.1+ or add php.exe to PATH.
    pause
    exit /b 1
)
set "FINDIT_EXT="
"%FINDIT_PHP%" -r "exit(extension_loaded('sodium') ? 0 : 1);"
if errorlevel 1 set "FINDIT_EXT=-d extension=sodium"
"%FINDIT_PHP%" %FINDIT_EXT% scripts\setup.php
if errorlevel 1 (
    pause
    exit /b 1
)
echo Open http://localhost:8000 and keep this window running.
"%FINDIT_PHP%" %FINDIT_EXT% -S localhost:8000 -t public
pause
