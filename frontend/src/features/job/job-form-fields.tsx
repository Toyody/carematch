import type { Job } from "./api";

export function JobFormFields({ job }: { job?: Job }) {
  return (
    <>
      <label htmlFor="job-title">Title</label>
      <input
        defaultValue={job?.title}
        id="job-title"
        maxLength={200}
        name="title"
        required
      />
      <label htmlFor="job-occupation">Occupation</label>
      <input
        defaultValue={job?.occupation ?? ""}
        id="job-occupation"
        maxLength={255}
        name="occupation"
      />
      <label htmlFor="job-location">Location</label>
      <input
        defaultValue={job?.location ?? ""}
        id="job-location"
        maxLength={255}
        name="location"
      />
      <label htmlFor="job-employment-type">Employment type</label>
      <input
        defaultValue={job?.employment_type ?? ""}
        id="job-employment-type"
        maxLength={100}
        name="employment_type"
      />
      <label htmlFor="job-opened-at">Opening date</label>
      <input
        defaultValue={dateTimeValue(job?.opened_at)}
        id="job-opened-at"
        name="opened_at"
        type="datetime-local"
      />
      <label htmlFor="job-closes-at">Closing date</label>
      <input
        defaultValue={dateTimeValue(job?.closes_at)}
        id="job-closes-at"
        name="closes_at"
        type="datetime-local"
      />
      <label htmlFor="job-description">Description</label>
      <textarea
        defaultValue={job?.description ?? ""}
        id="job-description"
        maxLength={10000}
        name="description"
        rows={7}
      />
    </>
  );
}

export function jobInput(form: HTMLFormElement) {
  const data = new FormData(form);
  const optional = (name: string) => {
    const value = String(data.get(name) ?? "").trim();
    return value === "" ? null : value;
  };
  const date = (name: string) => {
    const value = optional(name);
    return value === null ? null : new Date(value).toISOString();
  };
  return {
    closes_at: date("closes_at"),
    description: optional("description"),
    employment_type: optional("employment_type"),
    location: optional("location"),
    occupation: optional("occupation"),
    opened_at: date("opened_at"),
    title: String(data.get("title") ?? "").trim(),
  };
}

function dateTimeValue(value: string | null | undefined) {
  if (!value) return "";

  const date = new Date(value);
  const part = (number: number) => String(number).padStart(2, "0");

  return `${date.getFullYear()}-${part(date.getMonth() + 1)}-${part(date.getDate())}T${part(date.getHours())}:${part(date.getMinutes())}`;
}
