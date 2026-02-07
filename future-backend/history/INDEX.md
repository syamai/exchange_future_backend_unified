# History Index

프로젝트의 주요 작업 기록을 시간 순서대로 정렬합니다.

## 작업 기록

### 2026-02-02

#### [2026-02-02_06-53-13] 매칭 엔진 및 주문 라우터 통합
- **상태**: ✅ 완료
- **파일**: `2026-02-02_06-53-13_Matching-Engine-And-OrderRouter-Integration.md`
- **작업 내용**:
  - 매칭 엔진 서비스 최적화 (Kafka 토픽 처리 개선)
  - 주문 라우터 서비스 구현 완료 (3-샤드 기반 라우팅)
  - Kafka Enum 확장 (13개 토픽 추가)
  - Kubernetes 배포 설정 개선
  - Spot-Backend 주문 처리 최적화
- **산출물**:
  - 수정: 11개 파일
  - 변경: 984개 라인 추가 + 340개 라인 제거
  - 영향: 매칭 엔진, 주문 라우팅, Kafka 인프라, K8s 배포
- **검증**:
  - ✅ 매칭 엔진 Kafka 통합 정상
  - ✅ 3-샤드 주문 라우팅 구현 완료
  - ✅ Kubernetes 배포 설정 최적화 완료
  - ✅ Future Event V2 Consumer Pod 정상 실행

### 2026-01-31

#### [2026-01-31_09-34-46] Future Event V2 AWS EKS 배포
- **상태**: ✅ 완료
- **파일**: `2026-01-31_09-34-46_Future-Event-V2-AWS-EKS-Deployment.md`
- **작업 내용**:
  - Future Event V2 Kafka Consumer Docker 이미지 빌드
  - AWS ECR에 이미지 푸시 (`event-v2` 태그)
  - Kubernetes Deployment YAML 생성 (`future-event-v2-consumers.yaml`)
  - AWS EKS 클러스터에 Deposit/Deduction Consumer 배포
  - Kafka Consumer Group 정상 연결 확인
- **산출물**:
  - Docker 이미지: `990781424619.dkr.ecr.ap-northeast-2.amazonaws.com/exchange/future-backend:event-v2`
  - 배포 파일: 4개 (Deployment YAML + Kustomization 수정)
  - 실행 상태: 2개 Consumer Pod 모두 Running
- **검증**:
  - ✅ Deposit Consumer: Kafka 토픽 구독 정상
  - ✅ Deduction Consumer: Kafka 토픽 구독 정상
  - ✅ 모든 NestJS 모듈 정상 로드
  - ✅ OrderRouter 정상 초기화 (3 shards, 6 symbol mappings)

### 2026-01-30

#### [2026-01-30_22-20-52] 세션 검증 및 히스토리 기록
- **상태**: ✅ 완료
- **파일**: `2026-01-30_22-20-52_Session-Verification-And-Checkpoints.md`
- **작업 내용**:
  - 현재 세션 상태 분석 및 검증
  - 이전 세션들의 작업 결과 확인
  - history 폴더 및 INDEX.md 검증
  - 미커밋 파일 상태 정리
- **결과**:
  - 이전 2개 세션의 작업 내용 정상 기록됨
  - Future Event V2 모듈: 16개 파일 생성, 테스트 19/19 통과
  - 현재 파일들은 아직 커밋 대기 중

#### [2026-01-30_15-14-58] Future Event V2 - 완전 구현 완료
- **상태**: ✅ 완료
- **파일**: `2026-01-30_15-14-58_Future-Event-V2-Complete-Implementation.md`
- **작업 내용**:
  - 마이그레이션 파일 생성 및 테이블 스키마 정의
  - Kafka 인프라 통합 (3개 토픽: deposit_approved, principal_deduction, liquidation_trigger)
  - 청산 로직 구현 (원금 <= 0 시 자동 청산)
  - 19개 단위 테스트 작성 및 모두 통과
  - 3개 개발 환경 스크립트 추가
- **산출물**:
  - 신규: 16개 파일 + 마이그레이션 + 테스트 + 스크립트
  - 수정: 3개 파일 (database-common.ts, modules.ts, kafka.enum.ts)
  - 테스트: 19/19 통과 (100%)
  - 빌드: ✅ 성공

#### [2026-01-30_14-41-00] Future Event V2 모듈 스켈레톤 구현
- **상태**: ✅ 완료
- **파일**: `2026-01-30_14-41-00_Future-Event-V2-Module-Skeleton-Implementation.md`
- **작업 내용**:
  - 16개 신규 파일 생성 (엔티티, Repository, DTO, Service, Controller, Enum 등)
  - 2개 기존 파일 수정 (modules.ts, database-common.ts)
  - TypeScript 타입 오류 수정
  - 빌드 성공 확인
- **생성 파일 요약**:
  - Entity: event-setting-v2, user-bonus-v2, user-bonus-v2-history
  - Repository: 위 3개 엔티티에 대한 repository
  - DTO: 4개 (create, update, grant, admin-query)
  - Enum: event-status-v2, bonus-status-v2
  - Service, Controller, Module: 기본 구현 완료

---

### 2026-02-07 (Latest Session)

#### [2026-02-07_21-45-00] 캐시 TTL + Mark Price 인메모리 최적화 **[최신]**
- **상태**: ✅ 완료
- **파일**: `2026-02-07_21-45-00_cache-ttl-mark-price-optimization.md`
- **작업 내용**:
  1. Mark Price 인메모리 캐시 구현 (1초 TTL, Redis 조회 4회 제거)
  2. orderMargin TTL 5초 → 30초 증가
  3. positionMargin TTL 5초 → 10초 증가
- **TPS 테스트 결과 (5 Pods)**:
  - Cold: 278 TPS (유효 224 TPS, 80.67%)
  - Warm: 287 TPS (유효 **238 TPS**, 82.98%)
  - **Median RT 28% 개선** (179ms → 129ms)
  - HTTP 실패율: 0%
- **수정 파일**:
  - `src/modules/order/order.service.ts`
  - `src/modules/position/position.service.ts`
- **검증**:
  - ✅ TypeScript 빌드 성공
  - ✅ 80개 테스트 통과
  - ✅ EKS 배포 완료 (tps-opt-202602072141)
  - ✅ TPS 테스트 완료

---

#### [2026-02-07_15-12-07] 병렬 쿼리 최적화 + 보안 수정 + TPS 테스트
- **상태**: ✅ 완료 (TPS 테스트 포함)
- **파일**: `2026-02-07_15-12-07_parallel-query-optimization.md`
- **작업 내용**:
  1. `getTradingRuleByInstrumentId`: tradingRule + instrument 병렬 조회
  2. `validateOrder`: markPrice 병렬 조회 추가 (Promise.all)
  3. `calcOrderCost`: markPrice 옵셔널 파라미터 추가 (Redis 중복 조회 제거)
  4. `validateMinMaxPrice`: 시그니처 변경 (3개 파라미터 추가로 중복 조회 제거)
- **보안 수정** (security-engineer 분석):
  - [HIGH] markPrice null/NaN 처리 (`??` → 명시적 체크)
  - [HIGH] markPrice 빈 문자열 처리 강화
  - [MEDIUM] instrument null 체크 추가
- **수정 파일**:
  - `src/modules/trading-rules/trading-rule.service.ts`
  - `src/modules/order/order.service.ts`
  - `src/modules/order/usecase/save-order-from-client-v2.usecase.ts`
- **TPS 테스트 결과**:
  - 5 Pods: Raw 463 TPS (유효 214 TPS, 46% 성공률)
  - 10 Pods: Raw 308 TPS (유효 **262 TPS**, **85% 성공률**)
  - 스케일업 효과: 유효 TPS +22%, HTTP 실패 50%→0%
- **검증**:
  - ✅ TypeScript 빌드 성공
  - ✅ 80개 테스트 통과
  - ✅ CI/CD 파이프라인 통과
  - ✅ EKS 배포 완료
  - ✅ 5 Pods / 10 Pods TPS 테스트 완료

---

#### [2026-02-07_11-44-46] 캐시 무효화 및 TPS 최적화
- **상태**: ✅ 완료
- **파일**: `2026-02-07_11-44-46_cache-invalidation-tps-optimization.md`
- **작업 내용**:
  1. calOrderMargin 캐싱 추가 (5초 TTL)
  2. 캐시 무효화 메서드 추가 (invalidateOrderMarginCache)
  3. Position 캐시 TTL 조정 (3초 → 5초)
  4. 복합 인덱스 2개 추가 (orders 테이블)
- **예상 효과**:
  - DB 쿼리 60-80% 감소
  - 쿼리 성능 90% 개선
  - TPS: 290 → 400-520 TPS (+38-80%)
- **검증**:
  - ✅ TypeScript 빌드 성공
  - ✅ refactoring-expert 에이전트 검증 완료
  - ⚠️ 인덱스 마이그레이션 필요

---

#### [2026-02-07_08-30-00] TPS 최적화 4단계 + 성능 테스트
- **상태**: ✅ 완료 (TPS 테스트 포함)
- **파일**: `2026-02-07_08-30-00_TPS-Optimization-4-Steps.md`
- **작업 내용**:
  1. lodash 타입 호환성 문제 해결 (`@types/lodash@4.14.191`)
  2. Kafka 샤딩 활성화 확인 (이미 enabled)
  3. calAvailableBalance 병렬화 (50-60% 응답 시간 개선)
  4. 계정 캐시 TTL 증가 (60초 → 300초)
  5. Kafka 파티션 수정 (1 → 10 파티션)
  6. Consumer 스케일 업 테스트 (병목 아님 확인)
- **TPS 테스트 결과**:
  - 최대 TPS: **~290 orders/sec**
  - 성공률: 82-85% (200 VU), 98% (50 VU)
  - Median RT: 112-177ms
- **병목 분석**:
  - Consumer 스케일 업 효과 없음 (병목 아님)
  - 실제 병목: RDS(db.t3.large) + Backend 5 Pods
- **검증**:
  - ✅ TypeScript 빌드 성공
  - ✅ TPS 테스트 완료 (~290 TPS)
  - ✅ Kafka 파티션 문제 해결

---

### 2026-02-02

#### [2026-02-02_06-53-13] Future Event V2 테스트 및 검증
- **상태**: ✅ 완료
- **파일**: `2026-02-02_06-53-13_Future-Event-V2-Testing-And-Verification.md`
- **작업 내용**:
  - Future Event V2 API 최종 테스트 및 검증
  - Matching Engine Kafka 토픽 처리 최적화
  - Order Router 구현 (3-Shard 기반 분산 라우팅)
  - Kafka Enum 확장 (13개 토픽 추가)
  - Kubernetes 배포 최적화 및 최종 검증
- **산출물**:
  - 수정: 11개 파일
  - 변경: 984개 라인 추가 + 340개 라인 제거
  - API 테스트: 3/3 PASS ✅
  - K8s 배포: 2개 Pod 정상 실행
- **검증**:
  - ✅ 모든 API 엔드포인트 정상
  - ✅ Matching Engine 통합 완료
  - ✅ Order Router 3-Shard 라우팅 동작 확인
  - ✅ K8s 배포 상태 정상
  - ✅ Future Event V2 시스템 운영 정상

---

## 폴더 구조

```
history/
├── INDEX.md (이 파일)
├── 2026-02-07_21-45-00_cache-ttl-mark-price-optimization.md ⭐ [최신]
├── 2026-02-07_15-12-07_parallel-query-optimization.md
├── 2026-02-07_11-44-46_cache-invalidation-tps-optimization.md
├── 2026-02-07_08-30-00_TPS-Optimization-4-Steps.md
├── 2026-02-02_06-53-13_Future-Event-V2-Testing-And-Verification.md
├── 2026-02-02_06-53-13_Matching-Engine-And-OrderRouter-Integration.md
├── 2026-01-31_09-34-46_Future-Event-V2-AWS-EKS-Deployment.md
├── 2026-01-30_22-20-52_Session-Verification-And-Checkpoints.md
├── 2026-01-30_15-14-58_Future-Event-V2-Complete-Implementation.md
├── 2026-01-30_14-41-00_Future-Event-V2-Module-Skeleton-Implementation.md
└── [앞으로 추가될 작업 기록들...]
```

## 기록 작성 규칙

- **파일명**: `YYYY-MM-DD_HH-mm-ss_[작업요약].md` 형식
- **섹션**: Date, Prompt, Result 포함
- **대상**: 코드 변경, 파일 생성 등 의미있는 작업만 기록
- **제외**: 단순 질문, 정보 요청은 기록하지 않음

