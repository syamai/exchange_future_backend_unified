import { Injectable } from "@nestjs/common";
import { UserService } from "src/modules/user/users.service";
import { FirebaseAdminService } from "../firebase-admin.service";
import { FireBaseNotiInterface } from "../firebase.console";

@Injectable()
export class FirebaseSendNotiUseCase {
  constructor(
    private readonly userService: UserService,

    private readonly firebaseAdminService: FirebaseAdminService
  ) {}

  async execute(msg: FireBaseNotiInterface) {
    try {
      const userId = msg.data.user_id;
      const user = await this.userService.findUserById(userId);
      const title = msg.data?.title;
      const body = msg.data?.content;
      if (!user || !user?.notification_token || !title || !body) {
        return;
      }

      this.firebaseAdminService.sendMessageToToken(user.notification_token, title, body, {
        userId: user.id ? String(user.id) : "",
        type: msg.type,
        detail: title,
      }).catch(e => {
        console.log(`[FirebaseSendNotiUseCase] Fail to send msg: ${e}`);
      });
    } catch (e) {
      console.log(`[FirebaseSendNotiUseCase]-error: ${e}`);
    }
  }
}
