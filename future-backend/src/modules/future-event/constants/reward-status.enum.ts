export enum RewardStatus {
  PENDING = 'PENDING',     // (Optional) Reward created but not usable yet
  IN_USE = 'IN_USE',       // Reward is issued and currently usable
  REVOKING = 'REVOKING',   // Revocation process has started
  REVOKED = 'REVOKED',     // Reward has been fully revoked
  FULLY_USED = 'FULLY_USED' // Reward has been fully used
}
