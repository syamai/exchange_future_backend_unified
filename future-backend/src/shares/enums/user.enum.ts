import { enumize } from "src/shares/enums/enumize";

export const UserStatus = enumize("ACTIVE", "DEACTIVE");

export const UserRole = enumize("USER", "ADMIN", "SUPER_ADMIN");

export const UserType = enumize("RESTRICTED", "UNRESTRICTED");

export const UserIsLocked = enumize("LOCKED", "UNLOCKED");

export const UserMailStatus = enumize("NONE", "VERIFIED");
