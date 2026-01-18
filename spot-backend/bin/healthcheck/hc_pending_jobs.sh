#!/bin/sh
# this file impliment for CloudWatch only

net="$1"

env_path=`pwd`/.env

error=0

host=`grep ^DB_HOST= $env_path | cut -d'=' -f 2`
port=`grep ^DB_PORT= $env_path | cut -d'=' -f 2`
user=`grep ^DB_USERNAME= $env_path | cut -d'=' -f 2`
pass=`grep ^DB_PASSWORD= $env_path | cut -d'=' -f 2`
# aws_instance=`grep ^AWS_INSTANCE= $env_path | cut -d'=' -f 2`
aws_instance=$(curl http://169.254.169.254/latest/meta-data/instance-id)

pending_jobs=`/usr/bin/mysql -h$host -u$user -p$pass -P$port --connect-timeout=10 -s -N -e "SELECT count(id) FROM amanpuri.jobs;"`
echo "$pending_jobs"
/usr/local/bin/aws cloudwatch put-metric-data --metric-name "${net}PendingJobs" --dimensions InstanceId=$aws_instance  --namespace "Common" --value $pending_jobs

failed_jobs=`/usr/bin/mysql -h$host -u$user -p$pass -P$port --connect-timeout=10 -s -N -e "SELECT count(id) FROM amanpuri.failed_jobs;"`
echo "$failed_jobs"
/usr/local/bin/aws cloudwatch put-metric-data --metric-name "${net}FailedJobs" --dimensions InstanceId=$aws_instance  --namespace "Common" --value $failed_jobs
