#!/bin/bash
 
IMAGE_VERSION=v1.5
API_TAG=registry.gitlab.com/thanh89/sotatek-exchange-api:api-$IMAGE_VERSION
SERVICE_TAG=registry.gitlab.com/thanh89/sotatek-exchange-api:service-$IMAGE_VERSION

docker build -t $API_TAG -f Dockerfiledev .
docker build -t $SERVICE_TAG -f Dockerfilecron .

docker push $API_TAG
docker push $SERVICE_TAG


# export VERSION="api-$IMAGE_VERSION"
# envsubst < kube/api/deployment.yml | kubesotatek apply -f -

# export VERSION=service-$IMAGE_VERSION
# envsubst < kube/spot-matching-engine/deployment.yml | kubesotatek apply -f -

# export VERSION=service-$IMAGE_VERSION
# envsubst < kube/spot/deployment.yml | kubesotatek apply -f -

# export VERSION=cron-$IMAGE_VERSION
# envsubst < kube/amanpuri-margin/cron.yml | kubesotatek apply -f -

# export VERSION=service-$IMAGE_VERSION
# envsubst < kube/index/deployment.yml | kubesotatek apply -f -

# export VERSION=service-$IMAGE_VERSION
# envsubst < kube/amanpuri-margin-matching/cron.yml | kubesotatek apply -f -

# export CIRCLE_SHA1=$IMAGE_VERSION
# envsubst < k8s/amanpuri-admin/deployment.yml | kubesotatek apply -f -