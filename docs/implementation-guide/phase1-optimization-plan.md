# Phase 1 코드 최적화 플랜

## 목표
현재 코드 기준 5K-10K TPS → **20K-30K TPS**로 향상 (코드 변경만으로, 인프라 변경 없음)

## 예상 효과
| 변경 항목 | 현재 값 | 변경 값 | 예상 효과 |
|----------|--------|--------|----------|
| OUTPUT_BATCH_SIZE | 1 | 100 | 처리량 5-10x 향상 |
| Kafka poll timeout | 100ms | 50ms | 지연 50% 감소 |
| JVM 설정 일관화 | 불일치 | 통일 | 안정성 향상 |

---

## 변경 사항

### 1. OUTPUT_BATCH_SIZE 변경

**파일:** `future-engine/src/main/java/com/sotatek/future/engine/MatchingEngineConfig.java`
- **Line 17:** `OUTPUT_BATCH_SIZE = 1` → `OUTPUT_BATCH_SIZE = 100`

**영향 범위:**
- `JsonOutputStream.java` - 배치 처리 로직
- `KafkaOutputStream.java` - Kafka 메시지 전송
- Backend consumer - JSON 배열 파싱 (이미 지원됨)

**버그 수정 필요:**
- `JsonOutputStream.java:99` - `dataList.size() <= batchSize` → `dataList.size() < batchSize`
  - 현재 조건문이 batchSize+1개까지 수집하는 버그 존재

---

### 2. Kafka Poll Timeout 변경

**파일:** `future-engine/src/main/java/com/sotatek/future/input/KafkaInputStream.java`
- **Line 75:** `poll(Duration.ofMillis(100))` → `poll(Duration.ofMillis(50))`

**권장 사유:**
- 10ms는 CPU 오버헤드 10배 증가 위험
- 50ms가 지연/리소스 균형점

**추가 설정 (선택):**
```java
props.put(ConsumerConfig.FETCH_MAX_WAIT_MS_CONFIG, "50");
props.put(ConsumerConfig.MAX_POLL_RECORDS_CONFIG, "500");
```

---

### 3. JVM 설정 일관화

**현재 불일치:**
| 파일 | 힙 크기 | GC |
|------|--------|-----|
| DockerfileMe.dev | 5GB | ZGC |
| DockerfileMe.prod | 12GB | ZGC |
| entry.sh | 6GB | 미설정 |

**변경할 파일들:**

#### 3.1 entry.sh
```bash
# Before
java -Xmx6144M -cp MatchingEngine-1.0-shaded.jar ...

# After
java -Xms6g -Xmx6g \
  -XX:+UseZGC \
  -XX:ParallelGCThreads=4 \
  -XX:ConcGCThreads=8 \
  -Xlog:gc*:file=gc.log:time,uptime,level,tags \
  -cp MatchingEngine-1.0-shaded.jar ...
```

#### 3.2 DockerfileMe.dev (선택적 최적화)
```dockerfile
# 추가 권장 플래그
-XX:+AlwaysPreTouch \
-XX:+UseNUMA
```

---

## 수정 파일 목록

| 파일 | 변경 내용 | 위험도 |
|------|----------|--------|
| `MatchingEngineConfig.java:17` | BATCH_SIZE 1→100 | 낮음 |
| `JsonOutputStream.java:99` | 버그 수정 (<=→<) | 낮음 |
| `KafkaInputStream.java:75` | poll timeout 100→50ms | 낮음 |
| `entry.sh` | JVM 플래그 추가 | 낮음 |

---

## 테스트 계획

### 1. 단위 테스트
```bash
cd future-engine
mvn clean verify
```

### 2. 통합 테스트
- 로컬 Docker 환경에서 매칭 엔진 실행
- 주문 100건 연속 처리 테스트
- Consumer lag 모니터링

### 3. 성능 검증
- Before/After TPS 측정
- GC 로그 분석
- 메모리 사용량 확인

---

## 롤백 계획

변경사항이 단순 설정값 변경이므로:
1. Git revert로 즉시 롤백 가능
2. 배치 크기만 1로 되돌리면 원복

---

## 실행 순서

1. [ ] `MatchingEngineConfig.java` - BATCH_SIZE 변경
2. [ ] `JsonOutputStream.java` - 버그 수정
3. [ ] `KafkaInputStream.java` - poll timeout 변경
4. [ ] `entry.sh` - JVM 설정 일관화
5. [ ] 단위 테스트 실행
6. [ ] 로컬 통합 테스트

---

## 확정된 설정값

| 항목 | 값 | 비고 |
|------|-----|------|
| OUTPUT_BATCH_SIZE | **100** | 권장값 채택 |
| Kafka poll timeout | **50ms** | 권장값 채택 |
| JVM 힙 크기 | **6GB** | 현재값 유지 |

---

## 최종 변경 파일 요약

```
future-engine/
├── src/main/java/com/sotatek/future/
│   ├── engine/MatchingEngineConfig.java    # Line 17: BATCH_SIZE 1→100
│   ├── input/KafkaInputStream.java         # Line 75: poll 100→50ms
│   └── output/JsonOutputStream.java        # Line 99: 버그 수정 (<=→<)
└── entry.sh                                 # JVM 플래그 추가 (ZGC, GC logging)
```
