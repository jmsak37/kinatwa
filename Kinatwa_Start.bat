@echo off
setlocal

set "XAMPP_DIR=C:\xampp"
set "KINATWA_URL=http://localhost/kinatwa/"
set "CHROME_EXE=%ProgramFiles%\Google\Chrome\Application\chrome.exe"

if not exist "%CHROME_EXE%" set "CHROME_EXE=%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe"

rem Start Apache and MySQL in background
start "" /min "%XAMPP_DIR%\apache_start.bat"
timeout /t 3 /nobreak >nul
start "" /min "%XAMPP_DIR%\mysql_start.bat"

rem Wait a bit for services to come up
timeout /t 8 /nobreak >nul

rem Open Chrome fullscreen
if exist "%CHROME_EXE%" (
    start "" "%CHROME_EXE%" --start-fullscreen --new-window "%KINATWA_URL%"
) else (
    start "" "%KINATWA_URL%"
)

endlocal
exit