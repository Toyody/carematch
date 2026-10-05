import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";

import type { Candidate, CandidateDocument } from "@/features/candidate/api";

import {
  applyCvExtraction,
  requestCvExtraction,
  requestMatchExplanation,
} from "./api";
import { CvExtractionReview } from "./cv-extraction-review";
import { AiMatchExplanation } from "./match-explanation";
import { ApiError } from "@/lib/api/client";

vi.mock("./api", () => ({
  applyCvExtraction: vi.fn(),
  getCvExtraction: vi.fn(),
  getMatchExplanation: vi.fn(),
  requestCvExtraction: vi.fn(),
  requestMatchExplanation: vi.fn(),
}));

const candidate: Candidate = {
  availability: null,
  created_at: "",
  email: "current@example.test",
  first_name: "Current",
  id: 9,
  last_name: "Candidate",
  location: "Current location",
  notes: null,
  occupation: "Nurse",
  phone: null,
  updated_at: "",
};
const document: CandidateDocument = {
  created_at: "",
  id: 3,
  mime_type: "application/pdf",
  original_name: "synthetic-cv.pdf",
  size_bytes: 200,
  updated_at: "",
  uploaded_by_user_id: 1,
};

describe("AI-assisted workflows", () => {
  beforeEach(() => vi.clearAllMocks());

  it("requires editable selective human review before applying CV fields", async () => {
    vi.mocked(requestCvExtraction).mockResolvedValue({
      applied_at: null,
      candidate_document_id: 3,
      candidate_id: 9,
      created_at: "",
      draft: {
        email: "suggested@example.test",
        first_name: "Suggested",
        last_name: "Candidate",
        location: null,
        occupation: "Registered Nurse",
        phone: null,
      },
      failure_code: null,
      id: 12,
      prompt_version: "cv_extraction_prompt_v1",
      schema_version: "cv_extraction_schema_v1",
      status: "review_ready",
    });
    vi.mocked(applyCvExtraction).mockResolvedValue({
      ...candidate,
      first_name: "Reviewed",
    });
    const applied = vi.fn();
    render(
      <CvExtractionReview
        candidate={candidate}
        document={document}
        onApplied={applied}
        organisationId={4}
      />,
    );
    fireEvent.click(
      screen.getByRole("button", { name: /Extract synthetic-cv.pdf with AI/ }),
    );
    expect(await screen.findByText(/Ready for review/)).toBeVisible();
    expect(screen.getByText("Current")).toBeVisible();
    expect(applyCvExtraction).not.toHaveBeenCalled();
    fireEvent.click(screen.getByLabelText("Apply First name"));
    fireEvent.change(screen.getByLabelText("First name"), {
      target: { value: "Reviewed" },
    });
    fireEvent.click(
      screen.getByRole("button", { name: "Apply selected reviewed fields" }),
    );
    await waitFor(() =>
      expect(applyCvExtraction).toHaveBeenCalledWith(4, 9, 3, 12, {
        first_name: "Reviewed",
      }),
    );
    expect(applied).toHaveBeenCalledWith(
      expect.objectContaining({ first_name: "Reviewed" }),
    );
  });

  it("keeps the deterministic disclaimer visible with a generated explanation", async () => {
    vi.mocked(requestMatchExplanation).mockResolvedValue({
      candidate_id: 9,
      created_at: "",
      disclaimer:
        "AI-generated explanation based on deterministic match factors. It does not affect ranking.",
      factors: [
        { explanation: "Qualifications are satisfied.", type: "qualification" },
      ],
      failure_code: null,
      id: 8,
      job_id: 6,
      stale: false,
      status: "ready",
      summary: "A concise explanation.",
    });
    render(<AiMatchExplanation candidateId={9} jobId={6} organisationId={4} />);
    fireEvent.click(
      screen.getByRole("button", { name: "Generate AI explanation" }),
    );
    expect(await screen.findByText("A concise explanation.")).toBeVisible();
    expect(screen.getByText(/does not affect ranking/)).toBeVisible();
  });

  it("reuses the CV idempotency key when a failed dispatch is retried", async () => {
    vi.mocked(requestCvExtraction)
      .mockRejectedValueOnce(new ApiError(503, "AI assistance is not enabled."))
      .mockResolvedValueOnce({
        applied_at: null,
        candidate_document_id: 3,
        candidate_id: 9,
        created_at: "",
        draft: null,
        failure_code: null,
        id: 12,
        prompt_version: "cv_extraction_prompt_v1",
        schema_version: "cv_extraction_schema_v1",
        status: "queued",
      });
    render(
      <CvExtractionReview
        candidate={candidate}
        document={document}
        onApplied={vi.fn()}
        organisationId={4}
      />,
    );
    const button = screen.getByRole("button", {
      name: /Extract synthetic-cv.pdf with AI/,
    });
    fireEvent.click(button);
    expect(
      await screen.findByText("AI assistance is not enabled."),
    ).toBeVisible();
    fireEvent.click(button);
    await waitFor(() => expect(requestCvExtraction).toHaveBeenCalledTimes(2));
    expect(vi.mocked(requestCvExtraction).mock.calls[0]?.[3]).toBe(
      vi.mocked(requestCvExtraction).mock.calls[1]?.[3],
    );
  });
});
