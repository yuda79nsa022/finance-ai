@echo off
REM ===================================================================
REM  Personal Finance Tracker — Start Apache + MySQL (XAMPP)
REM  Edit XAMPP_PATH below if XAMPP is not installed at C:\xampp
REM ===================================================================
set XAMPP_PATH=C:\xampp

echo Starting Apache...
start "" "%XAMPP_PATH%\apache_start.bat"

echo Starting MySQL...
start "" "%XAMPP_PATH%\mysql_start.bat"

timeout /t 5 /nobreak >nul

echo.
echo Apache and MySQL should now be running.
echo Open your browser to: http://localhost/finance/public/
echo (If this is the first run, go to http://localhost/finance/install.php first.)
echo.
pause
