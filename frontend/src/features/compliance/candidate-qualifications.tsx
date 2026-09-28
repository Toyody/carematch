"use client";

import { FormEvent, useEffect, useState } from "react";
import { FormError } from "@/components/forms/form-error";
import {
  createCandidateQualification,
  deleteCandidateQualification,
  listCandidateQualifications,
  listQualificationDefinitions,
  updateCandidateQualification,
  type CandidateQualification,
  type CandidateQualificationInput,
  type QualificationDefinition,
} from "./api";

export function CandidateQualifications({
  candidateId,
  mayManage,
  organisationId,
}: {
  candidateId: number;
  mayManage: boolean;
  organisationId: number;
}) {
  const [credentials, setCredentials] = useState<
    CandidateQualification[] | null
  >(null);
  const [definitions, setDefinitions] = useState<QualificationDefinition[]>([]);
  const [error, setError] = useState<unknown>(null);
  const [editing, setEditing] = useState<CandidateQualification | null>(null);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    let active = true;
    void Promise.all([
      listCandidateQualifications(organisationId, candidateId),
      listQualificationDefinitions(organisationId),
    ])
      .then(([nextCredentials, nextDefinitions]) => {
        if (active) {
          setCredentials(nextCredentials);
          setDefinitions(nextDefinitions);
        }
      })
      .catch((reason: unknown) => {
        if (active) setError(reason);
      });
    return () => {
      active = false;
    };
  }, [candidateId, organisationId]);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSubmitting(true);
    setError(null);
    const form = new FormData(event.currentTarget);
    const nullable = (name: string) =>
      String(form.get(name) ?? "").trim() || null;
    const input: CandidateQualificationInput = {
      qualification_definition_id: Number(
        form.get("qualification_definition_id"),
      ),
      issuer: nullable("issuer"),
      credential_number: nullable("credential_number"),
      issued_on: nullable("issued_on"),
      expires_on: nullable("expires_on"),
    };
    try {
      const saved = editing
        ? await updateCandidateQualification(
            organisationId,
            candidateId,
            editing.id,
            input,
          )
        : await createCandidateQualification(
            organisationId,
            candidateId,
            input,
          );
      setCredentials((current) =>
        editing
          ? (current ?? []).map((item) => (item.id === saved.id ? saved : item))
          : [...(current ?? []), saved],
      );
      setEditing(null);
      event.currentTarget.reset();
    } catch (reason) {
      setError(reason);
    } finally {
      setSubmitting(false);
    }
  }

  async function remove(id: number) {
    setSubmitting(true);
    setError(null);
    try {
      await deleteCandidateQualification(organisationId, candidateId, id);
      setCredentials((current) =>
        (current ?? []).filter((item) => item.id !== id),
      );
    } catch (reason) {
      setError(reason);
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <section aria-labelledby="candidate-qualifications-heading">
      <h2 id="candidate-qualifications-heading">
        Qualifications and certifications
      </h2>
      {credentials === null && error === null ? (
        <p role="status">Loading qualifications…</p>
      ) : null}
      {credentials?.length === 0 ? <p>No qualifications recorded.</p> : null}
      {credentials?.length ? (
        <ul className="compliance-list">
          {credentials.map((credential) => (
            <li key={credential.id}>
              <strong>{credential.qualification_name}</strong> —{" "}
              <span>{statusLabel(credential.status)}</span>
              <p>Expires: {credential.expires_on ?? "Does not expire"}</p>
              {credential.issuer ? <p>Issuer: {credential.issuer}</p> : null}
              {credential.credential_number ? (
                <p>Reference: {credential.credential_number}</p>
              ) : null}
              {mayManage ? (
                <div>
                  <button type="button" onClick={() => setEditing(credential)}>
                    Edit
                  </button>
                  <button
                    disabled={submitting}
                    type="button"
                    onClick={() => void remove(credential.id)}
                  >
                    Remove
                  </button>
                </div>
              ) : null}
            </li>
          ))}
        </ul>
      ) : null}
      {mayManage ? (
        <form key={editing?.id ?? "new"} onSubmit={submit}>
          <h3>{editing ? "Edit qualification" : "Add qualification"}</h3>
          <label htmlFor="credential-definition">Qualification</label>
          <select
            defaultValue={editing?.qualification_definition_id ?? ""}
            id="credential-definition"
            name="qualification_definition_id"
            required
          >
            <option disabled value="">
              Select qualification
            </option>
            {definitions
              .filter(
                (item) =>
                  item.is_active ||
                  item.id === editing?.qualification_definition_id,
              )
              .map((item) => (
                <option key={item.id} value={item.id}>
                  {item.name}
                </option>
              ))}
          </select>
          <label htmlFor="credential-issuer">Issuer</label>
          <input
            defaultValue={editing?.issuer ?? ""}
            id="credential-issuer"
            maxLength={200}
            name="issuer"
          />
          <label htmlFor="credential-number">Credential/reference number</label>
          <input
            defaultValue={editing?.credential_number ?? ""}
            id="credential-number"
            maxLength={255}
            name="credential_number"
          />
          <label htmlFor="credential-issued">Issued date</label>
          <input
            defaultValue={editing?.issued_on ?? ""}
            id="credential-issued"
            name="issued_on"
            type="date"
          />
          <label htmlFor="credential-expires">Expiry date</label>
          <input
            defaultValue={editing?.expires_on ?? ""}
            id="credential-expires"
            name="expires_on"
            type="date"
          />
          <button disabled={submitting} type="submit">
            {submitting
              ? "Saving…"
              : editing
                ? "Save qualification"
                : "Add qualification"}
          </button>
          {editing ? (
            <button type="button" onClick={() => setEditing(null)}>
              Cancel
            </button>
          ) : null}
        </form>
      ) : (
        <p>Your Organisation role has read-only qualification access.</p>
      )}
      <FormError error={error} />
    </section>
  );
}

function statusLabel(status: CandidateQualification["status"]) {
  return status === "valid"
    ? "Valid"
    : status === "expiring"
      ? "Expiring soon"
      : "Expired";
}
