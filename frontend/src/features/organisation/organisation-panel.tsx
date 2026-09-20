"use client";

import { FormEvent, useEffect, useState } from "react";

import { FormError } from "@/components/forms/form-error";

import {
  createOrganisation,
  listOrganisations,
  type Organisation,
} from "./api";

export function OrganisationPanel() {
  const [organisations, setOrganisations] = useState<Organisation[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [loadError, setLoadError] = useState(false);
  const [createError, setCreateError] = useState<unknown>(null);

  useEffect(() => {
    let active = true;

    async function loadOrganisations() {
      try {
        const availableOrganisations = await listOrganisations();

        if (active) {
          setOrganisations(availableOrganisations);
        }
      } catch {
        if (active) {
          setLoadError(true);
        }
      } finally {
        if (active) {
          setIsLoading(false);
        }
      }
    }

    void loadOrganisations();

    return () => {
      active = false;
    };
  }, []);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setCreateError(null);
    setIsSubmitting(true);
    const form = event.currentTarget;
    const data = new FormData(form);

    try {
      const organisation = await createOrganisation({
        name: String(data.get("name") ?? ""),
      });
      setOrganisations((current) =>
        [...current, organisation].sort((left, right) =>
          left.name.localeCompare(right.name),
        ),
      );
      form.reset();
    } catch (error) {
      setCreateError(error);
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <section
      aria-labelledby="organisations-title"
      className="organisation-panel"
    >
      <h2 id="organisations-title">Your organisations</h2>

      {isLoading ? <p role="status">Loading organisations…</p> : null}
      {loadError ? (
        <p role="alert">Unable to load your organisations. Please try again.</p>
      ) : null}
      {!isLoading && !loadError && organisations.length === 0 ? (
        <p>You do not belong to an organisation yet.</p>
      ) : null}
      {organisations.length > 0 ? (
        <ul className="organisation-list">
          {organisations.map((organisation) => (
            <li key={organisation.id}>
              <span>{organisation.name}</span>
              <span>{organisation.membership.role}</span>
            </li>
          ))}
        </ul>
      ) : null}

      <form onSubmit={handleSubmit}>
        <label htmlFor="organisation-name">Organisation name</label>
        <input id="organisation-name" maxLength={255} name="name" required />
        <FormError error={createError} />
        <button disabled={isSubmitting} type="submit">
          {isSubmitting ? "Creating…" : "Create organisation"}
        </button>
      </form>
    </section>
  );
}
