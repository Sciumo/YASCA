@echo off

rem SET THIS TO A DIFFERENT PATH TO USE A DIFFERENT VERSION OF FXCOP
set FXCOP_PATH=%ProgramFiles%\Microsoft FxCop 1.36

if not exist "%FXCOP_PATH%" goto notfound

if "%~1"=="" goto nodir
set TDEST=%~dp0
set TGT=%~1


cd "%FXCOP_PATH%"

rem echo "%FXCOP_PATH%\FxCopCmd.exe" /c "/rule:%FXCOP_PATH%\Rules\SecurityRules.dll" "/rule:%FXCOP_PATH%\Rules\DesignRules.dll" /iit "/file:%TGT%"
rem "%FXCOP_PATH%\FxCopCmd.exe" "/out:%TDEST%\scan.xml" "/rule:%FXCOP_PATH%\Rules\SecurityRules.dll" "/rule:%FXCOP_PATH%\Rules\DesignRules.dll" /iit "/file:%TGT%" 2>&1 > NUL

"%FXCOP_PATH%\FxCopCmd.exe" /savemessagestoreport:Excluded "/out:%TDEST%\scan.xml" "/r:+%FXCOP_PATH%\Rules\SecurityRules.dll" "/r:+%FXCOP_PATH%\Rules\UsageRules.dll" "/r:+%FXCOP_PATH%\Rules\PerformanceRules.dll" "/r:+%FXCOP_PATH%\Rules\PortabilityRules.dll" "/r:+%FXCOP_PATH%\Rules\MobilityRules.dll" "/r:+%FXCOP_PATH%\Rules\InteroperabilityRules.dll" "/r:+%FXCOP_PATH%\Rules\GlobalizationRules.dll" "/r:+%FXCOP_PATH%\Rules\NamingRules.dll" "/r:+%FXCOP_PATH%\Rules\DesignRules.dll" /iit "/f:%TGT%" 2>&1 > NUL

goto end
:notfound
echo Cannot find Microsoft FxCop. Please install from http://code.msdn.microsoft.com/codeanalysis or change FXCOP_PATH in resources\utility\fxcop\fxcop.bat
goto enderror

goto end
:nodir
echo No file or directory passed to fxcop.bat. Nothing to scan.
goto enderror

:end
cd %TDEST%
if exist scan.xml type scan.xml
if exist scan.xml erase scan.xml
goto realend

:enderror
echo Error

:realend