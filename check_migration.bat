@echo off
echo Running migrations...
php artisan migrate --force
echo.
echo Checking migration status...
php artisan migrate:status | find "2026_02_12"
echo.
echo Running check script...
php check_database.php
pause
