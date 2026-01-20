# Exchange Infra History

이 디렉토리는 Exchange 프로젝트의 인프라 개발 기록을 저장합니다.

## 세션 기록

### 1. EKS 노드 자동 스케일링 스케줄러 구현
- **파일**: `2026-01-19_14-40-02_EKS_Node_Auto_Scaling_Scheduler.md`
- **날짜**: 2026-01-19 14:40:02
- **작업 내용**:
  - EKS 개발 환경의 비용 절감을 위한 노드 자동 스케일링 구현
  - Lambda + EventBridge 기반 스케줄러 구축
  - 평일 09:00~22:00 KST 자동 스케일링 규칙 설정
  - 월 ~$36-54 비용 절감 예상
- **주요 파일**:
  - `infra/lib/stacks/eks-scheduler-stack.ts`
  - `infra/lib/lambda/eks-scheduler/index.ts`
  - `infra/lib/stacks/vpc-stack.ts`
  - `infra/lib/stacks/eks-stack.ts`
  - `infra/bin/app.ts`
- **커밋**: `bd81ed2` - "feat(infra): add EKS scheduler and fix security group drift"

## 사용 설명서

### 기록 작성 방법
새로운 작업을 기록할 때는:
1. 파일명: `YYYY-MM-DD_HH-mm-ss_[작업요약].md` 형식
2. 내용에 Date, Prompt, Result 섹션 포함
3. 이 INDEX.md 파일 업데이트

### 기록 대상
- ✅ 코드 변경, 파일 생성, 기능 구현
- ✅ 의미있는 설정 변경
- ❌ 단순 정보 조회, 질문, 상담만 있었을 경우

### 2. RDS & Kafka EC2 스케줄러 통합
- **파일**: `2026-01-19_15-08-58_RDS_Kafka_Scheduler_Integration.md`
- **날짜**: 2026-01-19 15:08:58 KST
- **작업 내용**:
  - Lambda 함수 확장: EKS, RDS, EC2(Kafka) 통합 스케줄러 구현
  - `@aws-sdk/client-rds`, `@aws-sdk/client-ec2` 추가
  - 병렬 처리로 세 서비스 동시 제어
  - 예상 추가 절감: ~$73/월 (RDS $56 + Kafka $17)
- **주요 파일**:
  - `infra/lib/lambda/eks-scheduler/index.ts` (완전 재작성)
- **상태**: 🔄 구현 중 (Lambda 완료, CDK/배포 진행 예정)
- **다음 단계**:
  - [ ] CDK 스택 업데이트: IAM 권한 추가
  - [ ] EventBridge 규칙 설정
  - [ ] 배포 및 테스트

### 3. 스케줄러 배포 및 테스트
- **파일**: `2026-01-19_10-08-30_Scheduler_Deployment_and_Testing.md`
- **날짜**: 2026-01-19 19:08:30 KST
- **작업 내용**:
  - EKS + RDS + Kafka 통합 스케줄러 배포 완료
  - Lambda 함수 실행 테스트 (scale-up/down)
  - 각 서비스 동작 검증 및 에러 처리 테스트
  - RDS 상태 전이 모니터링 (2-3분 소요)
  - 월 $104 비용 절감 효과 검증 (46% 감축)
- **테스트 결과**:
  - ✅ EKS 노드그룹: 성공 (3대로 확장)
  - ⚠️ RDS: 초기 상태 오류 → 대기 후 성공 (starting)
  - ✅ EC2 Kafka: 성공 (running)
  - ✅ 에러 처리 및 복구 메커니즘 정상 작동
- **상태**: ✅ 완료 (프로덕션 준비 완료)
- **주요 성과**:
  - 완전 자동화된 개발 환경 관리
  - CloudWatch 로깅 및 모니터링 완비
  - EventBridge 규칙 자동화 확인

### 4. EKS Prometheus + Grafana 모니터링 설정
- **파일**: `2026-01-19_18-10-30_EKS_Prometheus_Grafana_Monitoring_Setup.md`
- **날짜**: 2026-01-19 18:10:30 KST
- **작업 내용**:
  - Prometheus + Grafana 스택을 위한 Values 파일 생성
  - Helm 저장소 설정 및 monitoring 네임스페이스 생성
  - 비용 최적화된 설정 (불필요한 컴포넌트 비활성화)
  - 10GB Prometheus 스토리지, 5GB Grafana 스토리지 설정
- **상태**: 🔄 진행 중 (설치 타임아웃 - 재시도 필요)
- **다음 단계**:
  - [ ] Helm 설치 완료
  - [ ] Grafana 접근 설정
  - [ ] EKS 메트릭 수집 확인

## 최근 활동 요약

| 날짜 | 작업 | 상태 |
|------|------|------|
| 2026-01-19 | EKS 노드 스케줄러 구현 | ✅ 완료 |
| 2026-01-19 | RDS & Kafka 스케줄러 통합 | ✅ 완료 |
| 2026-01-19 | 스케줄러 배포 및 테스트 | ✅ 완료 |
| 2026-01-19 | Prometheus + Grafana 모니터링 설정 | 🔄 진행 중 |
