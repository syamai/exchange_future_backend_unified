import { Command, Console } from "nestjs-console";
import { AuthService } from "src/modules/auth/auth.service";

@Console()
export class AuthConsole {
  constructor(private readonly authService: AuthService) {}

  @Command({
    command: "auth:generate-test-token <userId>",
    description: "Generate a test JWT token for TPS testing",
  })
  async generateTestToken(userId: string): Promise<void> {
    const userIdNum = parseInt(userId, 10);
    if (isNaN(userIdNum)) {
      console.error("Error: userId must be a number");
      return;
    }

    const payload = { sub: userIdNum };
    const result = this.authService.generateAccessToken(payload);

    console.log("\n╔════════════════════════════════════════════════════════════════╗");
    console.log("║              TEST JWT TOKEN GENERATED                          ║");
    console.log("╠════════════════════════════════════════════════════════════════╣");
    console.log(`║  User ID: ${userIdNum}`);
    console.log("╠════════════════════════════════════════════════════════════════╣");
    console.log("║  Access Token:");
    console.log("╚════════════════════════════════════════════════════════════════╝");
    console.log(result.accessToken);
    console.log("\n");
    console.log("Usage with k6:");
    console.log(`  k6 run -e TOKEN=${result.accessToken} test/performance/order-tps-test.js`);
    console.log("\n");
  }
}
