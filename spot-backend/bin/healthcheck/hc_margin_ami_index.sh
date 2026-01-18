#!/bin/bash

env=$1

IFS=','
read -ra symbols <<< "$2"

interval=$3

env_path=`pwd`/.env

aws_instance=$(curl http://169.254.169.254/latest/meta-data/instance-id)


host=`grep ^DB_HOST= $env_path | cut -d'=' -f 2`
port=`grep ^DB_PORT= $env_path | cut -d'=' -f 2`
database=`grep ^DB_DATABASE= $env_path | cut -d'=' -f 2`
username=`grep ^DB_USERNAME= $env_path | cut -d'=' -f 2`
password=`grep ^DB_PASSWORD= $env_path | cut -d'=' -f 2`

# date=`date -d "1 min ago" '+%Y-%m-%d'`

time=`date -d "1 min ago" "+%Y-%m-%d %H:%M"`

start_time="$time:00"
end_time="$time:59"

echo $start_time $end_time


for symbol in "${symbols[@]}"; do
    index_count="0"

    index_query="SELECT count(*) FROM indices WHERE symbol='$symbol' AND created_at between '$start_time' AND '$end_time';"
    index_count=`/usr/bin/mysql -h$host -u$username -p$password -P$port $database -s -N -e "$index_query"`
    if [[ $index_count == "" ]]; then index_count="0"; fi

    echo $index_count

    /usr/bin/aws cloudwatch put-metric-data \
        --metric-name "${env}MarginAMIIndex" \
        --dimensions InstanceId=$aws_instance \
        --namespace "MarginExchange" \
        --value $index_count

done


# 30M index
time=`date -d "5 mins ago" '+%Y-%m-%d %H'`
minute=`date -d "5 mins ago" +"%M" | sed 's/^0*//'`

(( minute = minute/30, minute *= 30 ))
if (( $minute < 10 ));then minute="0$minute"; fi

time="$time:$minute:00"

for symbol in "${symbols[@]}"; do
    index_count="0"

    index_query="SELECT count(*) FROM indices WHERE symbol='${symbol}30M' AND created_at='$time';"
    index_count=`/usr/bin/mysql -h$host -u$username -p$password -P$port $database -s -N -e "$index_query"`
    if [[ $index_count == "" ]]; then index_count="0"; fi

    echo $index_count

    /usr/bin/aws cloudwatch put-metric-data \
        --metric-name "${env}Margin30MIndex" \
        --dimensions InstanceId=$aws_instance \
        --namespace "MarginExchange" \
        --value $index_count

done