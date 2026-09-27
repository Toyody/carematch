import { cleanup, render, screen } from "@testing-library/react";
import { afterEach, describe, expect, it } from "vitest";

import { ApiError } from "@/lib/api/client";

import { FormError } from "./form-error";

describe("FormError", () => {
  afterEach(cleanup);

  it("shows validation details without repeating the response summary", () => {
    render(
      <FormError
        error={
          new ApiError(422, "The email field is invalid.", {
            email: ["The email field is invalid."],
          })
        }
      />,
    );

    expect(screen.getByRole("alert")).toHaveTextContent(
      "The email field is invalid.",
    );
    expect(screen.getAllByText("The email field is invalid.")).toHaveLength(1);
  });

  it("shows the public error message when there are no field details", () => {
    render(
      <FormError error={new ApiError(419, "Your session has expired.")} />,
    );

    expect(screen.getByRole("alert")).toHaveTextContent(
      "Your session has expired.",
    );
  });
});
