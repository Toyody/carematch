import http from "k6/http";
import { check } from "k6";

export const options = {
  noCookiesReset: true,
  scenarios: {
    representative_reads: {
      executor: "constant-vus",
      vus: 3,
      duration: "10s",
    },
  },
  thresholds: {
    http_req_failed: ["rate<0.02"],
    checks: ["rate>0.99"],
  },
};

const baseUrl = __ENV.BASE_URL;
let context;

function authenticate() {
  const browserHeaders = { Accept: "application/json", Origin: baseUrl, Referer: `${baseUrl}/` };
  const csrf = http.get(`${baseUrl}/sanctum/csrf-cookie`, { headers: browserHeaders });
  check(csrf, { "CSRF bootstrap succeeds": (response) => response.status === 204 });

  const token = decodeURIComponent(csrf.cookies["XSRF-TOKEN"][0].value);
  const login = http.post(
    `${baseUrl}/api/v1/auth/login`,
    JSON.stringify({ email: __ENV.PERFORMANCE_EMAIL, password: __ENV.PERFORMANCE_PASSWORD }),
    {
      headers: {
        ...browserHeaders,
        "Content-Type": "application/json",
        "X-XSRF-TOKEN": token,
      },
    },
  );
  check(login, { "synthetic account login succeeds": (response) => response.status === 200 });

  const organisations = http.get(`${baseUrl}/api/v1/organisations`, { headers: browserHeaders });
  check(organisations, { "organisation discovery succeeds": (response) => response.status === 200 });
  const organisationId = organisations.json("data.0.id");

  const jobs = http.get(`${baseUrl}/api/v1/organisations/${organisationId}/jobs?per_page=10`, {
    headers: browserHeaders,
  });
  check(jobs, { "job discovery succeeds": (response) => response.status === 200 });

  return { organisationId, jobId: jobs.json("data.0.id") };
}

export default function () {
  context ??= authenticate();

  const headers = { Accept: "application/json", Origin: baseUrl, Referer: `${baseUrl}/` };
  const reads = [
    ["health", `${baseUrl}/api/v1/health`],
    ["dashboard", `${baseUrl}/api/v1/organisations/${context.organisationId}/dashboard`],
    ["analytics", `${baseUrl}/api/v1/organisations/${context.organisationId}/analytics`],
    ["matching", `${baseUrl}/api/v1/organisations/${context.organisationId}/jobs/${context.jobId}/matches`],
  ];

  for (const [name, url] of reads) {
    const response = http.get(url, { headers });
    check(response, { [`${name} read succeeds`]: (result) => result.status === 200 });
  }
}
