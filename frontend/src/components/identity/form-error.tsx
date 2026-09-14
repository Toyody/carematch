import { ApiError } from "@/lib/api/client";

export function FormError({ error }: { error: unknown }) {
  if (error === null) {
    return null;
  }

  if (!(error instanceof ApiError)) {
    return (
      <p className="form-message form-message-error" role="alert">
        The request could not be completed. Please try again.
      </p>
    );
  }

  const validationMessages = Object.values(error.validationErrors).flat();

  return (
    <div className="form-message form-message-error" role="alert">
      <p>{error.message}</p>
      {validationMessages.length > 0 ? (
        <ul>
          {validationMessages.map((message) => (
            <li key={message}>{message}</li>
          ))}
        </ul>
      ) : null}
    </div>
  );
}
