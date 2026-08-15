@echo off
:: Matikan baris di bawah karena server database sudah berjalan di sistem (misal via XAMPP)
:: start "" "database_server\bin\mysqld.exe" --defaults-file=..\database_server\my.ini

:: Jalankan aplikasi PHP Desktop
start "" phpdesktop-chrome.exe