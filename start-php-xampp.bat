@echo off
cd /d "%~dp0"
echo.
echo ========================================
echo   GameFlux - PHP Server Starten
echo ========================================
echo.

if exist "C:\xampp\php\php.exe" (
    echo PHP gevonden in XAMPP!
    echo.
    echo Server start op: http://localhost:8000
    echo Druk op Ctrl+C om te stoppen
    echo.
    "C:\xampp\php\php.exe" -S localhost:8000
) else if exist "C:\php\php.exe" (
    echo PHP gevonden in C:\php!
    echo.
    "C:\php\php.exe" -S localhost:8000
) else (
    echo PHP niet gevonden!
    echo.
    echo Installeer XAMPP van: https://www.apachefriends.org
    echo OF voeg PHP toe aan je PATH
    echo.
    pause
)

