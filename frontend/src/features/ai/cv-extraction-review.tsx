"use client";

import { FormEvent, useEffect, useRef, useState } from "react";

import { FormError } from "@/components/forms/form-error";
import type { Candidate, CandidateDocument } from "@/features/candidate/api";
import { ApiError } from "@/lib/api/client";

import {
  applyCvExtraction,
  getCvExtraction,
  requestCvExtraction,
  type CvExtraction,
  type CvField,
} from "./api";

const fields: Array<{ key: CvField; label: string }> = [
  { key: "first_name", label: "First name" },
  { key: "last_name", label: "Last name" },
  { key: "email", label: "Email" },
  { key: "phone", label: "Phone" },
  { key: "occupation", label: "Occupation" },
  { key: "location", label: "Location" },
];

export function CvExtractionReview({
  candidate,
  document,
  onApplied,
  organisationId,
}: {
  candidate: Candidate;
  document: CandidateDocument;
  onApplied: (candidate: Candidate) => void;
  organisationId: number;
}) {
  const [extraction, setExtraction] = useState<CvExtraction | null>(null);
  const [error, setError] = useState<unknown>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const idempotencyKey = useRef<string | null>(null);

  useEffect(() => {
    if (!extraction || !["queued", "processing"].includes(extraction.status))
      return;
    const timer = window.setTimeout(() => {
      void getCvExtraction(
        organisationId,
        candidate.id,
        document.id,
        extraction.id,
      )
        .then(setExtraction)
        .catch(setError);
    }, 1500);
    return () => window.clearTimeout(timer);
  }, [candidate.id, document.id, extraction, organisationId]);

  async function requestExtraction() {
    setError(null);
    setIsSubmitting(true);
    try {
      idempotencyKey.current ??= crypto.randomUUID();
      setExtraction(
        await requestCvExtraction(
          organisationId,
          candidate.id,
          document.id,
          idempotencyKey.current,
        ),
      );
    } catch (requestError) {
      setError(requestError);
    } finally {
      setIsSubmitting(false);
    }
  }

  async function apply(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!extraction?.draft) return;
    const data = new FormData(event.currentTarget);
    const selected: Partial<Record<CvField, string | null>> = {};
    for (const field of fields) {
      if (data.get(`apply_${field.key}`) !== "on") continue;
      const value = String(data.get(field.key) ?? "").trim();
      selected[field.key] = value === "" ? null : value;
    }
    if (Object.keys(selected).length === 0) {
      setError(new ApiError(422, "Select at least one reviewed field."));
      return;
    }
    setError(null);
    setIsSubmitting(true);
    try {
      const updated = await applyCvExtraction(
        organisationId,
        candidate.id,
        document.id,
        extraction.id,
        selected,
      );
      onApplied(updated);
      setExtraction({ ...extraction, status: "applied" });
    } catch (applyError) {
      setError(applyError);
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div className="ai-review">
      <p>
        <strong>AI-assisted CV extraction</strong> — sends this private document
        to the configured external provider. Human review is required.
      </p>
      {extraction === null ? (
        <button
          disabled={isSubmitting}
          onClick={() => void requestExtraction()}
          type="button"
        >
          {isSubmitting
            ? "Requesting AI extraction…"
            : `Extract ${document.original_name} with AI`}
        </button>
      ) : ["queued", "processing"].includes(extraction.status) ? (
        <p aria-live="polite">
          AI processing. This page will check for a review draft.
        </p>
      ) : extraction.status === "failed" ? (
        <p role="alert">
          AI extraction failed ({extraction.failure_code ?? "unavailable"}).
          Manual Candidate editing remains available.
        </p>
      ) : extraction.status === "applied" ? (
        <p aria-live="polite">Reviewed AI suggestions applied.</p>
      ) : extraction.draft ? (
        <form onSubmit={apply}>
          <p>
            Ready for review. Select and edit only the values you want to apply.
          </p>
          <div className="ai-review-grid">
            <strong>Apply?</strong>
            <strong>Field</strong>
            <strong>Current value</strong>
            <strong>AI suggestion</strong>
            {fields.map((field) => (
              <div className="ai-review-row" key={field.key}>
                <input
                  aria-label={`Apply ${field.label}`}
                  name={`apply_${field.key}`}
                  type="checkbox"
                />
                <label htmlFor={`ai-${extraction.id}-${field.key}`}>
                  {field.label}
                </label>
                <span>{candidate[field.key] ?? "Not provided"}</span>
                <input
                  defaultValue={extraction.draft?.[field.key] ?? ""}
                  id={`ai-${extraction.id}-${field.key}`}
                  name={field.key}
                />
              </div>
            ))}
          </div>
          <button disabled={isSubmitting} type="submit">
            {isSubmitting
              ? "Applying reviewed fields…"
              : "Apply selected reviewed fields"}
          </button>
        </form>
      ) : null}
      <FormError error={error} />
    </div>
  );
}
