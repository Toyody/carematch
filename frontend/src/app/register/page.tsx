"use client";

import Link from "next/link";
import { FormEvent, useState } from "react";

import { FormError } from "@/components/forms/form-error";
import { useAuth } from "@/features/identity/auth-context";

export default function RegisterPage() {
  const { register, user } = useAuth();
  const [error, setError] = useState<unknown>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(null);
    setIsSubmitting(true);
    const form = new FormData(event.currentTarget);

    try {
      await register({
        email: String(form.get("email") ?? ""),
        name: String(form.get("name") ?? ""),
        password: String(form.get("password") ?? ""),
        password_confirmation: String(form.get("password_confirmation") ?? ""),
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
        <h1>Register</h1>
        {user ? (
          <div role="status">
            <p>Signed in as {user.email}.</p>
            <Link href="/">Continue to CareMatch</Link>
          </div>
        ) : (
          <form onSubmit={handleSubmit}>
            <label htmlFor="name">Name</label>
            <input autoComplete="name" id="name" name="name" required />
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
              autoComplete="new-password"
              id="password"
              minLength={12}
              name="password"
              required
              type="password"
            />
            <label htmlFor="password_confirmation">Confirm password</label>
            <input
              autoComplete="new-password"
              id="password_confirmation"
              minLength={12}
              name="password_confirmation"
              required
              type="password"
            />
            <FormError error={error} />
            <button disabled={isSubmitting} type="submit">
              {isSubmitting ? "Registering…" : "Register"}
            </button>
          </form>
        )}
        <p>
          Already registered? <Link href="/login">Login</Link>
        </p>
      </section>
    </main>
  );
}
