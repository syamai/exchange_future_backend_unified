# EKS 노드 자동 스케일링 스케줄러 구현

## Date
2026-01-19 14:40:02 KST

## Prompt
사용자가 EKS 개발 서버의 야간 비용을 절감하기 위해 노드 스케일링 자동화를 요청했습니다.

주요 요청 사항:
- EKS 노드 그룹 자동 스케일링 구현
- Lambda + EventBridge를 이용한 스케줄 기반 자동화
- 평일 기준 운영 시간 설정 (09:00 ~ 22:00 KST)

## Result

### 구현된 기능

#### 1. EksSchedulerStack 생성
**파일**: `infra/lib/stacks/eks-scheduler-stack.ts` (128줄)

주요 구현 내용:
- **Lambda 함수**: NodeJS 20.x 런타임으로 EKS 노드 스케일링 처리
- **IAM 권한**: Lambda에 EKS NodeGroup 업데이트 권한 부여
- **EventBridge 스케줄**:
  - **Scale Up**: 평일 09:00 KST (UTC 00:00) - 노드 3대로 확대
  - **Scale Down**: 평일 22:00 KST (UTC 13:00) - 노드 0대로 축소

#### 2. Lambda 함수 구현
**파일**: `infra/lib/lambda/eks-scheduler/index.ts` (47줄)

주요 기능:
- EKS API를 통해 NodeGroup 설정 업데이트
- Scale-up/Scale-down 액션 처리
- 환경 변수로부터 클러스터 및 노드그룹 정보 수신

#### 3. 보안 그룹 설정 개선
**파일**: `infra/lib/stacks/vpc-stack.ts` 수정

추가된 규칙:
- 노드간 통신 (Self-referencing)
- Kubernetes API (443)
- Kubelet (10250)
- CoreDNS (53)

#### 4. CDK 앱 통합
**파일**: `infra/bin/app.ts` 수정

- EksSchedulerStack을 CDK 앱에 등록
- 다른 스택과의 의존성 설정

#### 5. EKS 클러스터 관리자 추가
**파일**: `infra/lib/stacks/eks-stack.ts` 수정

- IAM 사용자 'Alex'를 EKS 클러스터 관리자로 추가 (aws-auth ConfigMap)

### 예상 비용 절감

| 항목 | 월 비용 절감 |
|------|------------|
| 노드 비용 | ~$72 |
| Spot 인스턴스 할인 적용 시 | ~$36-54 |
| **총 절감액** | **약 $36-54/월** (12시간 운영 기준) |

### 구현 세부사항

**스케줄 설정**:
- 평일(월-금) 자동 운영
- 매일 정해진 시간에 자동 스케일링
- 수동 스케일링 명령어 제공

**Lambda 함수 사양**:
- 런타임: Node.js 20.x
- 메모리: 256MB
- 타임아웃: 5분
- 함수명: `exchange-dev-eks-scheduler`

**EventBridge 규칙**:
- 규칙명: `exchange-dev-eks-scale-up`, `exchange-dev-eks-scale-down`
- 트리거: Cron 스케줄 표현식
- 타겟: Lambda 함수 (payload 전달)

### 배포 완료
- 커밋: `bd81ed2`
- 커밋 메시지: "feat(infra): add EKS scheduler and fix security group drift"
- 변경 파일: 6개
- 추가된 코드: 228줄

### 사용 방법

**자동 스케줄 확인**:
```bash
aws events list-rules --name-prefix exchange-dev-eks
```

**수동 Scale Up**:
```bash
aws lambda invoke --function-name exchange-dev-eks-scheduler \
  --payload '{"action":"scale-up","clusterName":"exchange-dev","nodegroupName":"exchange-dev-spot-nodes","desiredSize":3,"minSize":2,"maxSize":6}' \
  --cli-binary-format raw-in-base64-out /dev/stdout
```

**수동 Scale Down**:
```bash
aws lambda invoke --function-name exchange-dev-eks-scheduler \
  --payload '{"action":"scale-down","clusterName":"exchange-dev","nodegroupName":"exchange-dev-spot-nodes","desiredSize":0,"minSize":0,"maxSize":6}' \
  --cli-binary-format raw-in-base64-out /dev/stdout
```

### 주요 특징
✅ 완전 자동화 - 수동 개입 불필요
✅ 비용 효율적 - 야간 비용 최소화
✅ 유연한 스케줄 - 평일/주말 구분 설정 가능
✅ CDK로 인프라 관리 - 코드 기반 배포 가능
✅ 모니터링 용이 - CloudWatch 로그 통합
