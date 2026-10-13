@echo off
title Pathology Laboratory Management System - Stopper
color 0C
echo Stopping Apache and MySQL services...
if exist "C:\xampp\xampp_stop.exe" (
    start "" "C:\xampp\xampp_stop.exe"
)
timeout /t 2 /nobreak >nul
echo Services stopped successfully.
exit
