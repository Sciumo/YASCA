@echo off
rem Yasca plugin installation helper script

set VERSION=2.0

if "%1"=="" goto usage
goto usage_ok

:usage
echo Usage: install-plugins.bat yasca-install-directory
goto done

:usage_ok

echo.
echo This script downloads plugin code from the Internet and unzips it into the Yasca directory. This
echo code is not part of the yasca-core distribution, which is licensed under BSD. Instead, these
echo components are licensed under various terms, including GPL, LGPL, and others. See their 
echo respective licenses for more information.
echo.
choice /N /M "Do you wish to continue? (Y/N)"
if errorlevel 2 exit

rem set BASE_URL=http://prdownloads.sourceforge.net/sourceforge/yasca/
set BASE_URL=http://archives.scovetta.com/private/
set UNZ=resources\utility\unzip.exe -d "%1" -o
set CURL=resources\utility\curl.exe -#

if not exist %UNZ% do goto ERR_NO_UNZ

set COMPONENT=clamav
echo Downloading yasca-%COMPONENT%...
%CURL% -o yasca-%VERSION%-%COMPONENT%.zip %BASE_URL%yasca-%VERSION%-%COMPONENT%.zip
if not errorlevel 0 echo Unable to download yasca-%VERSION%-%COMPONENT. Please download it from SourceForge and unzip it into the main Yasca directory.
if errorlevel 0 %UNZ% yasca-%VERSION%-%COMPONENT%.zip 
erase yasca-%VERSION%-%COMPONENT%.zip

set COMPONENT=cppcheck
echo Downloading yasca-%COMPONENT%...
%CURL% -o yasca-%VERSION%-%COMPONENT%.zip %BASE_URL%yasca-%VERSION%-%COMPONENT%.zip
if not errorlevel 0 echo Unable to download yasca-%VERSION%-%COMPONENT. Please download it from SourceForge and unzip it into the main Yasca directory.
if errorlevel 0 %UNZ% yasca-%VERSION%-%COMPONENT%.zip
erase yasca-%VERSION%-%COMPONENT%.zip

set COMPONENT=findbugs
echo Downloading yasca-%COMPONENT%...
%CURL% -o yasca-%VERSION%-%COMPONENT%.zip %BASE_URL%yasca-%VERSION%-%COMPONENT%.zip
if not errorlevel 0 echo Unable to download yasca-%VERSION%-%COMPONENT. Please download it from SourceForge and unzip it into the main Yasca directory.
if errorlevel 0 echo %UNZ% yasca-%VERSION%-%COMPONENT%.zip 
erase yasca-%VERSION%-%COMPONENT%.zip

set COMPONENT=findbugs-plugin
echo Downloading yasca-%COMPONENT%...
%CURL% -o yasca-%VERSION%-%COMPONENT%.zip %BASE_URL%yasca-%VERSION%-%COMPONENT%.zip
if not errorlevel 0 echo Unable to download yasca-%VERSION%-%COMPONENT. Please download it from SourceForge and unzip it into the main Yasca directory.
if errorlevel 0 %UNZ% yasca-%VERSION%-%COMPONENT%.zip 
erase yasca-%VERSION%-%COMPONENT%.zip

set COMPONENT=javascriptlint
echo Downloading yasca-%COMPONENT%...
%CURL% -o yasca-%VERSION%-%COMPONENT%.zip %BASE_URL%yasca-%VERSION%-%COMPONENT%.zip
if not errorlevel 0 echo Unable to download yasca-%VERSION%-%COMPONENT. Please download it from SourceForge and unzip it into the main Yasca directory.
if errorlevel 0 %UNZ% yasca-%VERSION%-%COMPONENT%.zip 
erase yasca-%VERSION%-%COMPONENT%.zip

set COMPONENT=jlint
echo Downloading yasca-%COMPONENT%...
%CURL% -o yasca-%VERSION%-%COMPONENT%.zip %BASE_URL%yasca-%VERSION%-%COMPONENT%.zip
if not errorlevel 0 echo Unable to download yasca-%VERSION%-%COMPONENT. Please download it from SourceForge and unzip it into the main Yasca directory.
if errorlevel 0 %UNZ% yasca-%VERSION%-%COMPONENT%.zip 
erase yasca-%VERSION%-%COMPONENT%.zip

set COMPONENT=phplint
echo Downloading yasca-%COMPONENT%...
%CURL% -o yasca-%VERSION%-%COMPONENT%.zip %BASE_URL%yasca-%VERSION%-%COMPONENT%.zip
if not errorlevel 0 echo Unable to download yasca-%VERSION%-%COMPONENT. Please download it from SourceForge and unzip it into the main Yasca directory.
if errorlevel 0 %UNZ% yasca-%VERSION%-%COMPONENT%.zip 
erase yasca-%VERSION%-%COMPONENT%.zip

set COMPONENT=pixy
echo Downloading yasca-%COMPONENT%...
%CURL% -o yasca-%VERSION%-%COMPONENT%.zip %BASE_URL%yasca-%VERSION%-%COMPONENT%.zip
if not errorlevel 0 echo Unable to download yasca-%VERSION%-%COMPONENT. Please download it from SourceForge and unzip it into the main Yasca directory.
if errorlevel 0 %UNZ% yasca-%VERSION%-%COMPONENT%.zip 
erase yasca-%VERSION%-%COMPONENT%.zip

set COMPONENT=pmd
echo Downloading yasca-%COMPONENT%...
%CURL% -o yasca-%VERSION%-%COMPONENT%.zip %BASE_URL%yasca-%VERSION%-%COMPONENT%.zip
if not errorlevel 0 echo Unable to download yasca-%VERSION%-%COMPONENT. Please download it from SourceForge and unzip it into the main Yasca directory.
if errorlevel 0 %UNZ% yasca-%VERSION%-%COMPONENT%.zip 
erase yasca-%VERSION%-%COMPONENT%.zip

echo Installation complete.

goto done

:ERR_NO_UNZ
echo unzip was not found in the resources directory. Please download plugins manually and unzip them into the main Yasca directory.
goto done

:done
