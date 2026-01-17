# Exchange System Documentation

암호화폐 선물 거래소(Futures Exchange) 시스템 기술 문서

## 시스템 개요

이 프로젝트는 고성능 암호화폐 선물 거래소 시스템으로, 두 개의 핵심 컴포넌트로 구성됩니다.

| 모듈 | 기술 스택 | 역할 |
|------|-----------|------|
| **future-engine** | Java 17 + Kafka | 고성능 주문 매칭 엔진 |
| **future-backend** | NestJS + TypeScript | REST API & WebSocket 서버 |

## 주요 기능

### 거래 기능
- **주문 유형**: Limit, Market, Stop-Limit, Stop-Market, Trailing Stop
- **마진 모드**: Cross (교차), Isolated (격리)
- **계약 타입**: USD-M (USDT 정산), COIN-M (코인 정산)
- **Time-in-Force**: GTC, IOC, FOK
- **TP/SL**: Take Profit, Stop Loss 자동 주문

### 리스크 관리
- 청산(Liquidation) 시스템
- Auto-Deleveraging (ADL)
- 보험 펀드(Insurance Fund)
- 레버리지 관리 (최대 125x)

### 펀딩 시스템
- 8시간 주기 펀딩 수수료
- Long/Short 포지션 간 자금 조달

## 문서 구조

| 문서 | 설명 |
|------|------|
| [Architecture](./architecture.md) | 시스템 아키텍처 및 데이터 흐름 |
| [Future Engine](./future-engine.md) | 매칭 엔진 상세 분석 |
| [Future Backend](./future-backend.md) | 백엔드 API 서버 상세 분석 |

## 기술 스택 요약

### Future Engine (Java)
- Java 17
- Apache Kafka 3.4
- Gson, Guava
- SLF4J + Log4j2
- Maven

### Future Backend (TypeScript)
- Node.js 14.x
- NestJS 7.x
- TypeORM + MySQL
- Redis + IORedis
- KafkaJS
- Socket.io

## 시스템 요구사항

### 인프라
- MySQL 8.x (Master/Report 분리 권장)
- Redis 6.x
- Apache Kafka 3.x
- Zookeeper (Kafka 의존)

### 환경 변수
각 프로젝트의 `.env.example` 파일 참조

## Quick Start

### Future Engine 실행
```bash
cd future-engine
mvn clean package
java -jar target/matching-engine.jar
```

### Future Backend 실행
```bash
cd future-backend
yarn install
yarn start:dev

# 매칭 엔진 초기화
yarn console:dev matching-engine:load
```

## 운영 명령어

```bash
# 계정 저장 워커
yarn console:dev matching-engine:save-accounts-to-db

# 포지션 저장 워커
yarn console:dev matching-engine:save-positions

# 주문 저장 워커
yarn console:dev matching-engine:save-orders-to-db

# 체결 저장 워커
yarn console:dev matching-engine:save-trades

# 실시간 알림 워커
yarn console:dev matching-engine:notify

# 펀딩 지급
yarn console:dev funding:pay
```

## 연락처

프로젝트 관련 문의는 개발팀에 연락하세요.
