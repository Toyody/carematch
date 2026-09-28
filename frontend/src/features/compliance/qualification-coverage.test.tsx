import { cleanup, render, screen } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { getQualificationCoverage } from "./api";
import { QualificationCoverage } from "./qualification-coverage";

vi.mock("./api", () => ({ getQualificationCoverage: vi.fn() }));
afterEach(() => {
  cleanup();
  vi.clearAllMocks();
});

describe("QualificationCoverage", () => {
  it("shows accessible requirement labels and the non-legal disclaimer", async () => {
    vi.mocked(getQualificationCoverage).mockResolvedValue({
      status: "not_satisfied",
      requirements: [
        {
          candidate_qualification_id: 1,
          expires_on: "2026-10-01",
          name: "First Aid",
          qualification_definition_id: 3,
          status: "expiring",
        },
        {
          candidate_qualification_id: null,
          expires_on: null,
          name: "CPR",
          qualification_definition_id: 4,
          status: "missing",
        },
      ],
    });
    render(
      <QualificationCoverage candidateId={8} jobId={9} organisationId={2} />,
    );
    expect(screen.getByText("Checking qualification coverage…")).toBeVisible();
    expect(await screen.findByText("Requirements not satisfied")).toBeVisible();
    expect(screen.getByText("First Aid").closest("li")).toHaveTextContent(
      "Expiring soon",
    );
    expect(screen.getByText("CPR").closest("li")).toHaveTextContent("Missing");
    expect(
      screen.getByText(/does not certify legal or regulatory compliance/),
    ).toBeVisible();
  });

  it("shows empty and failure states", async () => {
    vi.mocked(getQualificationCoverage).mockResolvedValue({
      status: "satisfied",
      requirements: [],
    });
    const { rerender } = render(
      <QualificationCoverage candidateId={8} jobId={9} organisationId={2} />,
    );
    expect(
      await screen.findByText(
        "This job has no recorded qualification requirements.",
      ),
    ).toBeVisible();
    vi.mocked(getQualificationCoverage).mockRejectedValue(new Error("failed"));
    rerender(
      <QualificationCoverage candidateId={10} jobId={9} organisationId={2} />,
    );
    expect(
      await screen.findByText("Qualification coverage is unavailable."),
    ).toBeVisible();
  });
});
