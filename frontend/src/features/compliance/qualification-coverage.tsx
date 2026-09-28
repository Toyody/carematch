"use client";

import { useEffect, useState } from "react";
import {
  getQualificationCoverage,
  type QualificationCoverage as Coverage,
} from "./api";

export function QualificationCoverage({
  candidateId,
  jobId,
  organisationId,
}: {
  candidateId: number;
  jobId: number;
  organisationId: number;
}) {
  const [coverage, setCoverage] = useState<Coverage | null>(null);
  const [failed, setFailed] = useState(false);
  useEffect(() => {
    let active = true;
    void getQualificationCoverage(organisationId, jobId, candidateId)
      .then((result) => {
        if (active) setCoverage(result);
      })
      .catch(() => {
        if (active) setFailed(true);
      });
    return () => {
      active = false;
    };
  }, [candidateId, jobId, organisationId]);

  return (
    <section aria-labelledby="qualification-coverage-heading">
      <h2 id="qualification-coverage-heading">
        Qualification requirement coverage
      </h2>
      {!coverage && !failed ? (
        <p role="status">Checking qualification coverage…</p>
      ) : null}
      {failed ? (
        <p role="alert">Qualification coverage is unavailable.</p>
      ) : null}
      {coverage ? (
        <>
          <p>
            Overall: <strong>{overallLabel(coverage.status)}</strong>
          </p>
          <p>
            This compares Organisation-recorded qualifications and does not
            certify legal or regulatory compliance.
          </p>
          {coverage.requirements.length === 0 ? (
            <p>This job has no recorded qualification requirements.</p>
          ) : (
            <ul>
              {coverage.requirements.map((item) => (
                <li key={item.qualification_definition_id}>
                  <strong>{item.name}</strong>: {requirementLabel(item.status)}
                  {item.expires_on ? ` (expires ${item.expires_on})` : ""}
                </li>
              ))}
            </ul>
          )}
        </>
      ) : null}
    </section>
  );
}

function overallLabel(status: Coverage["status"]) {
  if (status === "satisfied") return "Requirements satisfied";
  if (status === "attention_required") return "Attention required";
  return "Requirements not satisfied";
}

function requirementLabel(status: Coverage["requirements"][number]["status"]) {
  return status === "valid"
    ? "Valid"
    : status === "expiring"
      ? "Expiring soon"
      : status === "expired"
        ? "Expired"
        : "Missing";
}
