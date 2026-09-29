"use client";

import Link from "next/link";
import { FormEvent, useEffect, useState } from "react";

import { FormError } from "@/components/forms/form-error";
import { useAuth } from "@/features/identity/auth-context";
import { getOrganisation } from "@/features/organisation/api";

import {
  listAuditEvents,
  type AuditEvent,
  type AuditEventPage,
  type AuditFilters,
} from "./api";

const EVENT_LABELS: Record<string, string> = {
  "application.created": "Application created",
  "application.status_changed": "Application status changed",
  "candidate.created": "Candidate created",
  "candidate.updated": "Candidate updated",
  "candidate_document.deleted": "Candidate document deleted",
  "candidate_document.uploaded": "Candidate document uploaded",
  "candidate_qualification.created": "Candidate qualification created",
  "candidate_qualification.deleted": "Candidate qualification deleted",
  "candidate_qualification.updated": "Candidate qualification updated",
  "invitation.accepted": "Invitation accepted",
  "invitation.created": "Invitation created",
  "invitation.revoked": "Invitation revoked",
  "job.archived": "Job archived",
  "job.closed": "Job closed",
  "job.created": "Job created",
  "job.opened": "Job opened",
  "job.updated": "Job updated",
  "job_qualification_requirement.added": "Job qualification requirement added",
  "job_qualification_requirement.removed":
    "Job qualification requirement removed",
  "membership.deactivated": "Membership deactivated",
  "membership.role_changed": "Membership role changed",
  "organisation.created": "Organisation created",
  "organisation.updated": "Organisation updated",
  "qualification_definition.created": "Qualification definition created",
  "qualification_definition.deactivated":
    "Qualification definition deactivated",
  "qualification_definition.updated": "Qualification definition updated",
};

export function AuditTrail({ organisationId }: { organisationId: number }) {
  const { isLoading: authLoading, user } = useAuth();
  const [isAdmin, setIsAdmin] = useState<boolean | null>(null);
  const [filters, setFilters] = useState<AuditFilters>({});
  const [page, setPage] = useState<AuditEventPage | null>(null);
  const [error, setError] = useState<unknown>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!user) return;
    let active = true;
    void getOrganisation(organisationId)
      .then((organisation) => {
        if (active) setIsAdmin(organisation.membership.role === "admin");
      })
      .catch((reason: unknown) => {
        if (active) {
          setError(reason);
          setLoading(false);
        }
      });
    return () => {
      active = false;
    };
  }, [organisationId, user]);

  useEffect(() => {
    if (!user || isAdmin !== true) {
      return;
    }
    let active = true;
    void listAuditEvents(organisationId, filters)
      .then((result) => {
        if (active) {
          setPage(result);
          setError(null);
        }
      })
      .catch((reason: unknown) => {
        if (active) setError(reason);
      })
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => {
      active = false;
    };
  }, [filters, isAdmin, organisationId, user]);

  function applyFilters(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const value = (key: string) => String(form.get(key) ?? "").trim();
    setLoading(true);
    setFilters({
      event_type: value("event_type") || undefined,
      subject_type: value("subject_type") || undefined,
      occurred_from: value("occurred_from") || undefined,
      occurred_to: value("occurred_to") || undefined,
    });
  }

  if (authLoading)
    return (
      <Shell>
        <p role="status">Checking your session…</p>
      </Shell>
    );
  if (!user)
    return (
      <Shell>
        <p role="alert">You must be signed in.</p>
        <Link href="/login">Login</Link>
      </Shell>
    );
  if (isAdmin === false)
    return (
      <Shell>
        <p role="alert">Only Organisation Admins can view the audit trail.</p>
        <Back organisationId={organisationId} />
      </Shell>
    );

  return (
    <Shell>
      <p className="eyebrow">Organisation administration</p>
      <h1>Audit Trail</h1>
      <p>
        Business activity is shown newest first. Audit entries are append-only.
      </p>
      <form onSubmit={applyFilters}>
        <label htmlFor="audit-event-type">Event type</label>
        <input
          id="audit-event-type"
          name="event_type"
          placeholder="candidate.updated"
        />
        <label htmlFor="audit-subject-type">Subject type</label>
        <input
          id="audit-subject-type"
          name="subject_type"
          placeholder="candidate"
        />
        <label htmlFor="audit-from">From</label>
        <input id="audit-from" name="occurred_from" type="date" />
        <label htmlFor="audit-to">To</label>
        <input id="audit-to" name="occurred_to" type="date" />
        <button type="submit">Apply filters</button>
      </form>
      {loading ? <p role="status">Loading audit events…</p> : null}
      <FormError error={error} />
      {!loading && !error && page?.data.length === 0 ? (
        <p>No audit events yet.</p>
      ) : null}
      {!loading && !error && page && page.data.length > 0 ? (
        <ol aria-label="Audit events">
          {page.data.map((event) => (
            <AuditItem event={event} key={event.id} />
          ))}
        </ol>
      ) : null}
      {page && page.meta.last_page > 1 ? (
        <nav aria-label="Audit pagination">
          <button
            disabled={page.meta.current_page <= 1}
            onClick={() => {
              setLoading(true);
              setFilters((current) => ({
                ...current,
                page: page.meta.current_page - 1,
              }));
            }}
          >
            Previous
          </button>
          <span>
            Page {page.meta.current_page} of {page.meta.last_page}
          </span>
          <button
            disabled={page.meta.current_page >= page.meta.last_page}
            onClick={() => {
              setLoading(true);
              setFilters((current) => ({
                ...current,
                page: page.meta.current_page + 1,
              }));
            }}
          >
            Next
          </button>
        </nav>
      ) : null}
      <Back organisationId={organisationId} />
    </Shell>
  );
}

function AuditItem({ event }: { event: AuditEvent }) {
  return (
    <li>
      <time dateTime={event.occurred_at}>
        {new Date(event.occurred_at).toLocaleString()}
      </time>{" "}
      <strong>{EVENT_LABELS[event.event_type] ?? event.event_type}</strong> by{" "}
      {event.actor.name} ({event.actor.email}) — {subjectLabel(event)}
      {metadataSummary(event.metadata) ? (
        <small> — {metadataSummary(event.metadata)}</small>
      ) : null}
    </li>
  );
}

function subjectLabel(event: AuditEvent) {
  return `${event.subject.type.replaceAll("_", " ")} #${event.subject.id}`;
}

function metadataSummary(metadata: AuditEvent["metadata"]): string {
  return Object.entries(metadata)
    .map(
      ([key, value]) =>
        `${key.replaceAll("_", " ")}: ${Array.isArray(value) ? value.join(", ") : String(value)}`,
    )
    .join("; ");
}

function Back({ organisationId }: { organisationId: number }) {
  return (
    <Link href={`/organisations/${organisationId}`}>Back to workspace</Link>
  );
}

function Shell({ children }: { children: React.ReactNode }) {
  return (
    <main>
      <section className="foundation-card workspace-card">{children}</section>
    </main>
  );
}
