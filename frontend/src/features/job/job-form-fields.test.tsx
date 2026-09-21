import { cleanup, render, screen } from "@testing-library/react";
import { afterEach, describe, expect, it } from "vitest";
import type { Job } from "./api";
import { JobFormFields, jobInput } from "./job-form-fields";

const job: Job = {
  closes_at: "2026-11-01T00:00:00+00:00",
  created_at: "2026-09-21T00:00:00+00:00",
  description: null,
  employment_type: null,
  id: 9,
  location: null,
  occupation: null,
  opened_at: "2026-10-01T00:00:00+00:00",
  status: "draft",
  title: "Nurse",
  updated_at: "2026-09-21T00:00:00+00:00",
};

describe("JobFormFields", () => {
  afterEach(cleanup);

  it("round-trips API timestamps through datetime-local without changing the instant", () => {
    const { container } = render(
      <form>
        <JobFormFields job={job} />
      </form>,
    );
    const form = container.querySelector("form");

    expect(form).not.toBeNull();
    expect(screen.getByLabelText("Opening date")).toHaveValue(
      localDateTime(job.opened_at as string),
    );
    expect(jobInput(form as HTMLFormElement)).toMatchObject({
      closes_at: new Date(job.closes_at as string).toISOString(),
      opened_at: new Date(job.opened_at as string).toISOString(),
    });
  });
});

function localDateTime(value: string) {
  const date = new Date(value);
  const part = (number: number) => String(number).padStart(2, "0");

  return `${date.getFullYear()}-${part(date.getMonth() + 1)}-${part(date.getDate())}T${part(date.getHours())}:${part(date.getMinutes())}`;
}
