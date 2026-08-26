@echo off
REM Installs Dompdf and PhpSpreadsheet (needed for PDF/Excel report exports).
REM Requires Composer: https://getcomposer.org/download/
cd /d "%~dp0.."
echo Installing PHP dependencies (Dompdf, PhpSpreadsheet)...
composer install
echo.
echo Done. PDF and Excel report exports are now enabled.
pause
