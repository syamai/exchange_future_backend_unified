host=`grep ^DB_HOST= .env | cut -d'=' -f 2`
user=`grep ^DB_USERNAME= .env | cut -d'=' -f 2`
pass=`grep ^DB_PASSWORD= .env | cut -d'=' -f 2`
db=`grep ^DB_DATABASE= .env | cut -d'=' -f 2`
mysql -u$user -p$pass -h$host $db < ./database/seeds/dataset/prices.sql
# mysql -u$user -p$pass -h$host $db < ./database/seeds/dataset/blockchain_addresses.sql

current_time=`date +%s`
current_time=$(($current_time*1000))
last_created_at=`mysql -u$user -p$pass -h$host $db -s -N -e "select created_at from prices order by created_at desc limit 1"`
time_offset=$(($current_time-$last_created_at-60000))
mysql -u$user -p$pass -h$host $db -e "update prices set created_at = created_at + $time_offset"