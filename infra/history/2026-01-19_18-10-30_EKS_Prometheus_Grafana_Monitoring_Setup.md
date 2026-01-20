# EKS Prometheus + Grafana 모니터링 설정

## Date
2026-01-19 18:10:30 KST

## Prompt
EKS 클러스터에 Prometheus + Grafana를 이용한 모니터링 스택을 설정해달라.

## Result

### 수행 작업

#### 1. Prometheus + Grafana 설정 파일 생성
- **파일**: `infra/k8s/monitoring-values.yaml`
- **내용**: Helm kube-prometheus-stack 설치를 위한 Values 파일
  - Prometheus 설정 (7일 데이터 유지, 10GB 스토리지)
  - Grafana 설정 (LoadBalancer 서비스, 5GB 스토리지)
  - AlertManager 설정
  - 비용 최적화를 위한 불필요한 컴포넌트 비활성화
    - kube-etcd, kube-controller-manager, kube-scheduler, kube-proxy 비활성화
  - Node Exporter와 kube-state-metrics 활성화

#### 2. Helm 저장소 설정
```bash
helm repo add prometheus-community https://prometheus-community.github.io/helm-charts
helm repo update
```

#### 3. Monitoring 네임스페이스 생성
```bash
kubectl create namespace monitoring
```

#### 4. 설치 시도
```bash
helm install prometheus-stack prometheus-community/kube-prometheus-stack \
  --namespace monitoring \
  -f k8s/monitoring-values.yaml \
  --wait --timeout 10m
```

### 상태
🔄 **구현 진행 중**
- Helm 저장소 추가 및 네임스페이스 생성: ✅ 완료
- monitoring-values.yaml 설정 파일 생성: ✅ 완료
- Prometheus + Grafana 스택 설치: ⚠️ 진행 중 (초기 타임아웃 후 재시도 필요)

### 다음 단계
- [ ] Helm 설치 완료 (타임아웃 에러 해결)
- [ ] Grafana 접근 설정 (LoadBalancer IP 확인)
- [ ] 기본 대시보드 설정
- [ ] EKS 메트릭 수집 확인
- [ ] 알림 규칙 설정

### 기술 사항
- **스택**: kube-prometheus-stack (Prometheus + Grafana + AlertManager)
- **리소스**: 비용 최적화 설정 (dev 환경 기준)
  - Prometheus: 512Mi RAM / 250m CPU request, 1Gi / 500m limit
  - Grafana: 128Mi RAM / 100m CPU request, 256Mi / 200m limit
  - AlertManager: 64Mi RAM / 50m CPU request
- **스토리지**:
  - Prometheus: 10GB PVC
  - Grafana: 5GB PVC (대시보드/설정 저장)

### 주의사항
- 개발 환경용 임시 비밀번호 설정됨 (운영 환경에서 변경 필요)
- 스토리지 클래스 확인 필요 (기본값 사용)
- 인그레스/로드밸런서 설정으로 외부 접근 구성 필요
