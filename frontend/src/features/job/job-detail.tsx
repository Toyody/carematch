"use client";

import Link from "next/link";
import { FormEvent, useEffect, useState } from "react";
import { FormError } from "@/components/forms/form-error";
import { useAuth } from "@/features/identity/auth-context";
import {
  getOrganisation,
  type Organisation,
} from "@/features/organisation/api";
import { ApiError } from "@/lib/api/client";
import {
  getJob,
  transitionJob,
  updateJob,
  type Job,
  type JobTransition,
} from "./api";
import { JobFormFields, jobInput } from "./job-form-fields";

interface Result {
  error: unknown;
  job: Job | null;
  key: string;
  organisation: Organisation | null;
}

export function JobDetail({
  jobId,
  organisationId,
}: {
  jobId: number;
  organisationId: number;
}) {
  const { isLoading, user } = useAuth();
  const key = user ? `${user.id}:${organisationId}:${jobId}` : null;
  const [result, setResult] = useState<Result | null>(null);
  const [operationError, setOperationError] = useState<unknown>(null);
  const [submitting, setSubmitting] = useState(false);
  const [message, setMessage] = useState<string | null>(null);

  useEffect(() => {
    if (isLoading || key === null) return;
    let active = true;
    void Promise.all([
      getOrganisation(organisationId),
      getJob(organisationId, jobId),
    ])
      .then(([organisation, job]) => {
        if (active) {
          setResult({ error: null, job, key, organisation });
          setOperationError(null);
          setMessage(null);
        }
      })
      .catch((error: unknown) => {
        if (active) setResult({ error, job: null, key, organisation: null });
      });
    return () => {
      active = false;
    };
  }, [isLoading, jobId, key, organisationId]);

  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSubmitting(true);
    setOperationError(null);
    setMessage(null);
    try {
      replaceJob(
        await updateJob(organisationId, jobId, jobInput(event.currentTarget)),
      );
      setMessage("Job saved.");
    } catch (error) {
      setOperationError(error);
    } finally {
      setSubmitting(false);
    }
  }

  async function transition(action: JobTransition) {
    setSubmitting(true);
    setOperationError(null);
    setMessage(null);
    try {
      replaceJob(await transitionJob(organisationId, jobId, action));
      setMessage("Job status updated.");
    } catch (error) {
      setOperationError(error);
    } finally {
      setSubmitting(false);
    }
  }

  function replaceJob(job: Job) {
    setResult((current) => (current ? { ...current, job } : current));
  }

  if (isLoading || (user && result?.key !== key))
    return (
      <Shell title="Job">
        <p role="status">Loading job…</p>
      </Shell>
    );
  if (!user) return <DetailError status={401} />;
  if (result?.error)
    return (
      <DetailError
        status={result.error instanceof ApiError ? result.error.status : null}
      />
    );
  if (!result?.job || !result.organisation)
    return <DetailError status={null} />;
  const { job, organisation } = result;
  const mayWrite = ["admin", "recruiter"].includes(
    organisation.membership.role,
  );
  const actions = lifecycleActions(job.status);

  return (
    <Shell title={job.title} eyebrow={organisation.name}>
      <p>
        Status: <strong>{job.status}</strong>
      </p>
      {mayWrite ? (
        <>
          <form onSubmit={save}>
            <JobFormFields job={job} />
            <button disabled={submitting} type="submit">
              {submitting ? "Saving…" : "Save job"}
            </button>
          </form>
          {actions.length ? (
            <div className="job-actions" aria-label="Job lifecycle actions">
              {actions.map(({ action, label }) => (
                <button
                  disabled={submitting}
                  key={action}
                  onClick={() => void transition(action)}
                  type="button"
                >
                  {label}
                </button>
              ))}
            </div>
          ) : (
            <p>This archived job has no further lifecycle actions.</p>
          )}
          <FormError error={operationError} />
          {message ? <p role="status">{message}</p> : null}
        </>
      ) : (
        <ReadOnly job={job} />
      )}
      <Link href={`/organisations/${organisationId}/jobs`}>Back to jobs</Link>
    </Shell>
  );
}

function lifecycleActions(
  status: Job["status"],
): { action: JobTransition; label: string }[] {
  if (status === "draft")
    return [
      { action: "open", label: "Open job" },
      { action: "archive", label: "Archive job" },
    ];
  if (status === "open")
    return [
      { action: "close", label: "Close job" },
      { action: "archive", label: "Archive job" },
    ];
  if (status === "closed")
    return [
      { action: "open", label: "Reopen job" },
      { action: "archive", label: "Archive job" },
    ];
  return [];
}

function ReadOnly({ job }: { job: Job }) {
  const values = [
    ["Occupation", job.occupation],
    ["Location", job.location],
    ["Employment type", job.employment_type],
    ["Opening date", job.opened_at],
    ["Closing date", job.closes_at],
    ["Description", job.description],
  ];
  return (
    <div>
      <p>Your Organisation role has read-only Job access.</p>
      <dl className="candidate-details">
        {values.map(([label, value]) => (
          <div key={label}>
            <dt>{label}</dt>
            <dd>{value ?? "Not provided"}</dd>
          </div>
        ))}
      </dl>
    </div>
  );
}

function Shell({
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
function DetailError({ status }: { status: number | null }) {
  return (
    <Shell title={status === 401 ? "Session expired" : "Job unavailable"}>
      <p role="alert">
        {status === 401
          ? "Your session is no longer valid. Please log in again."
          : "This job is unavailable or you no longer have access."}
      </p>
      <Link href={status === 401 ? "/login" : "/"}>
        {status === 401 ? "Login" : "Back to your organisations"}
      </Link>
    </Shell>
  );
}
