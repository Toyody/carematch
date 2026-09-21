import { cleanup, render, screen } from "@testing-library/react";
import { afterEach, describe, expect, it } from "vitest";

import type { Organisation } from "./api";
import { OrganisationSelector } from "./organisation-selector";

const organisations: Organisation[] = [
  {
    created_at: "2026-09-20T00:00:00+00:00",
    id: 11,
    membership: { role: "admin" },
    name: "Northside Health",
  },
  {
    created_at: "2026-09-20T00:00:00+00:00",
    id: 22,
    membership: { role: "recruiter" },
    name: "Southside Care",
  },
];

describe("OrganisationSelector", () => {
  afterEach(cleanup);

  it("shows active organisations, roles, and an accessible current workspace", () => {
    render(
      <OrganisationSelector
        currentOrganisationId={organisations[0].id}
        organisations={organisations}
      />,
    );

    expect(screen.getByText(/admin/)).toBeInTheDocument();
    expect(screen.getByText("recruiter")).toBeInTheDocument();
    expect(
      screen.getByRole("link", { name: "Northside Health" }),
    ).toHaveAttribute("aria-current", "page");
    expect(screen.getByText("admin · Current")).toBeInTheDocument();
  });

  it("navigates by organisation route rather than storing selected state", () => {
    render(<OrganisationSelector organisations={organisations} />);

    expect(
      screen.getByRole("link", { name: "Southside Care" }),
    ).toHaveAttribute("href", "/organisations/22");
  });

  it("keeps a single organisation as an explicit workspace link", () => {
    render(<OrganisationSelector organisations={[organisations[0]]} />);

    expect(
      screen.getByRole("link", { name: "Northside Health" }),
    ).toHaveAttribute("href", "/organisations/11");
  });

  it("keeps the zero-organisation state valid", () => {
    render(<OrganisationSelector organisations={[]} />);

    expect(
      screen.getByText("You do not belong to an organisation yet."),
    ).toBeInTheDocument();
  });
});
