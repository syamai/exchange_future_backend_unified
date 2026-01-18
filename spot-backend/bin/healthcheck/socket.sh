email=$1
date=`date`
time=`date +%s`

error="0"

response=`curl -s -o /dev/null -I -w "%{http_code}" https://socket.vcc.exchange:6001/socket.io/socket.io.js`
if [ "$response" != "200" ]; then
    error="1"
    subject="WARNING: socket has been stopped"
    content="WARNING: socket has been stopped. Code: $response"
    php artisan email:send $email "$subject" "$content"
fi

echo "Checked socket at $time $date, error: $error"