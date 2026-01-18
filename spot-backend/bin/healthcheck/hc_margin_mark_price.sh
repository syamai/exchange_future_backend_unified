#!/bin/bash

net="$1"

IFS=','
read -ra symbols <<< "$2"

env_path=`pwd`/.env

logfrequeze=`grep ^APP_LOG= $env_path | cut -d'=' -f 2`
logpath=`grep ^HEALTHCHECK_LOGPATH= $env_path | cut -d'=' -f 2`

for symbol in "${symbols[@]}"; do

    logfilename="MarginMarkPrice$symbol"
    logfile="$logpath$logfilename.log"
    aws_instance=$(curl http://169.254.169.254/latest/meta-data/instance-id)

    if [ "$logfrequeze" = "daily" ]; then
      today=`date +'-%Y-%m-%d'`
      logfile="$logpath$logfilename$today.log"
    fi

    temp_file=`cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 32 | head -n 1`
    tail -n 100 $logfile | tac > $temp_file

    max_interval="0"
    latest_record_time="0"
    last_record_time="0"

    while IFS=, read -r transaction unixtime name domain value
    do
        if [[ $latest_record_time == "0" ]]; then
            latest_record_time="$unixtime"
            last_record_time="$unixtime"
            continue
        fi

        if (( $latest_record_time - $unixtime > 60 )); then
            echo "Break at $unixtime"
            break
        fi

        if (( $last_record_time - $unixtime > $max_interval )); then
            max_interval=$(( $last_record_time - $unixtime ))
        fi
        last_record_time="$unixtime"
    done <$temp_file

    if [[ $max_interval == "0" ]];then
        max_interval="100"
    fi

    rm -rf $temp_file

    /usr/bin/aws cloudwatch put-metric-data --metric-name "${net}MarkPrice$symbol" --dimensions InstanceId=$aws_instance  --namespace "MarginExchange" --value $max_interval

done