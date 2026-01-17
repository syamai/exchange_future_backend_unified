#!/bin/bash

source /home/ubuntu/engine/deploy/env.sh

if [ -z "$KAFKA_BROKERS" ]; then
  echo "KAFKA_BROKERS is not set.";
else
  echo "KAFKA_BROKERS '$KAFKA_BROKERS'";
  #cd /home/sotatek/sota-dex-be && yarn console:dev ticker:load
  java -cp /home/ubuntu/engine/MatchingEngine-1.0-jar-with-dependencies.jar com.sotatek.future.TickerEngineCLI $KAFKA_BROKERS
fi


#!/bin/bash

source /home/ubuntu/engine/deploy/env.sh

echo "BACKEND_IMAGE $BACKEND_IMAGE"
echo "EKS_CLUSTER $EKS_CLUSTER"

REGION=`curl -s http://169.254.169.254/latest/meta-data/placement/availability-zone | sed 's/\(.*\)[a-z]/\1/'`
aws ecr get-login-password --region=$REGION | docker login --username AWS --password-stdin $BACKEND_IMAGE

echo "
Please add EC2's IAm role to aws-auth configmap on EKS cluster. For example:
- rolearn: arn:aws:iam::487168310572:role/sota-dex-stg-engine-EngineInstanceRole-10JGOJLRZ5ABJ
  username: engine
  groups:
    - system:masters
"
aws eks --region $REGION update-kubeconfig --name $EKS_CLUSTER
CONFIG=`kubectl get configmap sota-dex -o jsonpath='{.data}'`
SECRET=`kubectl get secret sota-dex -o jsonpath='{.data}'`

echo '' > /home/ubuntu/.env.ticker

for s in $(echo $CONFIG | jq -r "to_entries|map(\"\(.key)=\(.value|tostring)\")|.[]" ); do
    echo $s >> /home/ubuntu/.env.ticker
done

for s in $(echo $SECRET | jq -r "to_entries|map(\"\(.key)=\(.value|tostring)\")|.[]" ); do
    key=`echo $s | cut -d'=' -f 1`
    value=`echo $s | cut -d'=' -f 2`
    value=`echo $value | base64 --decode`
    echo $key=$value >> /home/ubuntu/.env.ticker
done

KAFKA_BROKERS=`grep ^KAFKA_BROKERS= /home/ubuntu/.env.ticker | cut -d'=' -f 2`
echo "KAFKA_BROKERS $KAFKA_BROKERS"

docker pull $BACKEND_IMAGE
docker run --env-file /home/ubuntu/.env.ticker $BACKEND_IMAGE node dist/console.js ticker:load
java -cp /home/ubuntu/engine/MatchingEngine-1.0-jar-with-dependencies.jar com.sotatek.future.TickerEngineCLI $KAFKA_BROKERS