import type { Candidate } from "./api";

export function CandidateFormFields({ candidate }: { candidate?: Candidate }) {
  return (
    <>
      <label htmlFor="candidate-first-name">First name</label>
      <input
        defaultValue={candidate?.first_name}
        id="candidate-first-name"
        maxLength={100}
        name="first_name"
        required
      />

      <label htmlFor="candidate-last-name">Last name</label>
      <input
        defaultValue={candidate?.last_name}
        id="candidate-last-name"
        maxLength={100}
        name="last_name"
        required
      />

      <label htmlFor="candidate-email">Email</label>
      <input
        defaultValue={candidate?.email ?? ""}
        id="candidate-email"
        maxLength={255}
        name="email"
        type="email"
      />

      <label htmlFor="candidate-phone">Phone</label>
      <input
        defaultValue={candidate?.phone ?? ""}
        id="candidate-phone"
        maxLength={50}
        name="phone"
      />

      <label htmlFor="candidate-occupation">Occupation</label>
      <input
        defaultValue={candidate?.occupation ?? ""}
        id="candidate-occupation"
        maxLength={255}
        name="occupation"
      />

      <label htmlFor="candidate-location">Location</label>
      <input
        defaultValue={candidate?.location ?? ""}
        id="candidate-location"
        maxLength={255}
        name="location"
      />

      <fieldset>
        <legend>Optional matching coordinates</legend>
        <p>
          Use approximate locality coordinates, not a private street address.
        </p>
        <label htmlFor="candidate-latitude">Latitude</label>
        <input
          defaultValue={candidate?.latitude ?? ""}
          id="candidate-latitude"
          max="90"
          min="-90"
          name="latitude"
          step="0.000001"
          type="number"
        />
        <label htmlFor="candidate-longitude">Longitude</label>
        <input
          defaultValue={candidate?.longitude ?? ""}
          id="candidate-longitude"
          max="180"
          min="-180"
          name="longitude"
          step="0.000001"
          type="number"
        />
      </fieldset>

      <label htmlFor="candidate-availability">Availability</label>
      <input
        defaultValue={candidate?.availability ?? ""}
        id="candidate-availability"
        maxLength={255}
        name="availability"
      />

      <label htmlFor="candidate-notes">Notes</label>
      <textarea
        defaultValue={candidate?.notes ?? ""}
        id="candidate-notes"
        maxLength={5000}
        name="notes"
        rows={5}
      />
    </>
  );
}

export function candidateInput(form: HTMLFormElement) {
  const data = new FormData(form);
  const optional = (name: string) => {
    const value = String(data.get(name) ?? "").trim();

    return value === "" ? null : value;
  };
  const coordinate = (name: string) => {
    const value = optional(name);
    return value === null ? null : Number(value);
  };

  return {
    availability: optional("availability"),
    email: optional("email"),
    first_name: String(data.get("first_name") ?? ""),
    last_name: String(data.get("last_name") ?? ""),
    latitude: coordinate("latitude"),
    location: optional("location"),
    longitude: coordinate("longitude"),
    notes: optional("notes"),
    occupation: optional("occupation"),
    phone: optional("phone"),
  };
}
