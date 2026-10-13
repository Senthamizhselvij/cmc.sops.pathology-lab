@echo off
title Pathology Laboratory Management System - 1-Click Starter
color 0A
echo ======================================================================
echo       PATHOLOGY LABORATORY MANAGEMENT SYSTEM - 1-CLICK LAUNCHER
echo ======================================================================
echo.

:: 1. Start XAMPP Apache and MySQL automatically
if exist "C:\xampp\xampp_start.exe" (
    echo [1/3] Starting Apache and MySQL services via XAMPP...
    start "" "C:\xampp\xampp_start.exe"
) else (
    echo [1/3] Starting MySQL and Apache services...
    if exist "C:\xampp\mysql_start.bat" start "" "C:\xampp\mysql_start.bat"
    if exist "C:\xampp\apache_start.bat" start "" "C:\xampp\apache_start.bat"
)

:: 2. Wait 3 seconds for MySQL & Apache to be fully ready
echo [2/3] Waiting for server services to initialize...
timeout /t 3 /nobreak >nul

:: 3. Open Pathology Lab directly in the default web browser
echo [3/3] Opening Pathology Lab in your browser...
start "" "http://localhost/pathology-lab/"

echo.
echo ======================================================================
echo   SUCCESS: Application opened at http://localhost/pathology-lab/
echo   (Database is auto-created & auto-imported on first run!)
echo ======================================================================
echo.
timeout /t 4 >nul
exit
