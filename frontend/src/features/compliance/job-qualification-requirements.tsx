"use client";

import { FormEvent, useEffect, useState } from "react";
import { FormError } from "@/components/forms/form-error";
import {
  addJobQualificationRequirement,
  deleteJobQualificationRequirement,
  listJobQualificationRequirements,
  listQualificationDefinitions,
  type JobQualificationRequirement,
  type QualificationDefinition,
} from "./api";

export function JobQualificationRequirements({
  jobId,
  mayManage,
  organisationId,
}: {
  jobId: number;
  mayManage: boolean;
  organisationId: number;
}) {
  const [requirements, setRequirements] = useState<
    JobQualificationRequirement[] | null
  >(null);
  const [definitions, setDefinitions] = useState<QualificationDefinition[]>([]);
  const [error, setError] = useState<unknown>(null);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    let active = true;
    void Promise.all([
      listJobQualificationRequirements(organisationId, jobId),
      listQualificationDefinitions(organisationId),
    ])
      .then(([nextRequirements, nextDefinitions]) => {
        if (active) {
          setRequirements(nextRequirements);
          setDefinitions(nextDefinitions);
        }
      })
      .catch((reason: unknown) => {
        if (active) setError(reason);
      });
    return () => {
      active = false;
    };
  }, [jobId, organisationId]);

  async function add(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSubmitting(true);
    setError(null);
    const definitionId = Number(
      new FormData(event.currentTarget).get("qualification_definition_id"),
    );
    try {
      const created = await addJobQualificationRequirement(
        organisationId,
        jobId,
        definitionId,
      );
      setRequirements((current) => [...(current ?? []), created]);
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
      await deleteJobQualificationRequirement(organisationId, jobId, id);
      setRequirements((current) =>
        (current ?? []).filter((item) => item.id !== id),
      );
    } catch (reason) {
      setError(reason);
    } finally {
      setSubmitting(false);
    }
  }

  const used = new Set(
    (requirements ?? []).map((item) => item.qualification_definition_id),
  );
  return (
    <section aria-labelledby="job-qualification-requirements-heading">
      <h2 id="job-qualification-requirements-heading">
        Required qualifications
      </h2>
      {requirements === null && error === null ? (
        <p role="status">Loading requirements…</p>
      ) : null}
      {requirements?.length === 0 ? (
        <p>No required qualifications recorded.</p>
      ) : null}
      {requirements?.length ? (
        <ul>
          {requirements.map((requirement) => (
            <li key={requirement.id}>
              <strong>{requirement.qualification_name}</strong>
              {mayManage ? (
                <button
                  disabled={submitting}
                  type="button"
                  onClick={() => void remove(requirement.id)}
                >
                  Remove
                </button>
              ) : null}
            </li>
          ))}
        </ul>
      ) : null}
      {mayManage ? (
        <form onSubmit={add}>
          <label htmlFor="job-qualification-definition">
            Add required qualification
          </label>
          <select
            id="job-qualification-definition"
            name="qualification_definition_id"
            required
            defaultValue=""
          >
            <option disabled value="">
              Select qualification
            </option>
            {definitions
              .filter((item) => item.is_active && !used.has(item.id))
              .map((item) => (
                <option key={item.id} value={item.id}>
                  {item.name}
                </option>
              ))}
          </select>
          <button disabled={submitting} type="submit">
            {submitting ? "Adding…" : "Add requirement"}
          </button>
        </form>
      ) : (
        <p>Your Organisation role has read-only requirement access.</p>
      )}
      <FormError error={error} />
    </section>
  );
}
