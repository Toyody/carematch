import { cleanup, render, screen } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import {
  listJobQualificationRequirements,
  listQualificationDefinitions,
} from "./api";
import { JobQualificationRequirements } from "./job-qualification-requirements";

vi.mock("./api", () => ({
  addJobQualificationRequirement: vi.fn(),
  deleteJobQualificationRequirement: vi.fn(),
  listJobQualificationRequirements: vi.fn(),
  listQualificationDefinitions: vi.fn(),
}));
afterEach(() => {
  cleanup();
  vi.clearAllMocks();
});

describe("JobQualificationRequirements", () => {
  it("renders requirements without mutation controls for Hiring Managers", async () => {
    vi.mocked(listQualificationDefinitions).mockResolvedValue([]);
    vi.mocked(listJobQualificationRequirements).mockResolvedValue([
      {
        created_at: "2026-01-01T00:00:00Z",
        id: 3,
        qualification_definition_id: 4,
        qualification_name: "First Aid",
      },
    ]);
    render(
      <JobQualificationRequirements
        jobId={8}
        mayManage={false}
        organisationId={2}
      />,
    );
    expect(await screen.findByText("First Aid")).toBeVisible();
    expect(
      screen.getByText(
        "Your Organisation role has read-only requirement access.",
      ),
    ).toBeVisible();
    expect(
      screen.queryByRole("button", { name: "Remove" }),
    ).not.toBeInTheDocument();
  });
});
