"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { FormEvent, useEffect, useState } from "react";
import { FormError } from "@/components/forms/form-error";
import { listCandidates, type Candidate } from "@/features/candidate/api";
import { useAuth } from "@/features/identity/auth-context";
import { listJobs, type Job } from "@/features/job/api";
import {
  getOrganisation,
  type Organisation,
} from "@/features/organisation/api";
import { ApiError } from "@/lib/api/client";
import { createApplication } from "./api";

export function CreateApplication({
  organisationId,
}: {
  organisationId: number;
}) {
  const { isLoading, user } = useAuth();
  const router = useRouter();
  const key = user ? `${user.id}:${organisationId}` : null;
  const [result, setResult] = useState<{
    candidates: Candidate[];
    error: unknown;
    jobs: Job[];
    key: string;
    organisation: Organisation | null;
  } | null>(null);
  const [submitError, setSubmitError] = useState<unknown>(null);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    if (isLoading || key === null) return;
    let active = true;
    void Promise.all([
      getOrganisation(organisationId),
      listJobs(organisationId, { per_page: "100", status: "open" }),
      listCandidates(organisationId, {
        per_page: "100",
        sort: "name",
        direction: "asc",
      }),
    ]).then(
      ([organisation, jobs, candidates]) =>
        active &&
        setResult({
          candidates: candidates.data,
          error: null,
          jobs: jobs.data,
          key,
          organisation,
        }),
      (error: unknown) =>
        active &&
        setResult({ candidates: [], error, jobs: [], key, organisation: null }),
    );
    return () => {
      active = false;
    };
  }, [isLoading, key, organisationId]);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSubmitError(null);
    setSubmitting(true);
    const data = new FormData(event.currentTarget);
    try {
      const application = await createApplication(organisationId, {
        candidate_id: Number(data.get("candidate_id")),
        job_id: Number(data.get("job_id")),
      });
      router.push(
        `/organisations/${organisationId}/applications/${application.id}`,
      );
    } catch (error) {
      setSubmitError(error);
    } finally {
      setSubmitting(false);
    }
  }

  if (isLoading || (user && result?.key !== key))
    return (
      <Shell title="Add application">
        <p role="status">Loading application form…</p>
      </Shell>
    );
  if (!user) return <Unavailable status={401} />;
  if (result?.error || !result?.organisation)
    return (
      <Unavailable
        status={result?.error instanceof ApiError ? result.error.status : null}
      />
    );
  if (!["admin", "recruiter"].includes(result.organisation.membership.role)) {
    return (
      <Shell title="Application creation unavailable">
        <p role="alert">Your Organisation role cannot create applications.</p>
        <Link href={`/organisations/${organisationId}/applications`}>
          Back to applications
        </Link>
      </Shell>
    );
  }

  return (
    <Shell eyebrow={result.organisation.name} title="Add application">
      <form onSubmit={submit}>
        <label htmlFor="new-application-job">Open job</label>
        <select id="new-application-job" name="job_id" required>
          <option value="">Choose an open job</option>
          {result.jobs.map((job) => (
            <option key={job.id} value={job.id}>
              {job.title}
            </option>
          ))}
        </select>
        <label htmlFor="new-application-candidate">Candidate</label>
        <select id="new-application-candidate" name="candidate_id" required>
          <option value="">Choose a candidate</option>
          {result.candidates.map((candidate) => (
            <option key={candidate.id} value={candidate.id}>
              {candidate.first_name} {candidate.last_name}
            </option>
          ))}
        </select>
        {result.jobs.length === 0 ? (
          <p>No open jobs are currently available.</p>
        ) : null}
        {result.candidates.length === 0 ? (
          <p>No candidates are currently available.</p>
        ) : null}
        <FormError error={submitError} />
        <button
          disabled={
            submitting ||
            result.jobs.length === 0 ||
            result.candidates.length === 0
          }
          type="submit"
        >
          {submitting ? "Creating…" : "Create application"}
        </button>
      </form>
      <Link href={`/organisations/${organisationId}/applications`}>
        Back to applications
      </Link>
    </Shell>
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
      <section className="foundation-card application-card">
        {eyebrow ? <p className="eyebrow">{eyebrow}</p> : null}
        <h1>{title}</h1>
        {children}
      </section>
    </main>
  );
}
function Unavailable({ status }: { status: number | null }) {
  const unauthorized = status === 401;
  return (
    <Shell
      title={unauthorized ? "Session expired" : "Organisation unavailable"}
    >
      <p role="alert">
        {unauthorized
          ? "Your session is no longer valid. Please log in again."
          : "This organisation is unavailable or you no longer have access."}
      </p>
      <Link href={unauthorized ? "/login" : "/"}>
        {unauthorized ? "Login" : "Back to your organisations"}
      </Link>
    </Shell>
  );
}
