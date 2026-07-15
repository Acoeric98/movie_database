@echo off
setlocal
set "PHP_EXE=C:\xampp\php\php.exe"

if not exist "%PHP_EXE%" (
  echo [ERROR] PHP was not found at %PHP_EXE%
  pause
  exit /b 1
)

"%PHP_EXE%" -m

echo.
echo Required modules: curl, mbstring, PDO, pdo_sqlite, sqlite3
pause
endlocal
