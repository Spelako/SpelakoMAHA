#!/usr/bin/env sh
while :
do
php SpelakoMAHA.php --core="../SpelakoCore/SpelakoCore.php" --config="config.json" --verify-key="inputYourKey" --host="http://127.0.0.1:8080/" --qq="123456789"
echo "Waiting for 10 seconds, press Ctrl+C to quit ..."; sleep 10
done