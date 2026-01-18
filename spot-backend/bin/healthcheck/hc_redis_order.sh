#!/bin/sh

env=$1

env_path=`pwd`/.env

host=`grep ^REDIS_HOST= $env_path | cut -d'=' -f 2`
port=`grep ^REDIS_PORT= $env_path | cut -d'=' -f 2`
aws_instance=$(curl http://169.254.169.254/latest/meta-data/instance-id)
# EC2_INSTANCE_ID=`grep ^AWS_BUCKET= $env_path | cut -d'=' -f 2`
# aws_instance=`grep ^AWS_INSTANCE= $env_path | cut -d'=' -f 2`
#ping_result=`redis-cli -h $host -p $port ping`

bigecho() { echo; echo "## $1"; echo; }
exiterr()  { echo "Error: $1" >&2; exit 1; }

#bigecho "1) Check redis service"
#if [ "$ping_result" != "PONG" ]; then
  # echo "Error"
  #/usr/local/bin/aws cloudwatch put-metric-data --metric-name Redis --dimensions Instance=$aws_instance  --namespace "Redis" --value 0
  #exiterr "Redis not working"
  # exit
#else
  #/usr/local/bin/aws cloudwatch put-metric-data --metric-name Redis --dimensions Instance=$aws_instance  --namespace "Redis" --value 1
#fi


#bigecho "2) get Redis command"
#printf "redis information:\n host: %s\n port: %s\n instance: %s\n" $host $port $aws_instance
# printf "redis-cli -h %s -p %s -n 1 keys margin_unprocessed_orders*\n" $host $port
# printf "redis-cli -h %s -p %s -n 1 keys order*\n" $host $port

sent="0"
for each_unprocessd_order in $( redis-cli -h $host -p $port -n 1 keys margin_unprocessed_orders\* ); do
    value=$(redis-cli -h $host -p $port -n 1 zcount $each_unprocessd_order -9000000000000000 9000000000000000)
    if [[ $value =~ ^[+-]?[0-9]+$ ]]; then
        echo "$each_unprocessd_order $value"
        sent="1"
        /usr/local/bin/aws cloudwatch put-metric-data \
            --metric-name "${env}MarginUnprocessedOrder" \
            --dimensions InstanceId=$aws_instance \
            --namespace "Redis" \
            --value $value
    fi
done
if [[ $sent == "0" ]]; then
    /usr/local/bin/aws cloudwatch put-metric-data \
        --metric-name "${env}MarginUnprocessedOrder" \
        --dimensions InstanceId=$aws_instance \
        --namespace "Redis" \
        --value "0"
fi


sent="0"
for each_order in $( redis-cli -h $host -p $port -n 1 keys un_processed_order_\* ); do
    order_numb=$(redis-cli -h $host -p $port -n 1 zcount $each_order -9000000000000000 9000000000000000)
    if [[ $order_numb =~ ^[+-]?[0-9]+$ ]]; then
        sent="1"
        /usr/local/bin/aws cloudwatch put-metric-data \
            --metric-name "${env}SpotUnprocessedOrder" \
            --dimensions InstanceId=$aws_instance \
            --namespace "Redis" \
            --value $order_numb
    fi
done
if [[ $sent == "0" ]]; then
    /usr/local/bin/aws cloudwatch put-metric-data \
        --metric-name "${env}SpotUnprocessedOrder" \
        --dimensions InstanceId=$aws_instance \
        --namespace "Redis" \
        --value "0"
fi
