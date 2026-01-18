#!/bin/sh
env_path=`pwd`/.env

logfrequeze=`grep ^APP_LOG= $env_path | cut -d'=' -f 2`
logpath=`grep ^HEALTHCHECK_LOGPATH= $env_path | cut -d'=' -f 2`
# aws_instance=`grep ^AWS_INSTANCE= $env_path | cut -d'=' -f 2`
aws_instance=$(curl http://169.254.169.254/latest/meta-data/instance-id)

logfilename="CircuitBreaker-Lock"
logfile="$logpath$logfilename.log"

if [ "$logfrequeze" = "daily" ]; then
  today=`date +'-%Y-%m-%d'`
  logfile="$logpath$logfilename$today.log"
fi

i=0
while IFS=, read -r transaction unixtime name domain value
do
    i=$((i+1))
    # pattern="$transaction,$unixtime,$name,$domain,$value"
    # echo "line $i => $pattern"
    if [ $name != 'Matching-engine' ]; then
      namespace="$name-$domain"
      echo "$namespace => $value"
      /usr/bin/aws cloudwatch put-metric-data --metric-name "$name" --dimensions Instance=$aws_instance  --namespace "$domain" --value $value
      sed -i "$i d" $logfile
      i=$((i-1))
    fi
done <$logfile