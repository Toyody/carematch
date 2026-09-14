import { apiRequest } from "@/lib/api/client";

export interface User {
  created_at: string;
  email: string;
  id: number;
  name: string;
}

interface UserResponse {
  data: User;
}

interface ForgotPasswordResponse {
  message: string;
}

export interface ForgotPasswordInput {
  email: string;
}

export interface RegisterInput {
  email: string;
  name: string;
  password: string;
  password_confirmation: string;
}

export interface LoginInput {
  email: string;
  password: string;
}

export interface ResetPasswordInput {
  email: string;
  password: string;
  password_confirmation: string;
  token: string;
}

export async function register(input: RegisterInput): Promise<User> {
  const response = await apiRequest<UserResponse>("/auth/register", {
    body: input,
    method: "POST",
    withCsrf: true,
  });

  return response.data;
}

export async function login(input: LoginInput): Promise<User> {
  const response = await apiRequest<UserResponse>("/auth/login", {
    body: input,
    method: "POST",
    withCsrf: true,
  });

  return response.data;
}

export async function getCurrentUser(): Promise<User> {
  const response = await apiRequest<UserResponse>("/auth/me");

  return response.data;
}

export async function logout(): Promise<void> {
  await apiRequest<void>("/auth/logout", {
    method: "POST",
    withCsrf: true,
  });
}

export function forgotPassword(
  input: ForgotPasswordInput,
): Promise<ForgotPasswordResponse> {
  return apiRequest<ForgotPasswordResponse>("/auth/forgot-password", {
    body: input,
    method: "POST",
    withCsrf: true,
  });
}

export function resetPassword(input: ResetPasswordInput): Promise<void> {
  return apiRequest<void>("/auth/reset-password", {
    body: input,
    method: "POST",
    withCsrf: true,
  });
}
