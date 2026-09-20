import {
  cleanup,
  fireEvent,
  render,
  screen,
  waitFor,
} from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";

import { ApiError } from "@/lib/api/client";

import {
  createOrganisation,
  listOrganisations,
  type Organisation,
} from "./api";
import { OrganisationPanel } from "./organisation-panel";

vi.mock("./api", () => ({
  createOrganisation: vi.fn(),
  listOrganisations: vi.fn(),
}));

const organisation: Organisation = {
  created_at: "2026-09-20T00:00:00+00:00",
  id: 11,
  membership: { role: "admin" },
  name: "Northside Health",
};

describe("Organisation panel", () => {
  afterEach(() => {
    cleanup();
    vi.clearAllMocks();
  });

  it("shows loading and then active organisations with their roles", async () => {
    vi.mocked(listOrganisations).mockResolvedValue([organisation]);
    render(<OrganisationPanel />);

    expect(screen.getByText("Loading organisations…")).toBeInTheDocument();
    expect(await screen.findByText(organisation.name)).toBeInTheDocument();
    expect(screen.getByText("admin")).toBeInTheDocument();
  });

  it("shows the empty state", async () => {
    vi.mocked(listOrganisations).mockResolvedValue([]);
    render(<OrganisationPanel />);

    expect(
      await screen.findByText("You do not belong to an organisation yet."),
    ).toBeInTheDocument();
  });

  it("creates an organisation and adds it to the list", async () => {
    vi.mocked(listOrganisations).mockResolvedValue([]);
    vi.mocked(createOrganisation).mockResolvedValue(organisation);
    render(<OrganisationPanel />);

    await screen.findByText("You do not belong to an organisation yet.");
    fireEvent.change(screen.getByLabelText("Organisation name"), {
      target: { value: organisation.name },
    });
    fireEvent.click(
      screen.getByRole("button", { name: "Create organisation" }),
    );

    expect(await screen.findByText(organisation.name)).toBeInTheDocument();
    expect(createOrganisation).toHaveBeenCalledWith({
      name: organisation.name,
    });
  });

  it("shows organisation validation errors", async () => {
    vi.mocked(listOrganisations).mockResolvedValue([]);
    vi.mocked(createOrganisation).mockRejectedValue(
      new ApiError(422, "The given data was invalid.", {
        name: ["The name field is required."],
      }),
    );
    render(<OrganisationPanel />);

    await screen.findByText("You do not belong to an organisation yet.");
    fireEvent.change(screen.getByLabelText("Organisation name"), {
      target: { value: "Invalid" },
    });
    fireEvent.click(
      screen.getByRole("button", { name: "Create organisation" }),
    );

    await waitFor(() => {
      expect(
        screen.getByText("The name field is required."),
      ).toBeInTheDocument();
    });
  });
});
