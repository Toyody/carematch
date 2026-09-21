"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { FormEvent, useEffect, useState } from "react";

import { FormError } from "@/components/forms/form-error";
import { useAuth } from "@/features/identity/auth-context";
import {
  getOrganisation,
  type Organisation,
} from "@/features/organisation/api";
import { ApiError } from "@/lib/api/client";

import { createJob } from "./api";
import { JobFormFields, jobInput } from "./job-form-fields";

export function CreateJob({ organisationId }: { organisationId: number }) {
  const { isLoading, user } = useAuth();
  const router = useRouter();
  const key = user ? `${user.id}:${organisationId}` : null;
  const [result, setResult] = useState<{
    error: unknown;
    key: string;
    organisation: Organisation | null;
  } | null>(null);
  const [submitError, setSubmitError] = useState<unknown>(null);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    if (isLoading || key === null) return;
    let active = true;
    void getOrganisation(organisationId).then(
      (organisation) => {
        if (active) setResult({ error: null, key, organisation });
      },
      (error: unknown) => {
        if (active) setResult({ error, key, organisation: null });
      },
    );
    return () => {
      active = false;
    };
  }, [isLoading, key, organisationId]);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSubmitError(null);
    setSubmitting(true);
    try {
      const job = await createJob(
        organisationId,
        jobInput(event.currentTarget),
      );
      router.push(`/organisations/${organisationId}/jobs/${job.id}`);
    } catch (error) {
      setSubmitError(error);
    } finally {
      setSubmitting(false);
    }
  }

  if (isLoading || (user && result?.key !== key))
    return (
      <JobShell title="Add job">
        <p role="status">Loading job form…</p>
      </JobShell>
    );
  if (!user) return <JobError status={401} />;
  if (result?.error)
    return (
      <JobError
        status={result.error instanceof ApiError ? result.error.status : null}
      />
    );
  if (!result?.organisation) return <JobError status={null} />;
  if (!["admin", "recruiter"].includes(result.organisation.membership.role)) {
    return (
      <JobShell title="Job creation unavailable">
        <p role="alert">Your Organisation role cannot create jobs.</p>
        <Link href={`/organisations/${organisationId}/jobs`}>Back to jobs</Link>
      </JobShell>
    );
  }

  return (
    <JobShell title="Add job" eyebrow={result.organisation.name}>
      <form onSubmit={submit}>
        <JobFormFields />
        <FormError error={submitError} />
        <button disabled={submitting} type="submit">
          {submitting ? "Creating…" : "Create draft"}
        </button>
      </form>
      <Link href={`/organisations/${organisationId}/jobs`}>Back to jobs</Link>
    </JobShell>
  );
}

function JobShell({
  children,
  eyebrow,
  title,
}: {
  children: React.ReactNode;
  eyebrow?: string;
  title: string;
}) {
  return (
    <main>
      <section className="foundation-card job-card">
        {eyebrow ? <p className="eyebrow">{eyebrow}</p> : null}
        <h1>{title}</h1>
        {children}
      </section>
    </main>
  );
}

function JobError({ status }: { status: number | null }) {
  return (
    <JobShell
      title={status === 401 ? "Session expired" : "Organisation unavailable"}
    >
      <p role="alert">
        {status === 401
          ? "Your session is no longer valid. Please log in again."
          : "This organisation is unavailable or you no longer have access."}
      </p>
      <Link href={status === 401 ? "/login" : "/"}>
        {status === 401 ? "Login" : "Back to your organisations"}
      </Link>
    </JobShell>
  );
}
