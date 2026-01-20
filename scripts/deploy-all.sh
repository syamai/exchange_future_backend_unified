#!/bin/bash
set -e

#######################################
# Exchange Infrastructure One-Click Deploy
#
# Usage:
#   ./scripts/deploy-all.sh [env] [options]
#
# Examples:
#   ./scripts/deploy-all.sh dev              # Deploy all to dev
#   ./scripts/deploy-all.sh dev --infra-only # CDK only
#   ./scripts/deploy-all.sh dev --app-only   # K8s apps only
#######################################

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Default values
ENV="${1:-dev}"
REGION="ap-northeast-2"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"

# Parse options
INFRA_ONLY=false
APP_ONLY=false
SKIP_CONFIRM=false

for arg in "$@"; do
  case $arg in
    --infra-only) INFRA_ONLY=true ;;
    --app-only) APP_ONLY=true ;;
    --yes|-y) SKIP_CONFIRM=true ;;
    --help|-h)
      echo "Usage: $0 [env] [options]"
      echo ""
      echo "Options:"
      echo "  --infra-only    Deploy CDK infrastructure only"
      echo "  --app-only      Deploy K8s applications only"
      echo "  --yes, -y       Skip confirmation prompts"
      echo "  --help, -h      Show this help"
      exit 0
      ;;
  esac
done

log_info() { echo -e "${BLUE}[INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[SUCCESS]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

print_banner() {
  echo ""
  echo -e "${GREEN}╔════════════════════════════════════════════════════════╗${NC}"
  echo -e "${GREEN}║     Exchange Infrastructure One-Click Deploy           ║${NC}"
  echo -e "${GREEN}║                                                        ║${NC}"
  echo -e "${GREEN}║  Environment: ${YELLOW}${ENV}${GREEN}                                      ║${NC}"
  echo -e "${GREEN}║  Region: ${YELLOW}${REGION}${GREEN}                              ║${NC}"
  echo -e "${GREEN}╚════════════════════════════════════════════════════════╝${NC}"
  echo ""
}

check_prerequisites() {
  log_info "Checking prerequisites..."

  local missing=()

  command -v aws >/dev/null 2>&1 || missing+=("aws-cli")
  command -v kubectl >/dev/null 2>&1 || missing+=("kubectl")
  command -v docker >/dev/null 2>&1 || missing+=("docker")
  command -v node >/dev/null 2>&1 || missing+=("node")
  command -v npx >/dev/null 2>&1 || missing+=("npx")

  if [ ${#missing[@]} -ne 0 ]; then
    log_error "Missing required tools: ${missing[*]}"
    exit 1
  fi

  # Check AWS credentials
  if ! aws sts get-caller-identity >/dev/null 2>&1; then
    log_error "AWS credentials not configured"
    exit 1
  fi

  log_success "All prerequisites met"
}

deploy_cdk_infrastructure() {
  log_info "Deploying CDK Infrastructure..."

  cd "$ROOT_DIR/infra"

  # Install dependencies if needed
  if [ ! -d "node_modules" ]; then
    log_info "Installing CDK dependencies..."
    npm install
  fi

  # Bootstrap CDK if needed
  log_info "Checking CDK bootstrap..."
  npx cdk bootstrap -c env=$ENV 2>/dev/null || true

  # Deploy all stacks in order
  local stacks=(
    "Exchange-${ENV}-Vpc"
    "Exchange-${ENV}-Ecr"
    "Exchange-${ENV}-Rds"
    "Exchange-${ENV}-Redis"
    "Exchange-${ENV}-Kafka"
    "Exchange-${ENV}-Eks"
    "Exchange-${ENV}-EksScheduler"
  )

  for stack in "${stacks[@]}"; do
    log_info "Deploying $stack..."
    npx cdk deploy "$stack" -c env=$ENV --require-approval never
    log_success "$stack deployed"
  done

  log_success "CDK Infrastructure deployed successfully"
}

configure_kubectl() {
  log_info "Configuring kubectl..."

  aws eks update-kubeconfig \
    --name "exchange-${ENV}" \
    --region "$REGION"

  # Verify connection
  if kubectl get nodes >/dev/null 2>&1; then
    log_success "kubectl configured successfully"
  else
    log_error "Failed to connect to EKS cluster"
    exit 1
  fi
}

wait_for_rds() {
  log_info "Waiting for RDS to be available..."

  local max_attempts=30
  local attempt=1

  while [ $attempt -le $max_attempts ]; do
    local status=$(aws rds describe-db-instances \
      --db-instance-identifier "exchange-${ENV}-mysql" \
      --region "$REGION" \
      --query 'DBInstances[0].DBInstanceStatus' \
      --output text 2>/dev/null || echo "not-found")

    if [ "$status" = "available" ]; then
      log_success "RDS is available"
      return 0
    elif [ "$status" = "stopped" ]; then
      log_info "Starting RDS..."
      aws rds start-db-instance \
        --db-instance-identifier "exchange-${ENV}-mysql" \
        --region "$REGION"
    fi

    log_info "RDS status: $status (attempt $attempt/$max_attempts)"
    sleep 30
    ((attempt++))
  done

  log_error "RDS did not become available in time"
  exit 1
}

deploy_k8s_resources() {
  log_info "Deploying Kubernetes resources..."

  # Create namespace if not exists
  kubectl create namespace "future-backend-${ENV}" --dry-run=client -o yaml | kubectl apply -f -

  # Deploy backend ConfigMap and Secrets
  log_info "Deploying ConfigMaps and Secrets..."
  kubectl apply -f "$ROOT_DIR/future-backend/k8s/base/" -n "future-backend-${ENV}" 2>/dev/null || true

  # Deploy HPA
  log_info "Deploying HPA..."
  kubectl apply -f "$ROOT_DIR/future-backend/k8s/base/hpa.yaml" -n "future-backend-${ENV}"

  log_success "Kubernetes resources deployed"
}

build_and_push_images() {
  log_info "Building and pushing Docker images..."

  # Get ECR login
  aws ecr get-login-password --region "$REGION" | \
    docker login --username AWS --password-stdin \
    "$(aws sts get-caller-identity --query Account --output text).dkr.ecr.${REGION}.amazonaws.com"

  # Build and push future-backend
  local backend_repo="$(aws sts get-caller-identity --query Account --output text).dkr.ecr.${REGION}.amazonaws.com/exchange/future-backend"
  local tag="v$(date +%Y%m%d-%H%M%S)"

  log_info "Building future-backend:$tag..."
  cd "$ROOT_DIR/future-backend"
  docker build -t "$backend_repo:$tag" -t "$backend_repo:latest" .

  log_info "Pushing future-backend:$tag..."
  docker push "$backend_repo:$tag"
  docker push "$backend_repo:latest"

  log_success "Docker images built and pushed"
  echo "$tag" > "$ROOT_DIR/.last-deploy-tag"
}

deploy_backend_app() {
  log_info "Deploying Backend application..."

  local tag="latest"
  if [ -f "$ROOT_DIR/.last-deploy-tag" ]; then
    tag=$(cat "$ROOT_DIR/.last-deploy-tag")
  fi

  local repo="$(aws sts get-caller-identity --query Account --output text).dkr.ecr.${REGION}.amazonaws.com/exchange/future-backend"

  # Update deployment image
  kubectl set image deployment/dev-future-backend \
    future-backend="$repo:$tag" \
    -n "future-backend-${ENV}" 2>/dev/null || \
  log_warn "Deployment not found, may need manual deployment"

  # Wait for rollout
  kubectl rollout status deployment/dev-future-backend \
    -n "future-backend-${ENV}" \
    --timeout=300s 2>/dev/null || true

  log_success "Backend application deployed"
}

install_cluster_autoscaler() {
  log_info "Installing Cluster Autoscaler..."

  # Check if already installed
  if kubectl get deployment cluster-autoscaler -n kube-system >/dev/null 2>&1; then
    log_info "Cluster Autoscaler already installed"
    return
  fi

  kubectl apply -f https://raw.githubusercontent.com/kubernetes/autoscaler/master/cluster-autoscaler/cloudprovider/aws/examples/cluster-autoscaler-autodiscover.yaml

  # Patch with cluster name
  kubectl patch deployment cluster-autoscaler \
    -n kube-system \
    --type='json' \
    -p="[{\"op\": \"replace\", \"path\": \"/spec/template/spec/containers/0/command/6\", \"value\": \"--node-group-auto-discovery=asg:tag=k8s.io/cluster-autoscaler/enabled,k8s.io/cluster-autoscaler/exchange-${ENV}\"}]" 2>/dev/null || true

  log_success "Cluster Autoscaler installed"
}

install_metrics_server() {
  log_info "Installing Metrics Server..."

  if kubectl get deployment metrics-server -n kube-system >/dev/null 2>&1; then
    log_info "Metrics Server already installed"
    return
  fi

  kubectl apply -f https://github.com/kubernetes-sigs/metrics-server/releases/latest/download/components.yaml

  log_success "Metrics Server installed"
}

print_summary() {
  echo ""
  echo -e "${GREEN}╔════════════════════════════════════════════════════════╗${NC}"
  echo -e "${GREEN}║              Deployment Complete! 🎉                   ║${NC}"
  echo -e "${GREEN}╚════════════════════════════════════════════════════════╝${NC}"
  echo ""

  log_info "Resources deployed:"
  echo "  - VPC & Networking"
  echo "  - EKS Cluster"
  echo "  - RDS MySQL"
  echo "  - ElastiCache Redis"
  echo "  - Kafka (Redpanda)"
  echo "  - Backend Application"
  echo "  - HPA & Cluster Autoscaler"
  echo ""

  log_info "Useful commands:"
  echo "  kubectl get pods -n future-backend-${ENV}"
  echo "  kubectl get hpa -n future-backend-${ENV}"
  echo "  kubectl logs -f deployment/dev-future-backend -n future-backend-${ENV}"
  echo ""

  # Get Load Balancer URL
  local lb_url=$(kubectl get svc dev-future-backend-lb -n "future-backend-${ENV}" -o jsonpath='{.status.loadBalancer.ingress[0].hostname}' 2>/dev/null || echo "pending")
  if [ -n "$lb_url" ] && [ "$lb_url" != "pending" ]; then
    log_info "API Endpoint: http://$lb_url"
  fi

  echo ""
  log_info "Schedule:"
  echo "  - Auto Start: 09:00 KST (Mon-Fri)"
  echo "  - Auto Stop:  21:00 KST (Mon-Fri)"
  echo ""
}

# Main execution
main() {
  print_banner

  if [ "$SKIP_CONFIRM" = false ]; then
    echo -e "This will deploy the ${YELLOW}${ENV}${NC} environment."
    read -p "Continue? (y/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
      log_info "Deployment cancelled"
      exit 0
    fi
  fi

  check_prerequisites

  if [ "$APP_ONLY" = false ]; then
    deploy_cdk_infrastructure
  fi

  configure_kubectl

  if [ "$INFRA_ONLY" = false ]; then
    wait_for_rds
    install_metrics_server
    install_cluster_autoscaler
    deploy_k8s_resources
    # build_and_push_images  # Uncomment to rebuild images
    deploy_backend_app
  fi

  print_summary
}

main
