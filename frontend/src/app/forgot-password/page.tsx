"use client";

import Link from "next/link";
import { FormEvent, useState } from "react";

import { FormError } from "@/components/identity/form-error";
import { forgotPassword } from "@/features/identity/api";

export default function ForgotPasswordPage() {
  const [error, setError] = useState<unknown>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [message, setMessage] = useState<string | null>(null);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(null);
    setMessage(null);
    setIsSubmitting(true);
    const form = new FormData(event.currentTarget);

    try {
      const response = await forgotPassword({
        email: String(form.get("email") ?? ""),
      });
      setMessage(response.message);
    } catch (caughtError) {
      setError(caughtError);
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <main>
      <section className="foundation-card auth-card">
        <h1>Forgot password</h1>
        {message ? (
          <p role="status">{message}</p>
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
            <FormError error={error} />
            <button disabled={isSubmitting} type="submit">
              {isSubmitting ? "Sending…" : "Send reset link"}
            </button>
          </form>
        )}
        <p>
          <Link href="/login">Back to login</Link>
        </p>
      </section>
    </main>
  );
}
