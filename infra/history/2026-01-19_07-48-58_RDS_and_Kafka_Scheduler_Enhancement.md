# RDS 및 Kafka EC2 자동 스케줄링 기능 추가

## Date
2026-01-19 07:48:58 UTC (16:48:58 KST)

## Prompt

사용자가 EKS 노드 자동 스케일링에서 더 나아가 다른 서비스도 함께 관리하기를 요청했습니다.

주요 요청 사항:
- RDS (MySQL) 데이터베이스 인스턴스 자동 시작/중지
- Kafka EC2 인스턴스 자동 시작/중지
- 기존 EKS 스케줄러에 통합
- EKS와 동일한 스케줄 (평일 09:00~22:00 KST)

비용 절감 목표:
- RDS: ~$56/월 절감 (24/7 $98 → 12시간 $42)
- Kafka: ~$17/월 절감 (24/7 $30 → 12시간 $13)
- 총 예상: ~$73/월 추가 절감

## Result

### 1. Lambda 함수 확장
**파일**: `lib/lambda/eks-scheduler/index.ts` (130줄)

주요 변경사항:
- **RDS 핸들러 추가** (`handleRds` 함수)
  - `StartDBInstanceCommand`: RDS 시작
  - `StopDBInstanceCommand`: RDS 중지
  - 상태 확인 및 에러 처리
  - InvalidDBInstanceState 예외 무시 처리

- **EC2 핸들러 추가** (`handleEc2` 함수)
  - `StartInstancesCommand`: EC2 인스턴스 시작
  - `StopInstancesCommand`: EC2 인스턴스 중지
  - 다중 인스턴스 지원

- **병렬 처리**
  - Promise.all을 사용하여 EKS, RDS, EC2 작업 동시 실행
  - 각 서비스 독립적으로 실패/성공 처리

### 2. TypeScript 인터페이스 확장
**SchedulerEvent 인터페이스**:
```typescript
interface SchedulerEvent {
  action: 'scale-up' | 'scale-down';
  // EKS config
  clusterName: string;
  nodegroupName: string;
  desiredSize: number;
  minSize: number;
  maxSize: number;
  // RDS config (optional)
  rdsInstanceId?: string;
  // EC2 config (optional)
  ec2InstanceIds?: string[];
}
```

**Result 인터페이스**:
```typescript
interface Result {
  service: string;
  status: 'success' | 'error' | 'skipped';
  message: string;
}
```

### 3. CDK 스택 업데이트
**파일**: `lib/stacks/eks-scheduler-stack.ts` (162줄)

**IAM 권한 추가**:

#### RDS 권한
```typescript
actions: ['rds:StartDBInstance', 'rds:StopDBInstance', 'rds:DescribeDBInstances']
resources: [`arn:aws:rds:${region}:${account}:db:${rdsInstanceId}`]
```

#### EC2 (Kafka) 권한
```typescript
actions: ['ec2:StartInstances', 'ec2:StopInstances', 'ec2:DescribeInstances']
resources: [`arn:aws:ec2:${region}:${account}:instance/${kafkaInstanceId}`]
```

**리소스 식별자**:
- RDS: `exchange-dev-mysql`
- Kafka EC2: `i-044548ca3fe3ae1a1`

**EventBridge 페이로드 확장**:
```typescript
// Scale UP payload
{
  action: 'scale-up',
  // EKS
  clusterName: 'exchange-dev',
  nodegroupName: 'exchange-dev-spot-nodes',
  desiredSize: 3,
  minSize: 2,
  maxSize: 6,
  // RDS
  rdsInstanceId: 'exchange-dev-mysql',
  // Kafka EC2
  ec2InstanceIds: ['i-044548ca3fe3ae1a1']
}
```

### 4. EventBridge 규칙 업데이트
**Scale UP 규칙** (09:00 KST):
- 설명: "Start dev environment at 09:00 KST on weekdays (EKS + RDS + Kafka)"
- 대상: EKS 3대 노드, RDS 시작, Kafka EC2 시작

**Scale DOWN 규칙** (22:00 KST):
- 설명: "Stop dev environment at 22:00 KST on weekdays (EKS + RDS + Kafka)"
- 대상: EKS 0대 노드, RDS 중지, Kafka EC2 중지

### 5. Lambda 함수 응답 포맷
```typescript
{
  statusCode: 200,
  body: JSON.stringify({
    action: 'scale-up',
    results: [
      {
        service: 'EKS',
        status: 'success',
        message: 'Nodegroup scale-up: updateId=...'
      },
      {
        service: 'RDS',
        status: 'success',
        message: 'Starting exchange-dev-mysql'
      },
      {
        service: 'EC2',
        status: 'success',
        message: 'Starting i-044548ca3fe3ae1a1'
      }
    ]
  })
}
```

### 6. 에러 처리 전략

#### RDS 에러 처리
- `InvalidDBInstanceState`: 이미 목표 상태이면 성공으로 처리
- 기타 에러: 로깅 후 에러 반환

#### EC2 에러 처리
- 모든 에러: 로깅 후 에러 반환

#### 전체 응답
- 서비스 중 하나라도 에러면 statusCode 500 반환
- 개별 결과는 모두 반환

### 7. 월 비용 절감 요약

| 서비스 | 현재 비용 | 최적화 후 | 절감액 |
|--------|---------|---------|-------|
| EKS 노드 | $31 | $0 (야간) | ~$18 |
| RDS | $98 | $42 | ~$56 |
| Kafka EC2 | $30 | $13 | ~$17 |
| 총계 | $159 | $55 | **~$104/월** |

**전체 절감**: 기존 $327/월 → ~$223/월 (32% 절감)

### 8. 배포 및 검증

**CDK 합성 완료**:
```
Successfully synthesized to /Users/ahnsungbin/Source/exchange/infra/cdk.out
```

**생성된 스택**:
- Exchange-dev-Vpc
- Exchange-dev-Ecr
- Exchange-dev-Rds
- Exchange-dev-Redis
- Exchange-dev-Kafka
- Exchange-dev-Eks
- Exchange-dev-EksScheduler

### 9. 사용 명령어

**수동 Scale Up**:
```bash
aws lambda invoke --function-name exchange-dev-dev-scheduler \
  --payload '{
    "action": "scale-up",
    "clusterName": "exchange-dev",
    "nodegroupName": "exchange-dev-spot-nodes",
    "desiredSize": 3,
    "minSize": 2,
    "maxSize": 6,
    "rdsInstanceId": "exchange-dev-mysql",
    "ec2InstanceIds": ["i-044548ca3fe3ae1a1"]
  }' \
  --cli-binary-format raw-in-base64-out \
  --region ap-northeast-2 /dev/stdout
```

**수동 Scale Down**:
```bash
aws lambda invoke --function-name exchange-dev-dev-scheduler \
  --payload '{
    "action": "scale-down",
    "clusterName": "exchange-dev",
    "nodegroupName": "exchange-dev-spot-nodes",
    "desiredSize": 0,
    "minSize": 0,
    "maxSize": 6,
    "rdsInstanceId": "exchange-dev-mysql",
    "ec2InstanceIds": ["i-044548ca3fe3ae1a1"]
  }' \
  --cli-binary-format raw-in-base64-out \
  --region ap-northeast-2 /dev/stdout
```

### 10. 주요 특징

✅ **다중 서비스 관리**: EKS, RDS, EC2(Kafka) 통합 관리
✅ **병렬 처리**: 세 서비스 동시 제어로 빠른 응답
✅ **독립적 실패 처리**: 한 서비스 실패가 다른 서비스에 영향 없음
✅ **유연한 구성**: 각 서비스 선택적으로 활성화 가능
✅ **완전 자동화**: 평일 09:00~22:00 자동 관리
✅ **모니터링 가능**: CloudWatch 로그에 각 서비스별 결과 기록
✅ **비용 효율적**: 월 $104 절감 (개발 환경 기준)

### 11. 변경 파일 목록

1. `lib/lambda/eks-scheduler/index.ts` - Lambda 함수 확장
2. `lib/stacks/eks-scheduler-stack.ts` - CDK 스택 업데이트

### 12. 다음 단계

- [ ] 프로덕션 환경(prod) 설정 추가
- [ ] NAT Gateway → NAT Instance 전환 ($29 절감)
- [ ] ElastiCache 다운사이징 ($35 절감)
- [ ] CloudWatch 알람 설정
- [ ] 배포 및 테스트

