import {
  cleanup,
  fireEvent,
  render,
  screen,
  waitFor,
} from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

import { ApiError } from "@/lib/api/client";

import {
  deleteCandidateDocument,
  downloadCandidateDocument,
  listCandidateDocuments,
  uploadCandidateDocument,
  type CandidateDocument,
} from "./api";
import { CandidateDocuments } from "./candidate-documents";

vi.mock("./api", () => ({
  deleteCandidateDocument: vi.fn(),
  downloadCandidateDocument: vi.fn(),
  listCandidateDocuments: vi.fn(),
  uploadCandidateDocument: vi.fn(),
}));

const document: CandidateDocument = {
  created_at: "2026-09-24T00:00:00+00:00",
  id: 3,
  mime_type: "application/pdf",
  original_name: "synthetic-resume.pdf",
  size_bytes: 2048,
  updated_at: "2026-09-24T00:00:00+00:00",
  uploaded_by_user_id: 7,
};

describe("CandidateDocuments", () => {
  beforeEach(() => {
    vi.mocked(listCandidateDocuments).mockResolvedValue([]);
    vi.mocked(deleteCandidateDocument).mockResolvedValue(undefined);
    vi.mocked(downloadCandidateDocument).mockResolvedValue(
      new Blob(["pdf"], { type: "application/pdf" }),
    );
    vi.mocked(uploadCandidateDocument).mockResolvedValue(document);
  });

  afterEach(() => {
    cleanup();
    vi.clearAllMocks();
    vi.restoreAllMocks();
  });

  it("shows loading, empty state, and Admin upload controls", async () => {
    render(
      <CandidateDocuments candidateId={9} mayManage organisationId={11} />,
    );

    expect(screen.getByText("Loading documents…")).toBeInTheDocument();
    expect(await screen.findByText("No documents uploaded.")).toBeVisible();
    expect(screen.getByLabelText("Choose document")).toHaveAttribute(
      "accept",
      expect.stringContaining(".pdf"),
    );
    expect(
      screen.getByRole("button", { name: "Upload document" }),
    ).toBeVisible();
  });

  it("renders metadata without any storage key", async () => {
    vi.mocked(listCandidateDocuments).mockResolvedValue([document]);
    const { container } = render(
      <CandidateDocuments candidateId={9} mayManage organisationId={11} />,
    );

    expect(await screen.findByText("synthetic-resume.pdf")).toBeVisible();
    expect(screen.getByText("PDF · 2.0 KiB")).toBeVisible();
    expect(screen.getByRole("button", { name: "Download" })).toBeVisible();
    expect(screen.getByRole("button", { name: "Delete" })).toBeVisible();
    expect(container.textContent).not.toContain("storage_key");
  });

  it("keeps Hiring Manager document access read-only", async () => {
    vi.mocked(listCandidateDocuments).mockResolvedValue([document]);
    render(
      <CandidateDocuments
        candidateId={9}
        mayManage={false}
        organisationId={11}
      />,
    );

    expect(await screen.findByText("synthetic-resume.pdf")).toBeVisible();
    expect(screen.getByRole("button", { name: "Download" })).toBeVisible();
    expect(
      screen.getByText("Your Organisation role has read-only document access."),
    ).toBeVisible();
    expect(
      screen.queryByRole("button", { name: "Upload document" }),
    ).not.toBeInTheDocument();
    expect(
      screen.queryByRole("button", { name: "Delete" }),
    ).not.toBeInTheDocument();
  });

  it("uploads a selected document and updates metadata", async () => {
    render(
      <CandidateDocuments candidateId={9} mayManage organisationId={11} />,
    );
    await screen.findByText("No documents uploaded.");
    const file = new File(["pdf"], "synthetic-resume.pdf", {
      type: "application/pdf",
    });
    fireEvent.change(screen.getByLabelText("Choose document"), {
      target: { files: [file] },
    });
    fireEvent.click(screen.getByRole("button", { name: "Upload document" }));

    expect(await screen.findByText("Document uploaded.")).toBeVisible();
    expect(screen.getByText("synthetic-resume.pdf")).toBeVisible();
    expect(uploadCandidateDocument).toHaveBeenCalledWith(11, 9, file);
  });

  it("shows validation and server upload errors", async () => {
    vi.mocked(uploadCandidateDocument)
      .mockRejectedValueOnce(
        new ApiError(422, "The given data was invalid.", {
          document: ["The document must be a PDF or DOCX file."],
        }),
      )
      .mockRejectedValueOnce(
        new ApiError(500, "The request could not be completed."),
      );
    render(
      <CandidateDocuments candidateId={9} mayManage organisationId={11} />,
    );
    await screen.findByText("No documents uploaded.");
    const input = screen.getByLabelText("Choose document");

    fireEvent.change(input, {
      target: { files: [new File(["text"], "notes.txt")] },
    });
    fireEvent.click(screen.getByRole("button", { name: "Upload document" }));
    expect(
      await screen.findByText("The document must be a PDF or DOCX file."),
    ).toBeVisible();

    fireEvent.change(input, {
      target: { files: [new File(["pdf"], "resume.pdf")] },
    });
    fireEvent.click(screen.getByRole("button", { name: "Upload document" }));
    expect(
      await screen.findByText("The request could not be completed."),
    ).toBeVisible();
  });

  it("downloads using a temporary browser object URL", async () => {
    vi.mocked(listCandidateDocuments).mockResolvedValue([document]);
    const createObjectUrl = vi.fn().mockReturnValue("blob:private-download");
    const revokeObjectUrl = vi.fn();
    const click = vi
      .spyOn(HTMLAnchorElement.prototype, "click")
      .mockImplementation(() => undefined);
    Object.defineProperty(URL, "createObjectURL", {
      configurable: true,
      value: createObjectUrl,
    });
    Object.defineProperty(URL, "revokeObjectURL", {
      configurable: true,
      value: revokeObjectUrl,
    });
    render(
      <CandidateDocuments
        candidateId={9}
        mayManage={false}
        organisationId={11}
      />,
    );

    fireEvent.click(await screen.findByRole("button", { name: "Download" }));

    await waitFor(() =>
      expect(downloadCandidateDocument).toHaveBeenCalledWith(11, 9, 3),
    );
    expect(createObjectUrl).toHaveBeenCalledOnce();
    expect(click).toHaveBeenCalledOnce();
    expect(revokeObjectUrl).toHaveBeenCalledWith("blob:private-download");
  });

  it("requires confirmation and removes deleted metadata", async () => {
    vi.mocked(listCandidateDocuments).mockResolvedValue([document]);
    const confirm = vi.spyOn(window, "confirm").mockReturnValueOnce(false);
    render(
      <CandidateDocuments candidateId={9} mayManage organisationId={11} />,
    );
    const deleteButton = await screen.findByRole("button", { name: "Delete" });

    fireEvent.click(deleteButton);
    expect(deleteCandidateDocument).not.toHaveBeenCalled();
    confirm.mockReturnValueOnce(true);
    fireEvent.click(deleteButton);

    expect(await screen.findByText("Document deleted.")).toBeVisible();
    expect(screen.getByText("No documents uploaded.")).toBeVisible();
    expect(deleteCandidateDocument).toHaveBeenCalledWith(11, 9, 3);
  });

  it("keeps metadata visible when deletion fails", async () => {
    vi.mocked(listCandidateDocuments).mockResolvedValue([document]);
    vi.mocked(deleteCandidateDocument).mockRejectedValue(
      new ApiError(500, "The request could not be completed."),
    );
    vi.spyOn(window, "confirm").mockReturnValue(true);
    render(
      <CandidateDocuments candidateId={9} mayManage organisationId={11} />,
    );

    fireEvent.click(await screen.findByRole("button", { name: "Delete" }));

    expect(
      await screen.findByText("The request could not be completed."),
    ).toBeVisible();
    expect(screen.getByText("synthetic-resume.pdf")).toBeVisible();
  });

  it("shows safe unavailable and download-error states", async () => {
    vi.mocked(listCandidateDocuments).mockRejectedValue(
      new ApiError(404, "Not Found"),
    );
    const { rerender } = render(
      <CandidateDocuments candidateId={999} mayManage organisationId={11} />,
    );

    expect(
      await screen.findByText(
        "Documents are unavailable or you no longer have access.",
      ),
    ).toBeVisible();
    expect(screen.queryByText("Not Found")).not.toBeInTheDocument();

    vi.mocked(listCandidateDocuments).mockResolvedValue([document]);
    vi.mocked(downloadCandidateDocument).mockRejectedValue(
      new ApiError(500, "The request could not be completed."),
    );
    rerender(
      <CandidateDocuments candidateId={9} mayManage organisationId={11} />,
    );
    fireEvent.click(await screen.findByRole("button", { name: "Download" }));
    expect(
      await screen.findByText("The request could not be completed."),
    ).toBeVisible();
  });

  it("clears stale documents when the tenant or Candidate changes", async () => {
    vi.mocked(listCandidateDocuments).mockImplementation(
      async (organisationId) => (organisationId === 11 ? [document] : []),
    );
    const { rerender } = render(
      <CandidateDocuments candidateId={9} mayManage organisationId={11} />,
    );
    await screen.findByText("synthetic-resume.pdf");

    rerender(
      <CandidateDocuments candidateId={20} mayManage organisationId={22} />,
    );

    await waitFor(() =>
      expect(
        screen.queryByText("synthetic-resume.pdf"),
      ).not.toBeInTheDocument(),
    );
    expect(await screen.findByText("No documents uploaded.")).toBeVisible();
    expect(listCandidateDocuments).toHaveBeenLastCalledWith(22, 20);
  });
});
