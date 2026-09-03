@echo off
REM Start the Laravel backend on port 50051 (matches the old Rust fge-backend).
REM Usage: dev.bat
where php >nul 2>nul
if %errorlevel%==0 (
  php artisan serve --host=0.0.0.0 --port=50051
) else (
  "C:\tools\php\php.exe" artisan serve --host=0.0.0.0 --port=50051
)