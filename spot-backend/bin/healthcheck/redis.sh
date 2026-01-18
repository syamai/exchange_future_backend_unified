email=$1
date=`date`
time=`date +%s`
env_path=`pwd`/.env

if [ -z $email ]; then
    echo "email is required."
    echo "Checked redis at $time $date"
    exit
fi

error=0

host=`grep ^REDIS_HOST= $env_path | cut -d'=' -f 2`
port=`grep ^REDIS_PORT= $env_path | cut -d'=' -f 2`

ping_result=`redis-cli -h $host -p $port ping`

if [ "$ping_result" != "PONG" ]; then
    error=1
    subject="WARNING: redis"
    content="WARNING: redis doesn't reponse."
    content="$content<br/>$ping_result<br/>Checked at $date."
    php artisan email:send $email "$subject" "$content"
fi

host=`grep ^OP_REDIS_HOST= $env_path | cut -d'=' -f 2`
port=`grep ^OP_REDIS_PORT= $env_path | cut -d'=' -f 2`

ping_result=`redis-cli -h $host -p $port ping`

if [ "$ping_result" != "PONG" ]; then
    error=1
    subject="WARNING: redis (order processor)"
    content="WARNING: redis (order processor) doesn't reponse."
    content="$content<br/>$ping_result<br/>Checked at $date."
    php artisan email:send $email "$subject" "$content"
fi
echo "Checked redis at $time $date, error: $error"