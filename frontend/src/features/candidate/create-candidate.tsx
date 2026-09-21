"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { FormEvent, useEffect, useState } from "react";

import { FormError } from "@/components/forms/form-error";
import { useAuth } from "@/features/identity/auth-context";
import {
  getOrganisation,
  type Organisation,
} from "@/features/organisation/api";
import { ApiError } from "@/lib/api/client";

import { createCandidate } from "./api";
import { CandidateFormFields, candidateInput } from "./candidate-form-fields";

export function CreateCandidate({
  organisationId,
}: {
  organisationId: number;
}) {
  const { isLoading: isAuthLoading, user } = useAuth();
  const router = useRouter();
  const [organisationResult, setOrganisationResult] = useState<{
    error: unknown;
    key: string;
    organisation: Organisation | null;
  } | null>(null);
  const [submitError, setSubmitError] = useState<unknown>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const userId = user?.id;
  const resultKey = userId === undefined ? null : `${userId}:${organisationId}`;

  useEffect(() => {
    if (isAuthLoading || resultKey === null) {
      return;
    }

    let active = true;

    void getOrganisation(organisationId)
      .then((organisation) => {
        if (active) {
          setOrganisationResult({ error: null, key: resultKey, organisation });
        }
      })
      .catch((error: unknown) => {
        if (active) {
          setOrganisationResult({ error, key: resultKey, organisation: null });
        }
      });

    return () => {
      active = false;
    };
  }, [isAuthLoading, organisationId, resultKey]);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSubmitError(null);
    setIsSubmitting(true);

    try {
      const candidate = await createCandidate(
        organisationId,
        candidateInput(event.currentTarget),
      );
      router.push(
        `/organisations/${organisationId}/candidates/${candidate.id}`,
      );
    } catch (error) {
      setSubmitError(error);
    } finally {
      setIsSubmitting(false);
    }
  }

  const currentResult =
    organisationResult?.key === resultKey ? organisationResult : null;

  if (isAuthLoading) {
    return <CreateStatus message="Loading candidate form…" />;
  }

  if (user === null) {
    return <CreateError status={401} />;
  }

  if (currentResult === null) {
    return <CreateStatus message="Loading candidate form…" />;
  }

  if (currentResult.error !== null) {
    const status =
      currentResult.error instanceof ApiError
        ? currentResult.error.status
        : null;

    return <CreateError status={status} />;
  }

  const organisation = currentResult.organisation;

  if (organisation === null) {
    return <CreateError status={null} />;
  }

  if (!["admin", "recruiter"].includes(organisation.membership.role)) {
    return (
      <main>
        <section className="foundation-card candidate-card">
          <h1>Candidate creation unavailable</h1>
          <p role="alert">Your Organisation role cannot create candidates.</p>
          <Link href={`/organisations/${organisationId}/candidates`}>
            Back to candidates
          </Link>
        </section>
      </main>
    );
  }

  return (
    <main>
      <section className="foundation-card candidate-card">
        <p className="eyebrow">{organisation.name}</p>
        <h1>Add candidate</h1>
        <form onSubmit={handleSubmit}>
          <CandidateFormFields />
          <FormError error={submitError} />
          <button disabled={isSubmitting} type="submit">
            {isSubmitting ? "Creating…" : "Create candidate"}
          </button>
        </form>
        <Link href={`/organisations/${organisationId}/candidates`}>
          Back to candidates
        </Link>
      </section>
    </main>
  );
}

function CreateStatus({ message }: { message: string }) {
  return (
    <main>
      <section className="foundation-card candidate-card">
        <h1>Add candidate</h1>
        <p role="status">{message}</p>
      </section>
    </main>
  );
}

function CreateError({ status }: { status: number | null }) {
  return (
    <main>
      <section className="foundation-card candidate-card">
        <h1>
          {status === 401 ? "Session expired" : "Organisation unavailable"}
        </h1>
        <p role="alert">
          {status === 401
            ? "Your session is no longer valid. Please log in again."
            : "This organisation is unavailable or you no longer have access."}
        </p>
        <Link href={status === 401 ? "/login" : "/"}>
          {status === 401 ? "Login" : "Back to your organisations"}
        </Link>
      </section>
    </main>
  );
}
