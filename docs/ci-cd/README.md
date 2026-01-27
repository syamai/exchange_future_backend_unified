# CI/CD Pipeline Documentation

GitHub Actions를 사용한 CI/CD 파이프라인 문서입니다.

## 아키텍처 개요

```
┌─────────────────────────────────────────────────────────────────────┐
│                        GitHub Repository                             │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐               │
│  │ spot-backend │  │future-backend│  │future-engine │               │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘               │
└─────────┼─────────────────┼─────────────────┼───────────────────────┘
          │                 │                 │
          ▼                 ▼                 ▼
┌─────────────────────────────────────────────────────────────────────┐
│                     GitHub Actions Workflows                         │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐               │
│  │    Test      │  │    Test      │  │    Test      │               │
│  │  (PHP 8.1)   │  │ (Node.js 22) │  │  (Java 17)   │               │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘               │
│         │                 │                 │                        │
│         ▼                 ▼                 ▼                        │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐               │
│  │ Build & Push │  │ Build & Push │  │ Build & Push │               │
│  │   to ECR     │  │   to ECR     │  │   to ECR     │               │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘               │
└─────────┼─────────────────┼─────────────────┼───────────────────────┘
          │                 │                 │
          ▼                 ▼                 ▼
┌─────────────────────────────────────────────────────────────────────┐
│                         Amazon ECR                                   │
│  ┌──────────────┐  ┌──────────────┐  ┌────────────────────┐         │
│  │spot-backend  │  │future-backend│  │matching-engine-shard│        │
│  └──────────────┘  └──────────────┘  └────────────────────┘         │
└─────────────────────────────────────────────────────────────────────┘
          │                 │                 │
          ▼                 ▼                 ▼
┌─────────────────────────────────────────────────────────────────────┐
│                         Amazon EKS                                   │
│  ┌───────────────────────────────────────────────────────────┐      │
│  │  Dev Environment                                           │      │
│  │  ┌────────────┐  ┌────────────┐  ┌────────────────────┐  │      │
│  │  │spot-backend│  │future-     │  │matching-engine     │  │      │
│  │  │    -dev    │  │backend-dev │  │  shards (1-3)      │  │      │
│  │  └────────────┘  └────────────┘  └────────────────────┘  │      │
│  └───────────────────────────────────────────────────────────┘      │
│  ┌───────────────────────────────────────────────────────────┐      │
│  │  Prod Environment (Manual Approval Required)               │      │
│  │  ┌────────────┐  ┌────────────┐  ┌────────────────────┐  │      │
│  │  │spot-backend│  │future-     │  │matching-engine     │  │      │
│  │  │   -prod    │  │backend-prod│  │  shards (1-3)      │  │      │
│  │  └────────────┘  └────────────┘  └────────────────────┘  │      │
│  └───────────────────────────────────────────────────────────┘      │
└─────────────────────────────────────────────────────────────────────┘
```

## 워크플로우 목록

| 워크플로우 | 파일 | 트리거 조건 |
|------------|------|-------------|
| Spot Backend | `.github/workflows/spot-backend.yml` | `spot-backend/**` 변경 |
| Future Backend | `.github/workflows/future-backend.yml` | `future-backend/**` 변경 |
| Future Engine | `.github/workflows/future-engine.yml` | `future-engine/**` 변경 |

## 배포 환경

### Development (Dev)

- **자동 배포**: `main` 브랜치에 push 시 자동 배포
- **네임스페이스**:
  - `spot-backend-dev`
  - `future-backend-dev`
  - `matching-engine-dev`

### Production (Prod)

- **수동 승인 필요**: GitHub Environment Protection Rules 적용
- **Canary 배포**: 10% → 100% 순차 배포
- **네임스페이스**:
  - `spot-backend-prod`
  - `future-backend-prod`
  - `matching-engine-prod`

## 사용법

### 1. 자동 배포 (Push to main)

```bash
# 코드 변경 후 main 브랜치에 push
git add .
git commit -m "feat: add new feature"
git push origin main
```

해당 디렉토리 변경 시 자동으로 CI/CD 파이프라인이 실행됩니다.

### 2. 수동 배포 (Workflow Dispatch)

GitHub Actions 탭에서 수동으로 워크플로우를 실행할 수 있습니다.

1. GitHub 저장소의 **Actions** 탭으로 이동
2. 좌측에서 원하는 워크플로우 선택
3. **Run workflow** 버튼 클릭
4. 환경 선택 (dev / prod)
5. **Run workflow** 클릭

### 3. 프로덕션 배포 승인 절차

1. Dev 환경 배포 완료 후 자동으로 Prod 배포 대기
2. GitHub에서 **Environments** → **production** 승인 요청 확인
3. 승인자가 **Review deployments** 클릭
4. **Approve and deploy** 클릭
5. Canary 배포 시작 (10% → Health Check → 100%)

## 파이프라인 상세

### Test Job

각 프로젝트별 테스트 환경:

| 프로젝트 | 언어 | 테스트 명령 | 서비스 |
|----------|------|-------------|--------|
| Spot Backend | PHP 8.1 | `php artisan test` | MySQL, Redis |
| Future Backend | Node.js 22 | `yarn test` | MySQL, Redis |
| Future Engine | Java 17 | `mvn verify` | - |

### Build Job

- Docker 이미지 빌드 (`--platform linux/amd64`)
- Amazon ECR로 푸시
- 이미지 태그: `{commit-sha}`, `latest`

### Deploy Job

- Kustomize를 사용한 K8s 매니페스트 관리
- Rolling Update 전략
- Rollout Status 확인

## 롤백 방법

### 1. kubectl rollout undo 사용

```bash
# Dev 환경 롤백
kubectl rollout undo deployment/spot-backend-api-dev -n spot-backend-dev
kubectl rollout undo deployment/dev-future-backend -n future-backend-dev
kubectl rollout undo statefulset/dev-matching-engine-shard-1 -n matching-engine-dev

# Prod 환경 롤백
kubectl rollout undo deployment/spot-backend-api -n spot-backend-prod
kubectl rollout undo deployment/future-backend -n future-backend-prod
kubectl rollout undo statefulset/matching-engine-shard-1 -n matching-engine-prod
```

### 2. 이전 이미지 태그로 재배포

```bash
# ECR에서 이전 이미지 태그 확인
aws ecr describe-images \
  --repository-name exchange/spot-backend \
  --query 'imageDetails[*].[imageTags,imagePushedAt]' \
  --output table

# kustomization.yaml에서 이미지 태그 변경 후 적용
cd spot-backend/k8s/overlays/dev
kustomize edit set image exchange/spot-backend=<ECR_URL>:<이전_태그>
kubectl apply -k .
```

### 3. GitHub Actions에서 이전 버전 재배포

1. Actions 탭에서 해당 워크플로우 선택
2. 이전 성공한 워크플로우 실행 확인
3. **Re-run all jobs** 클릭

## 트러블슈팅

### 빌드 실패 시 확인사항

1. **테스트 실패**
   ```bash
   # 로컬에서 테스트 실행
   cd spot-backend && php artisan test
   cd future-backend && yarn test
   cd future-engine && mvn verify
   ```

2. **Docker 빌드 실패**
   ```bash
   # 로컬에서 빌드 테스트
   docker build -t test --platform linux/amd64 -f Dockerfile_cicd .
   ```

3. **ECR 푸시 실패**
   - AWS 자격 증명 확인
   - ECR 저장소 존재 여부 확인
   - IAM 권한 확인

### 배포 실패 시 확인사항

1. **Pod 상태 확인**
   ```bash
   kubectl get pods -n <namespace>
   kubectl describe pod <pod-name> -n <namespace>
   kubectl logs <pod-name> -n <namespace>
   ```

2. **이미지 풀 실패**
   - ECR 이미지 태그 확인
   - EKS 노드의 ECR 접근 권한 확인

3. **리소스 부족**
   ```bash
   kubectl describe nodes
   kubectl top nodes
   kubectl top pods -n <namespace>
   ```

4. **ConfigMap/Secret 누락**
   ```bash
   kubectl get configmaps -n <namespace>
   kubectl get secrets -n <namespace>
   ```

### 롤아웃 타임아웃

기본 타임아웃: 300초 (5분)

```bash
# 수동으로 롤아웃 상태 확인
kubectl rollout status deployment/<name> -n <namespace> --timeout=600s
```

## 모니터링

### GitHub Actions 대시보드

- 저장소 → Actions 탭
- 워크플로우 실행 이력 확인
- 실패한 Job의 로그 확인

### Kubernetes 모니터링

```bash
# Pod 로그 실시간 확인
kubectl logs -f deployment/<name> -n <namespace>

# 이벤트 확인
kubectl get events -n <namespace> --sort-by='.lastTimestamp'
```

## 관련 문서

- [GitHub Secrets 설정 가이드](./github-secrets-setup.md)
- [프로덕션 배포 체크리스트](../production-deployment-checklist.md)
- [스테이징 테스트 계획](../staging-test-plan.md)
- [롤백 절차](../implementation-guide/rollback-procedure.md)
