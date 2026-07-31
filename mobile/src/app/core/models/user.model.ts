export type UserType = 'personal' | 'professional';

export interface User {
  id: number;
  email: string;
  firstName: string;
  lastName: string;
  fullName: string;
  type: UserType;
  avatar?: string;
  roles: string[];
  createdAt: string;
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
