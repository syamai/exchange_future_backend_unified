# RDS & Kafka 스케줄러 통합

## Date
2026-01-19 15:08:58 (KST)

## Prompt
AWS 인프라 비용 절감을 위해 RDS + Kafka EC2 인스턴스의 자동 스케줄링을 EKS 스케줄러와 통합 구현 요청

## Result

### 작업 내용

#### 1. Lambda 함수 확장 (`lib/lambda/eks-scheduler/index.ts`)
기존 EKS 노드 그룹만 관리하던 Lambda 함수를 확장하여 다음 기능 추가:

**주요 변경사항:**
- **RDS 지원**: `@aws-sdk/client-rds` 추가
  - `StartDBInstanceCommand` - RDS 시작
  - `StopDBInstanceCommand` - RDS 중지

- **EC2 지원**: `@aws-sdk/client-ec2` 추가 (Kafka 인스턴스용)
  - `StartInstancesCommand` - EC2 인스턴스 시작
  - `StopInstancesCommand` - EC2 인스턴스 중지

- **통합 API 설계**:
  ```typescript
  interface SchedulerEvent {
    action: 'scale-up' | 'scale-down';
    // EKS config
    clusterName: string;
    nodegroupName: string;
    desiredSize: number;
    minSize: number;
    maxSize: number;
    // RDS config (선택)
    rdsInstanceId?: string;
    // EC2 config (선택)
    ec2InstanceIds?: string[];
  }
  ```

- **병렬 처리**: 세 서비스(EKS, RDS, EC2)를 `Promise.all()`로 동시 실행
- **에러 처리**: 각 서비스별 독립적인 결과 반환 및 로깅

#### 2. 기능 설명

| 항목 | 내용 |
|------|------|
| scale-up 시간대 | EKS 노드그룹 확대 + RDS 시작 + Kafka EC2 시작 |
| scale-down 시간대 | EKS 노드그룹 축소 + RDS 중지 + Kafka EC2 중지 |
| 비용 절감 | ~$56/월(RDS) + ~$17/월(Kafka) = ~$73/월 추가 절감 |

#### 3. 다음 단계 (미완료)
- [ ] CDK 스택 업데이트: RDS/EC2 권한 추가
- [ ] EventBridge 규칙 설정: RDS, Kafka EC2 이벤트 추가
- [ ] 배포 및 테스트

### 기술 스택
- Language: TypeScript
- AWS SDK v3: EKS, RDS, EC2
- 아키텍처: 통합 Lambda 함수 (마이크로서비스가 아닌 모놀리식 핸들러)

### 파일 변경 목록
- ✅ `/lib/lambda/eks-scheduler/index.ts` - Lambda 함수 전체 구현

### 예상 효과
기존 EKS만 스케줄링하던 것에서 RDS와 Kafka EC2까지 통합 관리하여 개발 환경에서의 비용 절감 자동화 실현
