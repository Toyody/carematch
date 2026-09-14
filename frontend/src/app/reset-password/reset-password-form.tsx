"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { FormEvent, useState } from "react";

import { FormError } from "@/components/identity/form-error";
import { resetPassword } from "@/features/identity/api";

export function ResetPasswordForm() {
  const searchParams = useSearchParams();
  const token = searchParams.get("token") ?? "";
  const email = searchParams.get("email") ?? "";
  const [error, setError] = useState<unknown>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isSuccessful, setIsSuccessful] = useState(false);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(null);
    setIsSubmitting(true);
    const form = new FormData(event.currentTarget);

    try {
      await resetPassword({
        email,
        password: String(form.get("password") ?? ""),
        password_confirmation: String(form.get("password_confirmation") ?? ""),
        token,
      });
      setIsSuccessful(true);
    } catch (caughtError) {
      setError(caughtError);
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <main>
      <section className="foundation-card auth-card">
        <h1>Reset password</h1>
        {!token || !email ? (
          <p role="alert">This password reset link is incomplete.</p>
        ) : null}
        {isSuccessful ? (
          <div role="status">
            <p>Your password has been reset.</p>
            <Link href="/login">Continue to login</Link>
          </div>
        ) : (
          <form onSubmit={handleSubmit}>
            <label htmlFor="email">Email</label>
            <input id="email" readOnly type="email" value={email} />
            <label htmlFor="password">New password</label>
            <input
              autoComplete="new-password"
              id="password"
              minLength={12}
              name="password"
              required
              type="password"
            />
            <label htmlFor="password_confirmation">Confirm new password</label>
            <input
              autoComplete="new-password"
              id="password_confirmation"
              minLength={12}
              name="password_confirmation"
              required
              type="password"
            />
            <FormError error={error} />
            <button disabled={isSubmitting || !token || !email} type="submit">
              {isSubmitting ? "Resetting…" : "Reset password"}
            </button>
          </form>
        )}
      </section>
    </main>
  );
}
