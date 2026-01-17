export class UpdateEmailDto {
  id?: string;
  userId: number;
  email: string;
  confirmLink: string;
  oldEmail?: string;
  walletAddress?: string;
}
