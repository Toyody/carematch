const configuredApiUrl =
  process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000";

const apiUrl = configuredApiUrl.replace(/\/$/, "");

type ValidationErrors = Record<string, string[]>;

interface ErrorPayload {
  errors?: ValidationErrors;
  message?: string;
}

interface RequestOptions {
  body?: unknown;
  method?: "GET" | "PATCH" | "POST";
  withCsrf?: boolean;
}

export class ApiError extends Error {
  constructor(
    public readonly status: number,
    message: string,
    public readonly validationErrors: ValidationErrors = {},
    public readonly retryAfter: number | null = null,
  ) {
    super(message);
    this.name = "ApiError";
  }
}

export async function apiRequest<T>(
  path: string,
  { body, method = "GET", withCsrf = false }: RequestOptions = {},
): Promise<T> {
  const headers = new Headers({ Accept: "application/json" });

  if (withCsrf) {
    headers.set("X-XSRF-TOKEN", await initialiseCsrf());
  }

  if (body !== undefined) {
    headers.set("Content-Type", "application/json");
  }

  const response = await fetch(`${apiUrl}/api/v1${path}`, {
    body: body === undefined ? undefined : JSON.stringify(body),
    credentials: "include",
    headers,
    method,
  });

  if (!response.ok) {
    throw await createApiError(response);
  }

  if (response.status === 204) {
    return undefined as T;
  }

  return (await response.json()) as T;
}

async function initialiseCsrf(): Promise<string> {
  const response = await fetch(`${apiUrl}/sanctum/csrf-cookie`, {
    credentials: "include",
    headers: { Accept: "application/json" },
    method: "GET",
  });

  if (!response.ok) {
    throw await createApiError(response);
  }

  const encodedToken = readCookie("XSRF-TOKEN");

  if (encodedToken === null) {
    throw new ApiError(
      419,
      "Your session could not be initialised. Please refresh and try again.",
    );
  }

  try {
    return decodeURIComponent(encodedToken);
  } catch {
    throw new ApiError(
      419,
      "Your session could not be initialised. Please refresh and try again.",
    );
  }
}

function readCookie(name: string): string | null {
  const prefix = `${name}=`;

  for (const cookie of document.cookie.split(";")) {
    const value = cookie.trim();

    if (value.startsWith(prefix)) {
      return value.slice(prefix.length);
    }
  }

  return null;
}

async function createApiError(response: Response): Promise<ApiError> {
  const payload = await readErrorPayload(response);
  const retryAfter = parseRetryAfter(response.headers.get("Retry-After"));

  return new ApiError(
    response.status,
    publicErrorMessage(response.status, payload.message, retryAfter),
    payload.errors ?? {},
    retryAfter,
  );
}

async function readErrorPayload(response: Response): Promise<ErrorPayload> {
  const contentType = response.headers.get("Content-Type") ?? "";

  if (!contentType.includes("application/json")) {
    return {};
  }

  try {
    return (await response.json()) as ErrorPayload;
  } catch {
    return {};
  }
}

function parseRetryAfter(value: string | null): number | null {
  if (value === null || !/^\d+$/.test(value)) {
    return null;
  }

  return Number.parseInt(value, 10);
}

function publicErrorMessage(
  status: number,
  backendMessage: string | undefined,
  retryAfter: number | null,
): string {
  if (status === 419) {
    return "Your session has expired. Please refresh and try again.";
  }

  if (status === 429) {
    return retryAfter === null
      ? "Too many attempts. Please try again later."
      : `Too many attempts. Please try again in ${retryAfter} seconds.`;
  }

  if ([401, 409, 422].includes(status) && backendMessage) {
    return backendMessage;
  }

  return "The request could not be completed. Please try again.";
}
