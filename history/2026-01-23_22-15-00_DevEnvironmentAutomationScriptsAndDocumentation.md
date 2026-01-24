# 개발 환경 자동화 스크립트 및 문서화

**Date**: 2026-01-23 22:15:00

## Prompt (사용자 요청)

spot-backend 소스 코드 분석 및 최적화 기회 식별 (spot-backend PHP Laravel 코드베이스 분석 세션)

세션 진행 중 기존 인프라 최적화 작업과 매칭 엔진 관련 수정사항이 발생함.

## Result (수행 결과)

### 1. 개발 환경 자동화 스크립트 작성

**위치**: `future-backend/scripts/`

#### 1-1. 개발 환경 시작 스크립트 (dev-environment-start.sh)
- **목적**: AWS 인프라 스케일 업 및 매칭 엔진 초기화 자동화
- **기능**:
  - AWS Lambda를 통한 인프라 스케일 업 (EKS, RDS, Kafka, NAT, Redis)
  - 각 컴포넌트 준비 완료 대기 (최대 600초)
  - Kafka 토픽 및 Consumer Group 리셋
  - 매칭 엔진 초기화 명령 전송 (INITIALIZE_ENGINE, UPDATE_INSTRUMENT, START_ENGINE)
  - 시스템 상태 검증 및 헬스체크
- **실행 단계**:
  1. AWS 인프라 스케일 업
  2. EKS 노드 대기
  3. RDS 준비 대기
  4. Kafka 준비 대기
  5. Redis 준비 대기
  6. Kafka 토픽 상태 리셋
  7. Backend Pod 준비 대기
  8. Matching Engine Pod 준비 대기
  9. 매칭 엔진 초기화
  10. 시스템 상태 검증
- **실행 시간**: 약 5-10분
- **사용법**: `./scripts/dev-environment-start.sh`

#### 1-2. 개발 환경 중지 스크립트 (dev-environment-stop.sh)
- **목적**: 개발 환경 리소스 정리 및 비용 절감
- **기능**:
  - AWS Lambda를 통한 인프라 스케일 다운
  - EKS 노드: 0으로 축소
  - RDS: 중지
  - Kafka EC2: 중지
  - NAT Instance: 중지
  - Redis: 삭제 (다음 시작 시 재생성)
- **사용법**: `./scripts/dev-environment-stop.sh`

#### 1-3. 개발 환경 상태 확인 스크립트 (dev-environment-status.sh)
- **목적**: 개발 환경 각 컴포넌트의 현재 상태 조회
- **조회 항목**:
  - EKS 노드 상태 및 크기
  - RDS 상태 및 인스턴스 클래스
  - Kafka EC2 인스턴스 상태
  - NAT Instance 상태
  - Redis 상태
  - Backend Pod 상태
  - Matching Engine Pod 상태
- **사용법**: `./scripts/dev-environment-status.sh`

### 2. CLAUDE.md 문서 업데이트

**위치**: `CLAUDE.md`

#### 2-1 추가된 섹션: "개발 환경 시작/중지 스크립트 (2026-01-22)"
- 스크립트 위치 및 용도 명시
- 사용법 및 실행 예제 제공
- 각 스크립트의 상세한 기능 설명

#### 2-2 수동 명령어 업데이트
- "전체 시작" 섹션 명명을 "전체 시작 (매칭 엔진 초기화 포함)"으로 변경
- scale-up Payload에 `matchingEngineInit` 파라미터 추가:
  ```json
  {
    "kafkaInstanceId": "i-044548ca3fe3ae1a1",
    "preloadTopic": "matching_engine_preload",
    "delaySeconds": 420
  }
  ```
- 새로운 섹션 "인프라만 시작 (매칭 엔진 초기화 없이)" 추가

#### 2-3 자동 스케줄링 정보 추가
- Lambda 스케줄러의 매칭 엔진 초기화 자동 수행 명시
- 매일 11:00 KST 스케일 업 시의 자동 프로세스 설명
- 배포 필요 명령어 추가: `cdk deploy Exchange-dev-EksScheduler -c env=dev`

#### 2-4 주의사항 추가
- 샤딩 모드 현재 비활성화 상태 명시
- 매칭 엔진 초기화 시 DB 데이터 미로드 정보
- 실제 운영 데이터 테스트 필요 시 Backend 콘솔 명령어 명시

### 3. TypeScript 파일 수정

#### 3-1 matching-engine.service.ts
- 부분 수정 (상세 내용 확인 필요)

#### 3-2 order-router.service.ts 및 shard-info.interface.ts
- 주문 라우팅 로직 일부 수정

### 4. 주요 변경사항 요약

| 항목 | 변경 내용 |
|------|---------|
| 생성 파일 수 | 3개 (자동화 스크립트) |
| 문서 업데이트 | CLAUDE.md 대폭 확장 |
| 코드 수정 | 3개 TypeScript 파일 (matching-engine, order-router 관련) |
| 추가된 문서 섹션 | 5개 |

### 5. 기술적 영향

- **개발자 경험 개선**: 매일 스케일 업/다운 시 수동 AWS CLI 명령어 입력 불필요
- **시간 절약**: 개발 환경 시작 시간을 자동화로 5-10분 소요
- **비용 절감**: 개발 환경 자동 스케일 다운으로 야간/주말 비용 절감
- **문서화 개선**: 개발자 온보딩 시 개발 환경 구성 방법 명확화

### 6. 배포 및 실행

```bash
cd future-backend

# 개발 환경 시작 (약 5-10분 소요)
./scripts/dev-environment-start.sh

# 상태 확인
./scripts/dev-environment-status.sh

# 개발 환경 종료
./scripts/dev-environment-stop.sh
```

### 7. 참고 사항

- **Lambda 스케줄러**: `exchange-dev-dev-scheduler` 함수 활용
- **Kafka 브로커**: 10.0.2.51:9092 (로컬 VPC 내)
- **Kubernetes 네임스페이스**:
  - `future-backend-dev` (백엔드)
  - `matching-engine-dev` (매칭 엔진)
- **최대 대기 시간**: 600초 (10분)
- **폴링 간격**: 10초

---

**작업 완료 시간**: 2026-01-23 22:15:00
**변경 파일**: 4개 (생성 3개, 수정 1개 + 업데이트 1개)
**상태**: 완료 ✅
