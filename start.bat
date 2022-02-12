@echo off
title SpelakoMAHA
:start
php SpelakoMAHA.php --core="../SpelakoCore/SpelakoCore.php" --config="config.json" --verify-key="inputYourKey" --host="http://127.0.0.1:8080/" --qq="123456789"
goto start