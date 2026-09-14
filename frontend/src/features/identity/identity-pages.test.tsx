import {
  cleanup,
  fireEvent,
  render,
  screen,
  waitFor,
} from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

import ForgotPasswordPage from "@/app/forgot-password/page";
import LoginPage from "@/app/login/page";
import RegisterPage from "@/app/register/page";
import { ResetPasswordForm } from "@/app/reset-password/reset-password-form";
import { ApiError } from "@/lib/api/client";

import {
  forgotPassword,
  getCurrentUser,
  login,
  register,
  resetPassword,
  type User,
} from "./api";
import { AuthProvider } from "./auth-context";

let resetQuery = new URLSearchParams();

vi.mock("next/navigation", () => ({
  useSearchParams: () => resetQuery,
}));

vi.mock("./api", () => ({
  forgotPassword: vi.fn(),
  getCurrentUser: vi.fn(),
  login: vi.fn(),
  logout: vi.fn(),
  register: vi.fn(),
  resetPassword: vi.fn(),
}));

const user: User = {
  created_at: "2026-08-30T00:00:00Z",
  email: "person@example.com",
  id: 7,
  name: "Care Match",
};

function renderWithAuth(page: React.ReactNode) {
  return render(<AuthProvider>{page}</AuthProvider>);
}

function fill(label: string, value: string) {
  fireEvent.change(screen.getByLabelText(label), { target: { value } });
}

describe("Identity pages", () => {
  beforeEach(() => {
    vi.mocked(getCurrentUser).mockRejectedValue(
      new ApiError(401, "Unauthenticated."),
    );
  });

  afterEach(() => {
    cleanup();
    vi.clearAllMocks();
  });

  it("registers and reflects the authenticated user", async () => {
    vi.mocked(register).mockResolvedValue(user);
    renderWithAuth(<RegisterPage />);

    fill("Name", user.name);
    fill("Email", user.email);
    fill("Password", "long-enough-password");
    fill("Confirm password", "long-enough-password");
    fireEvent.click(screen.getByRole("button", { name: "Register" }));

    expect(
      await screen.findByText(`Signed in as ${user.email}.`),
    ).toBeInTheDocument();
    expect(register).toHaveBeenCalledWith({
      email: user.email,
      name: user.name,
      password: "long-enough-password",
      password_confirmation: "long-enough-password",
    });
  });

  it("shows registration validation errors", async () => {
    vi.mocked(register).mockRejectedValue(
      new ApiError(422, "The given data was invalid.", {
        email: ["The email is invalid."],
      }),
    );
    renderWithAuth(<RegisterPage />);

    fill("Name", user.name);
    fill("Email", user.email);
    fill("Password", "long-enough-password");
    fill("Confirm password", "long-enough-password");
    fireEvent.click(screen.getByRole("button", { name: "Register" }));

    expect(
      await screen.findByText("The email is invalid."),
    ).toBeInTheDocument();
  });

  it("logs in and reflects the authenticated user", async () => {
    vi.mocked(login).mockResolvedValue(user);
    renderWithAuth(<LoginPage />);

    fill("Email", user.email);
    fill("Password", "long-enough-password");
    fireEvent.click(screen.getByRole("button", { name: "Login" }));

    expect(
      await screen.findByText(`Signed in as ${user.email}.`),
    ).toBeInTheDocument();
  });

  it.each([
    [401, "The provided credentials are incorrect."],
    [409, "An authenticated user cannot perform this operation."],
  ])("shows the safe login error for status %s", async (status, message) => {
    vi.mocked(login).mockRejectedValue(new ApiError(status, message));
    renderWithAuth(<LoginPage />);

    fill("Email", user.email);
    fill("Password", "incorrect-password");
    fireEvent.click(screen.getByRole("button", { name: "Login" }));

    expect(await screen.findByText(message)).toBeInTheDocument();
  });

  it("shows the generic accepted forgot-password response", async () => {
    const message =
      "If an account exists for that email, a password reset link will be sent.";
    vi.mocked(forgotPassword).mockResolvedValue({ message });
    render(<ForgotPasswordPage />);

    fill("Email", user.email);
    fireEvent.click(screen.getByRole("button", { name: "Send reset link" }));

    expect(await screen.findByText(message)).toBeInTheDocument();
    expect(forgotPassword).toHaveBeenCalledWith({ email: user.email });
  });

  it("extracts the reset query and shows successful reset behaviour", async () => {
    resetQuery = new URLSearchParams(
      "token=reset-token&email=person%40example.com",
    );
    vi.mocked(resetPassword).mockResolvedValue(undefined);
    render(<ResetPasswordForm />);

    expect(screen.getByDisplayValue(user.email)).toBeInTheDocument();
    fill("New password", "replacement-password");
    fill("Confirm new password", "replacement-password");
    fireEvent.click(screen.getByRole("button", { name: "Reset password" }));

    expect(
      await screen.findByText("Your password has been reset."),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("link", { name: "Continue to login" }),
    ).toHaveAttribute("href", "/login");
    expect(resetPassword).toHaveBeenCalledWith({
      email: user.email,
      password: "replacement-password",
      password_confirmation: "replacement-password",
      token: "reset-token",
    });
  });

  it("shows a safe reset token or validation error", async () => {
    resetQuery = new URLSearchParams(
      "token=used-token&email=person%40example.com",
    );
    vi.mocked(resetPassword).mockRejectedValue(
      new ApiError(422, "The password reset token is invalid or has expired.", {
        token: ["The password reset token is invalid or has expired."],
      }),
    );
    render(<ResetPasswordForm />);

    fill("New password", "replacement-password");
    fill("Confirm new password", "replacement-password");
    fireEvent.click(screen.getByRole("button", { name: "Reset password" }));

    await waitFor(() => {
      expect(
        screen.getAllByText(
          "The password reset token is invalid or has expired.",
        ),
      ).toHaveLength(2);
    });
  });
});
