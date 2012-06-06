set VERSION=2.0
cd yasca-clamav
jar cvfM ..\yasca-%VERSION%-clamav.zip *
cd ..
jar cvfM ..\yasca-%VERSION%.zip yasca-core/*
cd yasca-cppcheck
jar cvfM ..\yasca-%VERSION%-cppcheck.zip *
cd ..\yasca-findbugs
jar cvfM ..\yasca-%VERSION%-findbugs.zip *
cd ..\yasca-findbugs-plugin
jar cvfM ..\yasca-%VERSION%-findbugs-plugin.zip *
cd ..\yasca-javascriptlint
jar cvfM ..\yasca-%VERSION%-javascriptlint.zip *
cd ..\yasca-jlint
jar cvfM ..\yasca-%VERSION%-jlint.zip *
cd ..\yasca-phplint
jar cvfM ..\yasca-%VERSION%-phplint.zip *
cd ..\yasca-pixy
jar cvfM ..\yasca-%VERSION%-pixy.zip *
cd ..\yasca-pmd
jar cvfM ..\yasca-%VERSION%-pmd.zip *