"use client";

import { useEffect, useState } from "react";

import { FormError } from "@/components/forms/form-error";

import {
  getMatchExplanation,
  requestMatchExplanation,
  type MatchExplanation,
} from "./api";

export function AiMatchExplanation({
  candidateId,
  jobId,
  organisationId,
}: {
  candidateId: number;
  jobId: number;
  organisationId: number;
}) {
  const [explanation, setExplanation] = useState<MatchExplanation | null>(null);
  const [error, setError] = useState<unknown>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    if (!explanation || !["queued", "processing"].includes(explanation.status))
      return;
    const timer = window.setTimeout(() => {
      void getMatchExplanation(
        organisationId,
        jobId,
        candidateId,
        explanation.id,
      )
        .then(setExplanation)
        .catch(setError);
    }, 1500);
    return () => window.clearTimeout(timer);
  }, [candidateId, explanation, jobId, organisationId]);

  async function generate() {
    setBusy(true);
    setError(null);
    try {
      setExplanation(
        await requestMatchExplanation(organisationId, jobId, candidateId),
      );
    } catch (requestError) {
      setError(requestError);
    } finally {
      setBusy(false);
    }
  }

  if (explanation?.status === "ready") {
    return (
      <aside aria-label="AI match explanation" className="ai-explanation">
        <strong>AI-generated explanation</strong>
        {explanation.stale ? (
          <p role="alert">
            Explanation stale. Generate a new explanation for the current
            factors.
          </p>
        ) : null}
        <p>{explanation.summary}</p>
        <ul>
          {explanation.factors?.map((factor, index) => (
            <li key={`${factor.type}-${index}`}>{factor.explanation}</li>
          ))}
        </ul>
        <small>{explanation.disclaimer}</small>
      </aside>
    );
  }

  return (
    <div className="ai-explanation">
      {explanation && ["queued", "processing"].includes(explanation.status) ? (
        <p aria-live="polite">AI explanation processing…</p>
      ) : explanation?.status === "failed" ? (
        <p role="alert">
          AI explanation failed. Deterministic match factors remain available.
        </p>
      ) : (
        <button disabled={busy} onClick={() => void generate()} type="button">
          {busy ? "Requesting explanation…" : "Generate AI explanation"}
        </button>
      )}
      <FormError error={error} />
    </div>
  );
}
