"use client";

import Link from "next/link";
import { FormEvent, useEffect, useState } from "react";
import { FormError } from "@/components/forms/form-error";
import { useAuth } from "@/features/identity/auth-context";
import {
  getOrganisation,
  type Organisation,
  type OrganisationRole,
} from "@/features/organisation/api";
import { ApiError } from "@/lib/api/client";
import {
  getApplication,
  getApplicationHistory,
  transitionApplication,
  type ApplicationHistoryEvent,
  type ApplicationStatus,
  type RecruitmentApplication,
} from "./api";

interface Result {
  application: RecruitmentApplication | null;
  error: unknown;
  history: ApplicationHistoryEvent[];
  key: string;
  organisation: Organisation | null;
}

export function ApplicationDetail({
  applicationId,
  organisationId,
}: {
  applicationId: number;
  organisationId: number;
}) {
  const { isLoading, user } = useAuth();
  const key = user ? `${user.id}:${organisationId}:${applicationId}` : null;
  const [result, setResult] = useState<Result | null>(null);
  const [note, setNote] = useState("");
  const [operationError, setOperationError] = useState<unknown>(null);
  const [submitting, setSubmitting] = useState(false);
  const [message, setMessage] = useState<string | null>(null);

  useEffect(() => {
    if (isLoading || key === null) return;
    let active = true;
    void Promise.all([
      getOrganisation(organisationId),
      getApplication(organisationId, applicationId),
      getApplicationHistory(organisationId, applicationId),
    ]).then(
      ([organisation, application, history]) => {
        if (active) {
          setResult({ application, error: null, history, key, organisation });
          setOperationError(null);
          setMessage(null);
          setNote("");
        }
      },
      (error: unknown) =>
        active &&
        setResult({
          application: null,
          error,
          history: [],
          key,
          organisation: null,
        }),
    );
    return () => {
      active = false;
    };
  }, [applicationId, isLoading, key, organisationId]);

  async function transition(
    event: FormEvent<HTMLFormElement>,
    target: ApplicationStatus,
  ) {
    event.preventDefault();
    setSubmitting(true);
    setOperationError(null);
    setMessage(null);
    try {
      const application = await transitionApplication(
        organisationId,
        applicationId,
        { note: note || undefined, to_status: target },
      );
      const history = await getApplicationHistory(
        organisationId,
        applicationId,
      );
      setResult((current) =>
        current ? { ...current, application, history } : current,
      );
      setNote("");
      setMessage(`Application moved to ${application.status}.`);
    } catch (error) {
      if (error instanceof ApiError && error.status === 409) {
        await refreshAfterConflict(error);
      } else {
        setOperationError(error);
      }
    } finally {
      setSubmitting(false);
    }
  }

  async function refreshAfterConflict(error: ApiError) {
    setOperationError(error);
    try {
      const [application, history] = await Promise.all([
        getApplication(organisationId, applicationId),
        getApplicationHistory(organisationId, applicationId),
      ]);
      setResult((current) =>
        current ? { ...current, application, history } : current,
      );
    } catch {
      setResult((current) =>
        current
          ? { ...current, application: null, error, history: [] }
          : current,
      );
    }
  }

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
  const { application, history, organisation } = result;
  const actions = transitionActions(
    application.status,
    organisation.membership.role,
  );

  return (
    <Shell
      eyebrow={organisation.name}
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

      {actions.length ? (
        <section aria-labelledby="transition-heading">
          <h2 id="transition-heading">Update pipeline stage</h2>
          <label htmlFor="transition-note">Optional note</label>
          <textarea
            disabled={submitting}
            id="transition-note"
            maxLength={1000}
            onChange={(event) => setNote(event.target.value)}
            value={note}
          />
          <div className="application-actions">
            {actions.map(({ label, target }) => (
              <form
                key={target}
                onSubmit={(event) => void transition(event, target)}
              >
                <button disabled={submitting} type="submit">
                  {submitting ? "Updating…" : label}
                </button>
              </form>
            ))}
          </div>
        </section>
      ) : (
        <p>No pipeline actions are available for this status and role.</p>
      )}
      {operationError instanceof ApiError && operationError.status === 403 ? (
        <p role="alert">
          Your Organisation role cannot perform this application transition.
        </p>
      ) : (
        <FormError error={operationError} />
      )}
      {message ? <p role="status">{message}</p> : null}

      <HistoryTimeline history={history} />
      <Link href={`/organisations/${organisationId}/applications`}>
        Back to applications
      </Link>
    </Shell>
  );
}

function transitionActions(
  status: ApplicationStatus,
  role: OrganisationRole,
): { label: string; target: ApplicationStatus }[] {
  if (role === "hiring_manager") {
    return status === "interview"
      ? [
          { label: "Move to Offer", target: "offer" },
          { label: "Reject", target: "rejected" },
        ]
      : [];
  }

  const forward: Partial<
    Record<ApplicationStatus, { label: string; target: ApplicationStatus }>
  > = {
    applied: { label: "Move to Screening", target: "screening" },
    screening: { label: "Move to Interview", target: "interview" },
    interview: { label: "Move to Offer", target: "offer" },
    offer: { label: "Mark as Hired", target: "hired" },
  };
  const next = forward[status];
  return next ? [next, { label: "Reject", target: "rejected" }] : [];
}

function HistoryTimeline({ history }: { history: ApplicationHistoryEvent[] }) {
  return (
    <section aria-labelledby="history-heading">
      <h2 id="history-heading">Status history</h2>
      <ol className="application-history">
        {history.map((event) => (
          <li key={event.id}>
            <strong>
              {event.from_status === null
                ? "Application created / Applied"
                : `${event.from_status} → ${event.to_status}`}
            </strong>
            <span>{new Date(event.created_at).toLocaleString()}</span>
            <span>Changed by user {event.changed_by_user_id}</span>
            {event.note ? <p>{event.note}</p> : null}
          </li>
        ))}
      </ol>
    </section>
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
