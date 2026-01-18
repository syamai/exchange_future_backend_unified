#!/bin/bash

env=$1

IFS=','
read -ra symbols <<< "$2"

env_path=`pwd`/.env

aws_instance=$(curl http://169.254.169.254/latest/meta-data/instance-id)


host=`grep ^DB_HOST= $env_path | cut -d'=' -f 2`
port=`grep ^DB_PORT= $env_path | cut -d'=' -f 2`
database=`grep ^DB_DATABASE= $env_path | cut -d'=' -f 2`
username=`grep ^DB_USERNAME= $env_path | cut -d'=' -f 2`
password=`grep ^DB_PASSWORD= $env_path | cut -d'=' -f 2`

time=`date +"%H:%M:%S"`

if [[ $time < "04:05:00" ]]; then
    funding_date=`date -d "1 day ago" '+%Y-%m-%d'`
else
    funding_date=`date '+%Y-%m-%d'`
fi

if [[ $time < "04:05:00" ]]; then
    funding_time="20:00:00"
elif [[ $time < "12:05:00" ]]; then
    funding_time="04:00:00"
elif [[ $time < "20:05:00" ]]; then
    funding_time="12:00:00"
else
    funding_time="20:00:00"
fi

funding_time="$funding_date $funding_time"

echo $funding_time

if [[ $time > "20:05:30" ]]; then
    next_funding_date=`date -d "next day" '+%Y-%m-%d'`
else
    next_funding_date=`date '+%Y-%m-%d'`
fi

if [[ $time < "04:05:30" ]]; then
    next_funding_time="04:00:00"
elif [[ $time < "12:05:30" ]]; then
    next_funding_time="12:00:00"
elif [[ $time < "20:05:30" ]]; then
    next_funding_time="20:00:00"
else
    next_funding_time="04:00:00"
fi

next_funding_time="$next_funding_date $next_funding_time"
echo $next_funding_time

for symbol in "${symbols[@]}"; do
    funding_rate="0"
    funding_process="0"

    funding_rate_query="SELECT count(*) FROM fundings WHERE symbol='$symbol' AND created_at='$next_funding_time';"
    funding_rate=`/usr/bin/mysql -h$host -u$username -p$password -P$port $database -s -N -e "$funding_rate_query"`
    if [[ $funding_rate == "" ]]; then funding_rate="0"; fi

    funding_process_query="SELECT is_processed FROM margin_processes WHERE \`key\`='margin_pay_funding_${symbol}_${funding_time}' limit 1;"
    funding_process=`/usr/bin/mysql -h$host -u$username -p$password -P$port $database -s -N -e "$funding_process_query"` || `echo "0"`
    if [[ $funding_process == "" ]]; then funding_process="0"; fi

    echo $funding_rate $funding_process

    /usr/bin/aws cloudwatch put-metric-data \
        --metric-name "${env}MarginFundingRate" \
        --dimensions InstanceId=$aws_instance \
        --namespace "MarginExchange" \
        --value $funding_rate

    /usr/bin/aws cloudwatch put-metric-data \
        --metric-name "${env}MarginFundingProcess" \
        --dimensions InstanceId=$aws_instance \
        --namespace "MarginExchange" \
        --value $funding_process

done
