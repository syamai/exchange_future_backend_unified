email=$1

date=`date`
time=`date +%s`
env_path=`pwd`/.env

if [ -z $email ]; then
    echo "email is required."
    echo "Checked database at $time $date"
    exit
fi

error=0

host=`grep ^DB_HOST= $env_path | cut -d'=' -f 2`
port=`grep ^DB_PORT= $env_path | cut -d'=' -f 2`
user=`grep ^DB_USERNAME= $env_path | cut -d'=' -f 2`
pass=`grep ^DB_PASSWORD= $env_path | cut -d'=' -f 2`

ping_result=`mysql -h$host -u$user -p$pass -P$port --connect-timeout=10 -s -N -e "select 1;"`

if [ "$ping_result" != "1" ]; then
    error=1
    subject="WARNING database(master)"
    content="WARNING: database(master) error."
    content="$content<br/>$ping_result<br/>Checked at $date."
    php artisan email:send $email "$subject" "$content"
fi

host=`grep ^SLAVE_DB_HOST= $env_path | cut -d'=' -f 2`
user=`grep ^SLAVE_DB_USERNAME= $env_path | cut -d'=' -f 2`
pass=`grep ^SLAVE_DB_PASSWORD= $env_path | cut -d'=' -f 2`

ping_result=`mysql -h$host -u$user -p$pass -P$port --connect-timeout=10 -s -N -e "select 1;"`

if [ "$ping_result" != "1" ]; then
    error=2
    subject="WARNING database(slave)"
    content="WARNING: database(slave) error."
    content="$content<br/>$ping_result<br/>Checked at $date."
    php artisan email:send $email "$subject" "$content"
fi

ping_result=`mysql -h$host -uslave_monitor -pga23AFDa -P$port --connect-timeout=10 -s -N -e "show slave 'trading' status;"`
slave_status=`echo $ping_result | grep "Waiting for master to send event"`
if [ "$slave_status" == "" ]; then
    error=3
    subject="WARNING replication"
    content="WARNING: replication error."
    content="$content<br/>$ping_result<br/>Checked at $date."
    php artisan email:send $email "$subject" "$content"
fi

echo "Checked database at $time $date, error: $error"