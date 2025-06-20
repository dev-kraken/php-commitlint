@echo off
rem PHP CommitLint Windows Batch Wrapper
rem This file allows PHP CommitLint to be executed on Windows systems

setlocal

rem Find PHP executable
set PHP_EXECUTABLE=php.exe
where php.exe >nul 2>&1
if %errorlevel% neq 0 (
    set PHP_EXECUTABLE=php
    where php >nul 2>&1
    if %errorlevel% neq 0 (
        echo Error: PHP not found in PATH
        exit /b 1
    )
)

rem Get the directory where this batch file is located
set SCRIPT_DIR=%~dp0

rem Execute the PHP script
"%PHP_EXECUTABLE%" "%SCRIPT_DIR%php-commitlint" %*

rem Exit with the same code as the PHP script
exit /b %errorlevel% 