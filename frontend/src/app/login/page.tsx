"use client";

import Link from "next/link";
import { FormEvent, useState } from "react";

import { FormError } from "@/components/forms/form-error";
import { useAuth } from "@/features/identity/auth-context";

export default function LoginPage() {
  const { login, user } = useAuth();
  const [error, setError] = useState<unknown>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(null);
    setIsSubmitting(true);
    const form = new FormData(event.currentTarget);

    try {
      await login({
        email: String(form.get("email") ?? ""),
        password: String(form.get("password") ?? ""),
      });
    } catch (caughtError) {
      setError(caughtError);
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <main>
      <section className="foundation-card auth-card">
        <h1>Login</h1>
        {user ? (
          <div role="status">
            <p>Signed in as {user.email}.</p>
            <Link href="/">Continue to CareMatch</Link>
          </div>
        ) : (
          <form onSubmit={handleSubmit}>
            <label htmlFor="email">Email</label>
            <input
              autoComplete="email"
              id="email"
              name="email"
              required
              type="email"
            />
            <label htmlFor="password">Password</label>
            <input
              autoComplete="current-password"
              id="password"
              name="password"
              required
              type="password"
            />
            <FormError error={error} />
            <button disabled={isSubmitting} type="submit">
              {isSubmitting ? "Logging in…" : "Login"}
            </button>
          </form>
        )}
        <p>
          <Link href="/forgot-password">Forgot your password?</Link>
        </p>
        <p>
          Need an account? <Link href="/register">Register</Link>
        </p>
      </section>
    </main>
  );
}
