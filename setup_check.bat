@echo off
echo ================================
echo HopeDrops Blood Bank System
echo Quick Setup Verification
echo ================================
echo.

echo Checking XAMPP installation...
if not exist "C:\xampp\xampp-control.exe" (
    echo ❌ XAMPP not found at C:\xampp\
    echo Please install XAMPP first from https://www.apachefriends.org/
    pause
    exit /b 1
)
echo ✅ XAMPP found

echo.
echo Checking Apache service...
netstat -an | findstr :80 >nul
if %errorlevel% equ 0 (
    echo ✅ Apache appears to be running on port 80
) else (
    echo ⚠️  Apache may not be running. Please start it in XAMPP Control Panel
)

echo.
echo Checking MySQL service...
netstat -an | findstr :3306 >nul
if %errorlevel% equ 0 (
    echo ✅ MySQL appears to be running on port 3306
) else (
    echo ⚠️  MySQL may not be running. Please start it in XAMPP Control Panel
)

echo.
echo Checking application files...
if exist "%~dp0index.html" (
    echo ✅ Application files found
) else (
    echo ❌ Application files not found in current directory
    echo Please ensure you're running this from the HopeDrops folder
)

if exist "%~dp0sql\bloodbank_complete.sql" (
    echo ✅ Database SQL file found
) else (
    echo ❌ Database SQL file not found
    echo Please ensure sql/bloodbank_complete.sql exists
)

echo.
echo ================================
echo Next steps:
echo 1. Start Apache and MySQL in XAMPP Control Panel
echo 2. Import sql/bloodbank_complete.sql in phpMyAdmin
echo 3. Open http://localhost/HopeDrops in your browser
echo ================================
echo.
echo Opening XAMPP Control Panel...
start "" "C:\xampp\xampp-control.exe"

echo.
echo Opening phpMyAdmin for database import...
timeout /t 3 /nobreak >nul
start "" "http://localhost/phpmyadmin"

echo.
echo Opening HopeDrops application...
timeout /t 5 /nobreak >nul
start "" "http://localhost/HopeDrops"

pause