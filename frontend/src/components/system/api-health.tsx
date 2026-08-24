"use client";

import { useEffect, useState } from "react";

type HealthStatus = "checking" | "ok" | "error";

interface HealthResponse {
  data: {
    service: string;
    status: "ok";
  };
}

const apiUrl = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000";

export function ApiHealth() {
  const [status, setStatus] = useState<HealthStatus>("checking");

  useEffect(() => {
    const controller = new AbortController();

    async function checkHealth() {
      try {
        const response = await fetch(`${apiUrl}/api/v1/health`, {
          headers: { Accept: "application/json" },
          signal: controller.signal,
        });

        if (!response.ok) {
          throw new Error("The API health check failed.");
        }

        const payload = (await response.json()) as HealthResponse;
        setStatus(payload.data.status === "ok" ? "ok" : "error");
      } catch (error) {
        if (!(error instanceof DOMException && error.name === "AbortError")) {
          setStatus("error");
        }
      }
    }

    void checkHealth();

    return () => controller.abort();
  }, []);

  const message = {
    checking: "Checking API connectivity…",
    ok: "API connected",
    error: "API unavailable",
  }[status];

  return (
    <p className="api-health" data-status={status} role="status">
      {message}
    </p>
  );
}
