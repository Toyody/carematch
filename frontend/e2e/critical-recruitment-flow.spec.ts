import { expect, test } from "@playwright/test";

test("recruiter completes the critical hiring workflow and sees the dashboard", async ({
  page,
}) => {
  const unique = Date.now();
  const candidateName = `Ada E2E ${unique}`;
  const jobTitle = `Registered Nurse E2E ${unique}`;
  const organisationName = `CareMatch E2E ${unique}`;

  const sessionDiscovery = page.waitForResponse((response) =>
    response.url().endsWith("/api/v1/auth/me"),
  );
  await page.goto("/register");
  await sessionDiscovery;
  await page.waitForLoadState("networkidle");
  await page.getByLabel("Name").fill("E2E Recruiter");
  await page.getByLabel("Email").fill(`e2e-${unique}@example.test`);
  await page
    .getByLabel("Password", { exact: true })
    .fill("carematch-e2e-password");
  await page.getByLabel("Confirm password").fill("carematch-e2e-password");
  await page.getByRole("button", { name: "Register" }).click();
  await expect(
    page.getByText(`Signed in as e2e-${unique}@example.test.`),
  ).toBeVisible();

  await page.getByRole("link", { name: "Continue to CareMatch" }).click();
  await expect(
    page.getByRole("heading", { name: "Your organisations" }),
  ).toBeVisible();
  await page.getByLabel("Organisation name").fill(organisationName);
  await page.getByRole("button", { name: "Create organisation" }).click();
  const organisationLink = page.getByRole("link", { name: organisationName });
  await expect(organisationLink).toBeVisible();
  const organisationPath = await organisationLink.getAttribute("href");
  expect(organisationPath).toMatch(/^\/organisations\/\d+$/);

  await organisationLink.click();
  await page.waitForURL(organisationPath!);
  await expect(
    page.getByRole("heading", { name: organisationName }),
  ).toBeVisible();
  await expect(page.getByRole("heading", { name: "Dashboard" })).toBeVisible();

  await page.getByRole("link", { name: "Manage candidates" }).click();
  await expect(page.getByRole("heading", { name: "Candidates" })).toBeVisible();
  await page.getByRole("link", { name: "Add candidate" }).click();
  await expect(
    page.getByRole("heading", { name: "Add candidate" }),
  ).toBeVisible();
  await page.getByLabel("First name").fill(candidateName);
  await page.getByLabel("Last name").fill("Lovelace");
  await page.getByRole("button", { name: "Create candidate" }).click();
  await expect(
    page.getByRole("heading", { name: `${candidateName} Lovelace` }),
  ).toBeVisible();

  await page.goto(organisationPath!);
  await page.getByRole("link", { name: "Manage jobs" }).click();
  await expect(page.getByRole("heading", { name: "Jobs" })).toBeVisible();
  await page.getByRole("link", { name: "Add job" }).click();
  await expect(page.getByRole("heading", { name: "Add job" })).toBeVisible();
  await page.getByLabel("Title").fill(jobTitle);
  await page.getByRole("button", { name: "Create draft" }).click();
  await expect(page.getByRole("heading", { name: jobTitle })).toBeVisible();
  await page.getByRole("button", { name: "Open job" }).click();
  await expect(page.getByText("Status: open")).toBeVisible();

  await page.goto(organisationPath!);
  await page.getByRole("link", { name: "Manage applications" }).click();
  await expect(
    page.getByRole("heading", { name: "Applications" }),
  ).toBeVisible();
  await page.getByRole("link", { name: "Add application" }).click();
  await expect(
    page.getByRole("heading", { name: "Add application" }),
  ).toBeVisible();
  await page.getByLabel("Open job").selectOption({ label: jobTitle });
  await page
    .getByLabel("Candidate")
    .selectOption({ label: `${candidateName} Lovelace` });
  await page.getByRole("button", { name: "Create application" }).click();
  await expect(page.getByText("applied", { exact: true })).toBeVisible();

  for (const [button, status] of [
    ["Move to Screening", "screening"],
    ["Move to Interview", "interview"],
    ["Move to Offer", "offer"],
    ["Mark as Hired", "hired"],
  ] as const) {
    await page.getByRole("button", { name: button }).click();
    await expect(page.getByText(status, { exact: true })).toBeVisible();
  }

  await expect(
    page.getByText(
      "No pipeline actions are available for this status and role.",
    ),
  ).toBeVisible();
  await expect(
    page.getByRole("heading", { name: "Status history" }),
  ).toBeVisible();
  await expect(
    page
      .getByRole("list", { name: "Application status history" })
      .getByRole("listitem"),
  ).toHaveCount(5);

  await page.goto(organisationPath!);
  const dashboard = page.getByRole("region", { name: "Dashboard" });
  await expect(
    dashboard.getByText("Candidates", { exact: true }).locator(".."),
  ).toContainText("1");
  await expect(
    dashboard.getByText("Open jobs", { exact: true }).locator(".."),
  ).toContainText("1");
  await expect(
    dashboard.getByText("Hired", { exact: true }).locator(".."),
  ).toContainText("1");
  await expect(
    dashboard.getByRole("link", { name: `${candidateName} Lovelace` }).first(),
  ).toBeVisible();
  await expect(dashboard.getByText("Offer to Hired")).toBeVisible();
});
