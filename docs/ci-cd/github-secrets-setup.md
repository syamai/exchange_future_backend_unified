# GitHub Secrets 설정 가이드

CI/CD 파이프라인에 필요한 GitHub Secrets 설정 방법입니다.

## 필요한 Secrets 목록

| Secret 이름 | 설명 | 예시 |
|-------------|------|------|
| `AWS_ACCESS_KEY_ID` | AWS IAM 액세스 키 | `AKIAIOSFODNN7EXAMPLE` |
| `AWS_SECRET_ACCESS_KEY` | AWS IAM 시크릿 키 | `wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY` |
| `AWS_ACCOUNT_ID` | AWS 계정 ID | `233244340438` |
| `EKS_CLUSTER_NAME` | Dev EKS 클러스터 이름 | `exchange-dev` |
| `EKS_CLUSTER_NAME_PROD` | Prod EKS 클러스터 이름 | `exchange-prod` |

## 설정 방법

### 1. GitHub 저장소에서 Secrets 설정

1. GitHub 저장소 페이지로 이동
2. **Settings** 탭 클릭
3. 좌측 메뉴에서 **Secrets and variables** → **Actions** 클릭
4. **New repository secret** 클릭
5. Name과 Secret 입력 후 **Add secret** 클릭

### 2. AWS IAM 사용자 생성

CI/CD용 IAM 사용자를 생성하고 필요한 권한을 부여합니다.

```bash
# IAM 사용자 생성
aws iam create-user --user-name github-actions-cicd

# 액세스 키 생성
aws iam create-access-key --user-name github-actions-cicd
```

### 3. IAM 정책 생성 및 연결

CI/CD에 필요한 최소 권한 정책:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "ECRAccess",
      "Effect": "Allow",
      "Action": [
        "ecr:GetAuthorizationToken",
        "ecr:BatchCheckLayerAvailability",
        "ecr:GetDownloadUrlForLayer",
        "ecr:GetRepositoryPolicy",
        "ecr:DescribeRepositories",
        "ecr:ListImages",
        "ecr:DescribeImages",
        "ecr:BatchGetImage",
        "ecr:InitiateLayerUpload",
        "ecr:UploadLayerPart",
        "ecr:CompleteLayerUpload",
        "ecr:PutImage"
      ],
      "Resource": [
        "arn:aws:ecr:ap-northeast-2:*:repository/exchange/*"
      ]
    },
    {
      "Sid": "ECRAuth",
      "Effect": "Allow",
      "Action": [
        "ecr:GetAuthorizationToken"
      ],
      "Resource": "*"
    },
    {
      "Sid": "EKSAccess",
      "Effect": "Allow",
      "Action": [
        "eks:DescribeCluster",
        "eks:ListClusters"
      ],
      "Resource": [
        "arn:aws:eks:ap-northeast-2:*:cluster/exchange-*"
      ]
    },
    {
      "Sid": "STSAccess",
      "Effect": "Allow",
      "Action": [
        "sts:GetCallerIdentity"
      ],
      "Resource": "*"
    }
  ]
}
```

정책 생성 및 연결:

```bash
# 정책 생성
aws iam create-policy \
  --policy-name GitHubActionsCICDPolicy \
  --policy-document file://github-actions-policy.json

# 정책 연결
aws iam attach-user-policy \
  --user-name github-actions-cicd \
  --policy-arn arn:aws:iam::<ACCOUNT_ID>:policy/GitHubActionsCICDPolicy
```

### 4. EKS 클러스터 접근 권한 설정

IAM 사용자가 EKS 클러스터에 접근할 수 있도록 aws-auth ConfigMap을 업데이트합니다.

```bash
# 현재 aws-auth ConfigMap 확인
kubectl get configmap aws-auth -n kube-system -o yaml
```

aws-auth ConfigMap에 IAM 사용자 추가:

```yaml
apiVersion: v1
kind: ConfigMap
metadata:
  name: aws-auth
  namespace: kube-system
data:
  mapUsers: |
    - userarn: arn:aws:iam::<ACCOUNT_ID>:user/github-actions-cicd
      username: github-actions-cicd
      groups:
        - system:masters
```

또는 eksctl 사용:

```bash
eksctl create iamidentitymapping \
  --cluster exchange-dev \
  --arn arn:aws:iam::<ACCOUNT_ID>:user/github-actions-cicd \
  --username github-actions-cicd \
  --group system:masters
```

### 5. GitHub Environments 설정 (선택사항)

프로덕션 배포에 승인 절차를 추가하려면:

1. GitHub 저장소 → **Settings** → **Environments**
2. **New environment** 클릭
3. 이름: `production` 입력
4. **Configure environment** 클릭
5. **Required reviewers** 체크
6. 승인자 추가
7. **Save protection rules** 클릭

## Secret 값 확인

### AWS 계정 ID 확인

```bash
aws sts get-caller-identity --query Account --output text
```

### EKS 클러스터 이름 확인

```bash
aws eks list-clusters --region ap-northeast-2 --query 'clusters[]' --output table
```

### ECR 저장소 URL 확인

```bash
aws ecr describe-repositories --region ap-northeast-2 \
  --query 'repositories[*].[repositoryName,repositoryUri]' \
  --output table
```

## 테스트

Secrets 설정 후 워크플로우를 수동으로 실행하여 테스트합니다.

```bash
# GitHub CLI로 워크플로우 실행
gh workflow run spot-backend.yml --ref main

# 실행 상태 확인
gh run list --workflow=spot-backend.yml
```

## 보안 권장사항

1. **최소 권한 원칙**: CI/CD에 필요한 최소한의 권한만 부여
2. **키 로테이션**: 정기적으로 액세스 키 교체 (90일 권장)
3. **감사 로깅**: CloudTrail로 API 호출 기록
4. **IP 제한**: 가능하면 GitHub Actions IP 범위로 제한

### GitHub Actions IP 범위 조회

```bash
curl -s https://api.github.com/meta | jq '.actions'
```

## 트러블슈팅

### AWS 자격 증명 오류

```
Error: The security token included in the request is invalid.
```

→ AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY 값 확인

### ECR 로그인 실패

```
Error: Cannot perform an interactive login from a non TTY device
```

→ `aws-actions/amazon-ecr-login@v2` 액션 사용 확인

### EKS 클러스터 접근 불가

```
error: You must be logged in to the server (Unauthorized)
```

→ aws-auth ConfigMap에 IAM 사용자 추가 필요

### 권한 부족

```
User: arn:aws:iam::xxx:user/github-actions-cicd is not authorized to perform: ecr:PutImage
```

→ IAM 정책에 해당 권한 추가 필요

## 관련 문서

- [CI/CD 개요](./README.md)
- [AWS IAM 모범 사례](https://docs.aws.amazon.com/IAM/latest/UserGuide/best-practices.html)
- [GitHub Actions 보안 강화](https://docs.github.com/en/actions/security-guides/security-hardening-for-github-actions)
