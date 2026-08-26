@echo off
set XAMPP_PATH=C:\xampp

echo Stopping Apache...
call "%XAMPP_PATH%\apache_stop.bat"

echo Stopping MySQL...
call "%XAMPP_PATH%\mysql_stop.bat"

echo Done.
pause
