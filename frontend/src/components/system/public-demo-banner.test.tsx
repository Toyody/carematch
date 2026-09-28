import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";

vi.mock("@/lib/public-demo", () => ({ isPublicDemo: true }));

import { PublicDemoBanner } from "./public-demo-banner";

describe("PublicDemoBanner", () => {
  it("warns visitors to use synthetic data", () => {
    render(<PublicDemoBanner />);

    expect(
      screen.getByText("Portfolio demo — use synthetic data only."),
    ).toBeVisible();
  });
});
