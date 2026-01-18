#!/bin/sh
email=$1

date=`date`
time=`date +%s`

if [ -z $email ]; then
    echo "email is required."
    echo "Checked order processor at $time $date"
    exit
fi

pm2 logs --lines 28 --nostream 01_order_processor > .opcheck.tmp
tail -n 26 .opcheck.tmp > .opcheck.tmp2 && mv .opcheck.tmp2 .opcheck.tmp
head -n 25 .opcheck.tmp > .opcheck.tmp2 && mv .opcheck.tmp2 .opcheck.tmp


error="0"
message=""
logs=""
while read line; do
    logs="$logs<br/>$line"
    if [[ ! "$line" =~ "Checking processor status at" ]]; then
        error="1"
        message="WARNING: Order processor has been restarted."
    fi

done <.opcheck.tmp

if [[ $error -eq "0" ]]; then
    last_run=`tail -n 1 .opcheck.tmp | grep -oP "\d{13}"`
    last_run=$(( last_run / 1000 ))
    currenct_time=`date +%s`
    if (( currenct_time - last_run > 10 )); then
        error="1"
        message="WARNING: Order processor has been stopped."
    fi
fi

if [[ $error -eq "1" ]]; then
    content="$message<br/>$logs<br/>Checked at $date."
    php artisan email:send $email "$message" "$content"
fi

rm -rf .opcheck.tmp
echo "Checked order processor at $time $date, error: $error"