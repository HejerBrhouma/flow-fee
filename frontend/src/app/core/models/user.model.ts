export type UserType = 'personal' | 'professional';

export interface User {
  id: number;
  email: string;
  firstName: string;
  lastName: string;
  fullName: string;
  type: UserType;
  avatarUrl?: string | null;
  phone?: string;
  preferredCurrency?: string | null;
  roles: string[];
  twoFactorEnabled?: boolean;
  createdAt: string;
}

export type LoginResult =
  | { requiresTwoFactor: false; user: User }
  | { requiresTwoFactor: true; challengeToken: string };

export interface UpdateProfilePayload {
  firstName: string;
  lastName: string;
  phone?: string;
  preferredCurrency?: string;
}

export interface ChangePasswordPayload {
  currentPassword: string;
  newPassword: string;
}

export interface AuthResponse {
  token: string;
  user: User;
}

export interface RegisterPayload {
  email: string;
  password: string;
  firstName: string;
  lastName: string;
  type: UserType;
}

export interface LoginPayload {
  email: string;
  password: string;
}
