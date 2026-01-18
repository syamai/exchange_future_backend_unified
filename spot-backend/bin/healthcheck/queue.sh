email=$1
queue=$2

date=`date`
time=`date +%s`

if [ -z $email ]; then
    echo "email is required."
    echo "Checked $queue at $time $date"
    exit
fi
if [ -z $queue ]; then
    echo "queue is required."
    echo "Checked $queue at $time $date"
    exit
fi


tmp_name=".$queue.tmp"
tmp_name2=".$queue.tmp2"

pm2 logs --lines 20 --nostream --out --raw $queue > $tmp_name
tail -n 11 $tmp_name > $tmp_name2 && mv $tmp_name2 $tmp_name
head -n 10 $tmp_name > $tmp_name2 && mv $tmp_name2 $tmp_name


error="0"
message=""
logs=""
while read line; do
    logs="$logs<br/>$line"
    if [[ "$line" =~ "^string" ]]; then
        if [[ ! "$line" =~ "Processing data: " ]]; then
            if [[ ! "$line" =~ "at: [0-9]{13}" ]]; then
                error="1"
                message="WARNING: $queue error."
            fi
        fi
    fi
done <$tmp_name

if [[ $error -eq "0" ]]; then
    last_run=`tail -n 1 $tmp_name | grep -oP "\d{13}$"`
    last_run=$(( last_run / 1000 ))
    current_time=`date +%s`
    if (( current_time - last_run > 60 )); then
        error="1"
        message="WARNING: $queue has been stopped."
    fi
fi

if [[ $error -eq "1" ]]; then
    content="$message<br/>$logs<br/>Checked at $date."
    php artisan email:send $email "$message" "$content"
fi

rm -rf $tmp_name
echo "Checked $queue at $time $date, error: $error"