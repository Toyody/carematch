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

import { getCandidate, updateCandidate, type Candidate } from "./api";
import { CandidateFormFields, candidateInput } from "./candidate-form-fields";

interface CandidateDetailResult {
  candidate: Candidate | null;
  error: unknown;
  key: string;
  organisation: Organisation | null;
}

export function CandidateDetail({
  candidateId,
  organisationId,
}: {
  candidateId: number;
  organisationId: number;
}) {
  const { isLoading: isAuthLoading, user } = useAuth();
  const [result, setResult] = useState<CandidateDetailResult | null>(null);
  const [submitError, setSubmitError] = useState<unknown>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [saved, setSaved] = useState(false);
  const userId = user?.id;
  const resultKey =
    userId === undefined ? null : `${userId}:${organisationId}:${candidateId}`;

  useEffect(() => {
    if (isAuthLoading || resultKey === null) {
      return;
    }

    let active = true;

    void Promise.all([
      getOrganisation(organisationId),
      getCandidate(organisationId, candidateId),
    ])
      .then(([organisation, candidate]) => {
        if (active) {
          setResult({
            candidate,
            error: null,
            key: resultKey,
            organisation,
          });
          setSaved(false);
          setSubmitError(null);
        }
      })
      .catch((error: unknown) => {
        if (active) {
          setResult({
            candidate: null,
            error,
            key: resultKey,
            organisation: null,
          });
        }
      });

    return () => {
      active = false;
    };
  }, [candidateId, isAuthLoading, organisationId, resultKey]);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSubmitError(null);
    setSaved(false);
    setIsSubmitting(true);

    try {
      const candidate = await updateCandidate(
        organisationId,
        candidateId,
        candidateInput(event.currentTarget),
      );
      setResult((current) =>
        current === null ? current : { ...current, candidate },
      );
      setSaved(true);
    } catch (error) {
      setSubmitError(error);
    } finally {
      setIsSubmitting(false);
    }
  }

  const currentResult = result?.key === resultKey ? result : null;

  if (isAuthLoading) {
    return <DetailStatus />;
  }

  if (user === null) {
    return <DetailError status={401} />;
  }

  if (currentResult === null) {
    return <DetailStatus />;
  }

  if (currentResult.error !== null) {
    return (
      <DetailError
        status={
          currentResult.error instanceof ApiError
            ? currentResult.error.status
            : null
        }
      />
    );
  }

  const candidate = currentResult.candidate;
  const organisation = currentResult.organisation;

  if (candidate === null || organisation === null) {
    return <DetailError status={null} />;
  }

  const mayWrite = ["admin", "recruiter"].includes(
    organisation.membership.role,
  );

  return (
    <main>
      <section className="foundation-card candidate-card">
        <p className="eyebrow">{organisation.name}</p>
        <h1>
          {candidate.first_name} {candidate.last_name}
        </h1>

        {mayWrite ? (
          <form onSubmit={handleSubmit}>
            <CandidateFormFields candidate={candidate} />
            <FormError error={submitError} />
            {saved ? <p role="status">Candidate saved.</p> : null}
            <button disabled={isSubmitting} type="submit">
              {isSubmitting ? "Saving…" : "Save candidate"}
            </button>
          </form>
        ) : (
          <CandidateReadOnly candidate={candidate} />
        )}

        <Link href={`/organisations/${organisationId}/candidates`}>
          Back to candidates
        </Link>
      </section>
    </main>
  );
}

function CandidateReadOnly({ candidate }: { candidate: Candidate }) {
  const values = [
    ["Email", candidate.email],
    ["Phone", candidate.phone],
    ["Occupation", candidate.occupation],
    ["Location", candidate.location],
    ["Availability", candidate.availability],
    ["Notes", candidate.notes],
  ];

  return (
    <div>
      <p>Your Organisation role has read-only Candidate access.</p>
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

function DetailStatus() {
  return (
    <main>
      <section className="foundation-card candidate-card">
        <h1>Candidate</h1>
        <p role="status">Loading candidate…</p>
      </section>
    </main>
  );
}

function DetailError({ status }: { status: number | null }) {
  return (
    <main>
      <section className="foundation-card candidate-card">
        <h1>{status === 401 ? "Session expired" : "Candidate unavailable"}</h1>
        <p role="alert">
          {status === 401
            ? "Your session is no longer valid. Please log in again."
            : "This candidate is unavailable or you no longer have access."}
        </p>
        <Link href={status === 401 ? "/login" : "/"}>
          {status === 401 ? "Login" : "Back to your organisations"}
        </Link>
      </section>
    </main>
  );
}
