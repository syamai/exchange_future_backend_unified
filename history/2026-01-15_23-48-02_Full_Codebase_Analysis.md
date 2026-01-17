# 전체 코드베이스 분석 및 CLAUDE.md 생성

## Date
2026-01-15 23:48:02

## Prompt
사용자 요청: 코드 분석하는 에이전트와 스킬을 이용해서 전체 코드 분석

## Result

### 1. 프로젝트 구조 파악
- **future-engine**: Java 17 기반 고성능 주문 매칭 엔진
  - 매칭 알고리즘
  - 마진 계산
  - 청산(Liquidation) 로직
  - 자동 청산(ADL) 처리

- **future-backend**: NestJS/TypeScript 기반 REST API & WebSocket 서버
  - 주문 처리 API
  - 실시간 업데이트 (WebSocket)
  - 데이터베이스 관리
  - Redis 캐시

### 2. 아키텍처 분석
```
Client → Backend (REST/WS) → Kafka → Engine (Matching) → Kafka → Backend → DB/Redis/WebSocket
```

**Kafka Topics**:
- `matching_engine_input`: 주문 및 취소 명령
- `matching_engine_output`: 체결 및 포지션 업데이트
- `save_order_from_client_v2`: 신규 주문
- `orderbook_output`: 오더북 업데이트

### 3. 핵심 도메인 모델 (future-engine)
- **MatchingEngine**: 메인 이벤트 루프, 명령 라우팅
- **Matcher**: 심볼별 주문 매칭 (TreeSet 오더북)
- **Trigger**: 스탑/TP/SL/트레일링 주문 트리거
- **LiquidationService**: 청산, ADL, 보험펀드
- **MarginCalculator**: PNL, 수수료 계산 (USD-M/COIN-M)
- **PositionCalculator**: 청산가격 계산

### 4. 기술 스택
**future-engine**:
- Java 17
- Maven
- Lombok
- Spring Boot
- Kafka

**future-backend**:
- Node.js
- NestJS
- TypeScript
- TypeORM
- PostgreSQL
- Redis
- Kafka

### 5. 생성된 산출물

#### CLAUDE.md 파일 생성
프로젝트 루트에 `CLAUDE.md` 파일 생성 완료:
- **Project Overview**: 두 컴포넌트 소개 및 Kafka 통신 구조
- **Build & Run Commands**: 전체 빌드, 테스트, 실행 명령어 정리
- **Architecture**: 데이터 흐름, Kafka 토픽, 핵심 모듈 위치
- **Coding Conventions**: Java/TypeScript 코딩 규칙
- **Domain Concepts**: 선물 거래 핵심 개념 정의
  - 마진 모드 (Isolated/Cross)
  - 계약 타입 (Perpetual/Quarterly)
  - 청산(Liquidation)과 자동청산(ADL)
  - 펀딩 수수료(Funding Fee)
- **Infrastructure**: 필요한 인프라 버전 정보

### 6. 주요 분석 결과

#### 선물 거래소 시스템 특징
1. **높은 처리량**: Java 매칭 엔진으로 밀리초 단위 체결
2. **비동기 처리**: Kafka를 통한 느슨한 결합 아키텍처
3. **안정성**: 청산(Liquidation), ADL, 보험펀드 메커니즘
4. **유연성**: USD-M, COIN-M 마진 모드 지원
5. **실시간 업데이트**: WebSocket을 통한 즉시 알림

#### 개발 환경
- 마이크로서비스 아키텍처로 독립적 배포 가능
- TypeORM 마이그레이션으로 데이터베이스 버전 관리
- 풍부한 CLI 명령어로 데이터 동기화 및 초기화 지원

### 7. 활용 방안
생성된 CLAUDE.md는 다음과 같이 활용 가능:
- 신규 개발자 온보딩 시 빠른 이해 제공
- 기능 개발 시 아키텍처 참고 자료
- 버그 수정 시 영향 범위 파악
- 마이크로서비스 간 통신 방식 이해

---

**작업 완료 시간**: 약 5분
**영향 범위**: 프로젝트 메타 정보 (코드 변경 없음, 문서 생성)
