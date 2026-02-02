# Kafka Infrastructure 및 배포 가이드 작성

**Date**: 2026-01-30
**Time**: 13:23:09

---

## Prompt (사용자 요청)

future-event-server 관련 코드 확인 및 분석

---

## Result (수행 결과)

### 작업 요약

본 세션에서는 exchange 프로젝트의 future-backend WebSocket 이벤트 시스템 분석과 AWS Kafka 인프라(Redpanda) 관련 문서 작성을 수행했습니다.

### 분석 내용

#### 1. Future-Event-Server (WebSocket 이벤트 시스템) 분석

**위치**: `future-backend/src/modules/events/`

**핵심 컴포넌트**:

- **EventGateway** (`event.gateway.ts`)
  - NestJS WebSocket Gateway 구현
  - Socket.IO 기반 양방향 통신
  - JWT 토큰 기반 인증
  - 클라이언트 연결/해제 처리
  - 동적 Room 기반 메시지 라우팅 (join/leave 이벤트)

- **EventModule** (`event.module.ts`)
  - EventGateway 제공자로 등록
  - JWT 모듈 통합
  - HealthService 의존성 주입

**인증 메커니즘**:
- Query 파라미터로 JWT 토큰 전달
- RS256 서명 검증
- Token에서 사용자 ID(sub) 추출하여 Room 자동 조인
- 인증 실패 시 Guest로 접속 허용

**Room 기반 메시징**:
- 사용자별 Private Room: 사용자 ID로 room name 사용
- 동적 Room 관리: 클라이언트의 join/leave 이벤트로 제어
- 브로드캐스팅: 특정 Room의 모든 클라이언트에게 메시지 전송 가능

#### 2. AWS Kafka 인프라 정보 추가

**CLAUDE.md 파일 확장** (59줄 추가):

AWS 계정 정보 및 exchange-cicd-dev 인프라의 Kafka(Redpanda) 서버 구성 정보를 문서화했습니다.

**추가 내용**:

```markdown
## AWS 계정 정보
- Account: 990781424619 (critonex)
- User: Prod-ahn
- Region: ap-northeast-2 (서울)

## exchange-cicd-dev Kafka (Redpanda) 인프라

### EC2 Instance 정보
- Instance ID: i-06b94401e85fad898
- Private IP: 172.31.13.13
- Public IP: 52.78.109.192
- Type: t3.medium
- VPC: vpc-0bd37d37ac2f47d7f (172.31.0.0/16)
- Security Group: sg-042f12df0c594b833

### 접속 및 관리 방법
- AWS EC2 Instance Connect 사용
- SSH 키 푸시 및 60초 내 SSH 접속
- Redpanda CLI (rpk)로 상태 확인

### Kubernetes Secrets 관리
- dev-matching-engine-secrets의 KAFKA_BOOTSTRAP_SERVERS 설정
- kubectl을 통한 secret 패치 및 pod 재시작
```

### 주요 변경사항

| 파일명 | 변경 유형 | 내용 |
|--------|----------|------|
| CLAUDE.md | 추가 | AWS 계정 정보 및 Kafka 인프라 가이드 (59줄) |
| history/INDEX.md | 수정 | 현재 세션 기록 추가 예정 |

### 인사이트

1. **Event-Server 아키텍처의 장점**:
   - JWT 기반 보안 인증
   - Socket.IO의 자동 재연결 및 안정성
   - Room 기반 유연한 메시징 모델
   - 수평 확장을 위한 Redis adapter 활용 가능

2. **Kafka 인프라 관리**:
   - EC2 Instance Connect로 접속 권한 관리 (60초 임시 SSH 키)
   - Kubernetes secret으로 중앙화된 설정 관리
   - Matching Engine과의 느슨한 결합을 위한 Kafka 메시지 브로커 활용

### 결과 평가

✅ **완료 사항**:
- WebSocket 이벤트 시스템 구조 분석 완료
- AWS Kafka 인프라 정보 문서화 완료
- 배포 및 관리 가이드라인 작성 완료

### 소요 시간

약 0.5시간

---

**마지막 업데이트**: 2026-01-30 13:23:09
