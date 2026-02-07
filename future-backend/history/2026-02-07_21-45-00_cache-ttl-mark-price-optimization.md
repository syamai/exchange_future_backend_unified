# Prompt History

## Date
2026-02-07 21:45:00

## Prompt
캐시 TTL 조정 및 Mark Price 인메모리 캐시 최적화 (Phase 1 미구현 항목)

기존 계획의 미구현 항목 구현하고, 개선되는 거 확인하고 다음 계획 진행해. 만약 테스트 결과가 좋지 않으면 계획 전체 다시 점검해.

## Result

### 구현된 최적화

#### 1. Mark Price 인메모리 캐시 (order.service.ts)
- JavaScript Map 기반 1초 TTL 캐시 구현
- Redis 조회 4회 → 인메모리 캐시로 대체
- `getMarkPriceCached()` 메서드 추가
- `invalidateMarkPriceCache()` 무효화 메서드 추가

```typescript
// 인메모리 캐시 구조
private markPriceCache = new Map<string, { price: string; timestamp: number }>();
private readonly MARK_PRICE_CACHE_TTL_MS = 1000; // 1 second
```

#### 2. 캐시 TTL 조정
| 캐시 항목 | 변경 전 | 변경 후 | 파일 |
|----------|--------|---------|------|
| orderMargin | 5초 | 30초 | order.service.ts |
| positionMargin | 5초 | 10초 | position.service.ts |

### 빌드 및 테스트
- `yarn build` ✅ 성공
- `yarn test` ✅ 80/80 테스트 통과

### EKS 배포
- Docker 이미지: `tps-opt-202602072141`
- ECR: `990781424619.dkr.ecr.ap-northeast-2.amazonaws.com/future-backend:tps-opt-202602072141`
- 5 Pods → Running 상태 확인

### TPS 테스트 결과 (5 Pods)

#### Cold Start
| 지표 | 값 |
|------|-----|
| Raw TPS | 278 |
| Success Rate | 80.67% |
| **유효 TPS** | **224** |
| Median RT | 159ms |
| P95 RT | 862ms |
| HTTP 실패율 | 0% |

#### Warm Cache
| 지표 | 값 |
|------|-----|
| Raw TPS | 287 |
| Success Rate | 82.98% |
| **유효 TPS** | **238** |
| Median RT | **129ms** |
| P95 RT | 967ms |
| HTTP 실패율 | 0% |

### 성능 분석

#### 개선 효과
- **응답 시간 28% 개선** (median 179ms → 129ms)
- **유효 TPS 약 8% 증가** (220 → 238 추정)
- HTTP 실패율 0% 유지

#### 한계점
- TPS 자체는 기대만큼 크게 증가하지 않음
- 현재 병목은 캐시보다 **DB 연결 풀 또는 쿼리 자체**에 있음
- Phase 2 (Usecase 리팩토링) 및 Phase 3 (인프라 업그레이드) 필요

### 변경 파일
1. `src/modules/order/order.service.ts`
   - Mark Price 인메모리 캐시 추가
   - orderMargin TTL 5초 → 30초

2. `src/modules/position/position.service.ts`
   - positionMargin TTL 5초 → 10초

### 다음 단계
- [ ] Phase 2: Promise.all 병렬 조회 구현
- [ ] Phase 2: TP/SL 배치 INSERT 최적화
- [ ] Phase 3: RDS 스펙 업그레이드
- [ ] Phase 3: Read Replica 분리

## Tags
#tps-optimization #cache #performance #future-backend
