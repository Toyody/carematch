"use client";

import Link from "next/link";
import { FormEvent, useEffect, useState } from "react";
import { FormError } from "@/components/forms/form-error";
import { useAuth } from "@/features/identity/auth-context";
import {
  getOrganisation,
  type Organisation,
} from "@/features/organisation/api";
import {
  createQualificationDefinition,
  listQualificationDefinitions,
  listQualificationExpiries,
  updateQualificationDefinition,
  type QualificationDefinition,
  type QualificationExpiry,
} from "./api";

export function ComplianceWorkspace({
  organisationId,
}: {
  organisationId: number;
}) {
  const { isLoading, user } = useAuth();
  const [organisation, setOrganisation] = useState<Organisation | null>(null);
  const [definitions, setDefinitions] = useState<
    QualificationDefinition[] | null
  >(null);
  const [expiries, setExpiries] = useState<QualificationExpiry[] | null>(null);
  const [error, setError] = useState<unknown>(null);
  const [submitting, setSubmitting] = useState(false);
  useEffect(() => {
    if (!user) return;
    let active = true;
    void Promise.all([
      getOrganisation(organisationId),
      listQualificationDefinitions(organisationId),
      listQualificationExpiries(organisationId),
    ])
      .then(([nextOrganisation, nextDefinitions, nextExpiries]) => {
        if (active) {
          setOrganisation(nextOrganisation);
          setDefinitions(nextDefinitions);
          setExpiries(nextExpiries);
        }
      })
      .catch((reason: unknown) => {
        if (active) setError(reason);
      });
    return () => {
      active = false;
    };
  }, [organisationId, user]);

  async function create(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSubmitting(true);
    setError(null);
    const form = new FormData(event.currentTarget);
    const nullable = (name: string) =>
      String(form.get(name) ?? "").trim() || null;
    try {
      const definition = await createQualificationDefinition(organisationId, {
        name: String(form.get("name") ?? ""),
        category: nullable("category"),
        description: nullable("description"),
      });
      setDefinitions((current) => [...(current ?? []), definition]);
      event.currentTarget.reset();
    } catch (reason) {
      setError(reason);
    } finally {
      setSubmitting(false);
    }
  }

  async function toggle(definition: QualificationDefinition) {
    setSubmitting(true);
    setError(null);
    try {
      const saved = await updateQualificationDefinition(organisationId, {
        ...definition,
        is_active: !definition.is_active,
      });
      setDefinitions((current) =>
        (current ?? []).map((item) => (item.id === saved.id ? saved : item)),
      );
    } catch (reason) {
      setError(reason);
    } finally {
      setSubmitting(false);
    }
  }

  if (isLoading || (user && definitions === null && error === null))
    return (
      <Shell>
        <p role="status">Loading qualifications…</p>
      </Shell>
    );
  if (!user)
    return (
      <Shell>
        <p role="alert">You must be signed in.</p>
        <Link href="/login">Login</Link>
      </Shell>
    );
  const admin = organisation?.membership.role === "admin";
  return (
    <Shell>
      <p className="eyebrow">{organisation?.name}</p>
      <h1>Qualifications and expiry tracking</h1>
      <p>
        CareMatch tracks Organisation-defined requirements; it does not certify
        legal or regulatory compliance.
      </p>
      <section aria-labelledby="catalogue-heading">
        <h2 id="catalogue-heading">Qualification catalogue</h2>
        {definitions?.length === 0 ? (
          <p>No qualification definitions yet.</p>
        ) : (
          <ul>
            {definitions?.map((definition) => (
              <li key={definition.id}>
                <strong>{definition.name}</strong>
                {definition.category ? ` — ${definition.category}` : ""} (
                {definition.is_active ? "Active" : "Inactive"})
                {admin ? (
                  <button
                    disabled={submitting}
                    type="button"
                    onClick={() => void toggle(definition)}
                  >
                    {definition.is_active ? "Deactivate" : "Reactivate"}
                  </button>
                ) : null}
              </li>
            ))}
          </ul>
        )}
        {admin ? (
          <form onSubmit={create}>
            <h3>Add qualification definition</h3>
            <label htmlFor="definition-name">Name</label>
            <input id="definition-name" maxLength={200} name="name" required />
            <label htmlFor="definition-category">Category</label>
            <input id="definition-category" maxLength={100} name="category" />
            <label htmlFor="definition-description">Description</label>
            <textarea
              id="definition-description"
              maxLength={1000}
              name="description"
            />
            <button disabled={submitting} type="submit">
              {submitting ? "Saving…" : "Add definition"}
            </button>
          </form>
        ) : (
          <p>Only Organisation Admins can change the catalogue.</p>
        )}
      </section>
      <section aria-labelledby="expiry-heading">
        <h2 id="expiry-heading">Expired and expiring credentials</h2>
        {expiries?.length === 0 ? (
          <p>No credentials are expired or within the warning window.</p>
        ) : (
          <ul>
            {expiries?.map((item) => (
              <li key={item.candidate_qualification_id}>
                <Link
                  href={`/organisations/${organisationId}/candidates/${item.candidate_id}`}
                >
                  {item.candidate_name}
                </Link>{" "}
                — {item.qualification_name}:{" "}
                <strong>
                  {item.status === "expired" ? "Expired" : "Expiring soon"}
                </strong>{" "}
                ({item.expires_on})
              </li>
            ))}
          </ul>
        )}
      </section>
      <FormError error={error} />
      <Link href={`/organisations/${organisationId}`}>Back to workspace</Link>
    </Shell>
  );
}

function Shell({ children }: { children: React.ReactNode }) {
  return (
    <main>
      <section className="foundation-card compliance-card">{children}</section>
    </main>
  );
}
