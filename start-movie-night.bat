@echo off
setlocal EnableExtensions
cd /d "%~dp0"

set "PORT=8090"
set "PHP_EXE="

rem 1) PHP a PATH-ban
where php.exe >nul 2>nul
if not errorlevel 1 set "PHP_EXE=php.exe"

rem 2) Gyakori Windowsos PHP helyek
if not defined PHP_EXE if exist "C:\xampp\php\php.exe" set "PHP_EXE=C:\xampp\php\php.exe"
if not defined PHP_EXE if exist "C:\php\php.exe" set "PHP_EXE=C:\php\php.exe"
if not defined PHP_EXE if exist "%~dp0php\php.exe" set "PHP_EXE=%~dp0php\php.exe"

if not defined PHP_EXE (
    echo.
    echo [HIBA] Nem talaltam a php.exe fajlt.
    echo.
    echo Telepits PHP 8.1 vagy ujabb verziot, vagy szerkeszd ezt a BAT fajlt,
    echo es add meg a PHP_EXE valtozoban a php.exe teljes eleresi utjat.
    echo Pelda: set "PHP_EXE=C:\xampp\php\php.exe"
    echo.
    pause
    exit /b 1
)

"%PHP_EXE%" -r "foreach(['curl','pdo_sqlite','sqlite3','mbstring'] as $e){if(!extension_loaded($e)){fwrite(STDERR,'Hianyzo PHP modul: '.$e.PHP_EOL);exit(2);}}"
if errorlevel 1 (
    echo.
    echo [HIBA] Egy vagy tobb szukseges PHP modul nincs bekapcsolva.
    echo Szükséges: curl, pdo_sqlite, sqlite3, mbstring
    echo Ellenorizd a php.ini fajlt.
    echo.
    pause
    exit /b 2
)

if not exist "data" mkdir "data"

echo.
echo Movie Night inditasa: http://localhost:%PORT%
echo Helyi halozatrol: http://EZ-A-GEP-IP-CIME:%PORT%
echo Leallitashoz nyomj Ctrl+C-t ebben az ablakban.
echo.

start "" "http://localhost:%PORT%/"
"%PHP_EXE%" -S 0.0.0.0:%PORT% -t public

endlocal
