"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { useAuth } from "@/features/identity/auth-context";
import {
  getOrganisation,
  type Organisation,
} from "@/features/organisation/api";
import { ApiError } from "@/lib/api/client";
import { getApplication, type RecruitmentApplication } from "./api";

export function ApplicationDetail({
  applicationId,
  organisationId,
}: {
  applicationId: number;
  organisationId: number;
}) {
  const { isLoading, user } = useAuth();
  const key = user ? `${user.id}:${organisationId}:${applicationId}` : null;
  const [result, setResult] = useState<{
    application: RecruitmentApplication | null;
    error: unknown;
    key: string;
    organisation: Organisation | null;
  } | null>(null);

  useEffect(() => {
    if (isLoading || key === null) return;
    let active = true;
    void Promise.all([
      getOrganisation(organisationId),
      getApplication(organisationId, applicationId),
    ]).then(
      ([organisation, application]) =>
        active && setResult({ application, error: null, key, organisation }),
      (error: unknown) =>
        active &&
        setResult({ application: null, error, key, organisation: null }),
    );
    return () => {
      active = false;
    };
  }, [applicationId, isLoading, key, organisationId]);

  if (isLoading || (user && result?.key !== key))
    return (
      <Shell title="Application">
        <p role="status">Loading application…</p>
      </Shell>
    );
  if (!user) return <ErrorView status={401} />;
  if (result?.error || !result?.application || !result.organisation)
    return (
      <ErrorView
        status={result?.error instanceof ApiError ? result.error.status : null}
      />
    );
  const application = result.application;

  return (
    <Shell
      eyebrow={result.organisation.name}
      title={`${application.candidate.first_name} ${application.candidate.last_name}`}
    >
      <dl className="application-detail">
        <div>
          <dt>Job</dt>
          <dd>{application.job.title}</dd>
        </div>
        <div>
          <dt>Status</dt>
          <dd>{application.status}</dd>
        </div>
        <div>
          <dt>Applied</dt>
          <dd>{new Date(application.applied_at).toLocaleString()}</dd>
        </div>
      </dl>
      <p>Recruitment status transitions will be available in Phase 4B.</p>
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
function ErrorView({ status }: { status: number | null }) {
  const unauthorized = status === 401;
  return (
    <Shell title={unauthorized ? "Session expired" : "Application unavailable"}>
      <p role="alert">
        {unauthorized
          ? "Your session is no longer valid. Please log in again."
          : "This application is unavailable or you no longer have access."}
      </p>
      <Link href={unauthorized ? "/login" : "/"}>
        {unauthorized ? "Login" : "Back to your organisations"}
      </Link>
    </Shell>
  );
}
