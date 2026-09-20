import { beforeEach, describe, expect, it, vi } from "vitest";

import { apiRequest } from "@/lib/api/client";

import { createOrganisation, listOrganisations } from "./api";

vi.mock("@/lib/api/client", () => ({
  apiRequest: vi.fn(),
}));

const organisation = {
  created_at: "2026-09-20T00:00:00+00:00",
  id: 11,
  membership: { role: "admin" as const },
  name: "Northside Health",
};

describe("Organisation API", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("lists organisations through the shared session client", async () => {
    vi.mocked(apiRequest).mockResolvedValue({ data: [organisation] });

    await expect(listOrganisations()).resolves.toEqual([organisation]);
    expect(apiRequest).toHaveBeenCalledWith("/organisations");
  });

  it("creates an organisation through the shared CSRF client", async () => {
    vi.mocked(apiRequest).mockResolvedValue({ data: organisation });

    await expect(
      createOrganisation({ name: organisation.name }),
    ).resolves.toEqual(organisation);
    expect(apiRequest).toHaveBeenCalledWith("/organisations", {
      body: { name: organisation.name },
      method: "POST",
      withCsrf: true,
    });
  });
});
