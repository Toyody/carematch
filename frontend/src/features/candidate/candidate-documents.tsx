"use client";

import { FormEvent, useEffect, useState } from "react";

import { FormError } from "@/components/forms/form-error";
import { ApiError } from "@/lib/api/client";

import {
  deleteCandidateDocument,
  downloadCandidateDocument,
  listCandidateDocuments,
  uploadCandidateDocument,
  type CandidateDocument,
} from "./api";

export function CandidateDocuments({
  candidateId,
  mayManage,
  organisationId,
}: {
  candidateId: number;
  mayManage: boolean;
  organisationId: number;
}) {
  const resultKey = `${organisationId}:${candidateId}`;
  const [result, setResult] = useState<{
    documents: CandidateDocument[];
    error: unknown;
    key: string;
  } | null>(null);
  const [actionError, setActionError] = useState<unknown>(null);
  const [status, setStatus] = useState<string | null>(null);
  const [isUploading, setIsUploading] = useState(false);
  const [busyDocumentId, setBusyDocumentId] = useState<number | null>(null);

  useEffect(() => {
    let active = true;

    void listCandidateDocuments(organisationId, candidateId)
      .then((loadedDocuments) => {
        if (active) {
          setResult({
            documents: loadedDocuments,
            error: null,
            key: resultKey,
          });
        }
      })
      .catch((error: unknown) => {
        if (active) {
          setResult({ documents: [], error, key: resultKey });
        }
      });

    return () => {
      active = false;
    };
  }, [candidateId, organisationId, resultKey]);

  const currentResult = result?.key === resultKey ? result : null;
  const documents = currentResult?.documents ?? null;
  const loadError = currentResult?.error ?? null;

  async function handleUpload(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    const fileInput = form.elements.namedItem("document");

    if (!(fileInput instanceof HTMLInputElement) || !fileInput.files?.[0]) {
      setActionError(new ApiError(422, "Please choose a PDF or DOCX file."));
      return;
    }

    setActionError(null);
    setStatus(null);
    setIsUploading(true);

    try {
      const document = await uploadCandidateDocument(
        organisationId,
        candidateId,
        fileInput.files[0],
      );
      setResult((current) => ({
        documents: [document, ...(current?.documents ?? [])],
        error: null,
        key: resultKey,
      }));
      setStatus("Document uploaded.");
      form.reset();
    } catch (error) {
      setActionError(error);
    } finally {
      setIsUploading(false);
    }
  }

  async function handleDownload(document: CandidateDocument) {
    setActionError(null);
    setStatus(null);
    setBusyDocumentId(document.id);

    try {
      const blob = await downloadCandidateDocument(
        organisationId,
        candidateId,
        document.id,
      );
      const url = URL.createObjectURL(blob);
      const link = window.document.createElement("a");
      link.href = url;
      link.download = document.original_name;
      link.hidden = true;
      window.document.body.append(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(url);
    } catch (error) {
      setActionError(error);
    } finally {
      setBusyDocumentId(null);
    }
  }

  async function handleDelete(document: CandidateDocument) {
    if (!window.confirm(`Delete ${document.original_name}?`)) {
      return;
    }

    setActionError(null);
    setStatus(null);
    setBusyDocumentId(document.id);

    try {
      await deleteCandidateDocument(organisationId, candidateId, document.id);
      setResult((current) => ({
        documents:
          current?.documents.filter((item) => item.id !== document.id) ?? [],
        error: null,
        key: resultKey,
      }));
      setStatus("Document deleted.");
    } catch (error) {
      setActionError(error);
    } finally {
      setBusyDocumentId(null);
    }
  }

  return (
    <section
      className="candidate-documents"
      aria-labelledby="documents-heading"
    >
      <h2 id="documents-heading">Documents</h2>
      <p>Private PDF or DOCX files, up to 10 MiB.</p>

      {loadError !== null ? (
        <p role="alert">
          {loadError instanceof ApiError && loadError.status === 404
            ? "Documents are unavailable or you no longer have access."
            : "Documents could not be loaded. Please try again."}
        </p>
      ) : documents === null ? (
        <p role="status">Loading documents…</p>
      ) : documents.length === 0 ? (
        <p>No documents uploaded.</p>
      ) : (
        <ul className="candidate-document-list">
          {documents.map((document) => (
            <li key={document.id}>
              <div>
                <strong>{document.original_name}</strong>
                <span>
                  {formatFileType(document.mime_type)} ·{" "}
                  {formatBytes(document.size_bytes)}
                </span>
              </div>
              <div className="candidate-document-actions">
                <button
                  disabled={busyDocumentId === document.id}
                  onClick={() => void handleDownload(document)}
                  type="button"
                >
                  Download
                </button>
                {mayManage ? (
                  <button
                    disabled={busyDocumentId === document.id}
                    onClick={() => void handleDelete(document)}
                    type="button"
                  >
                    Delete
                  </button>
                ) : null}
              </div>
            </li>
          ))}
        </ul>
      )}

      {mayManage ? (
        <form onSubmit={handleUpload}>
          <label htmlFor="candidate-document">Choose document</label>
          <input
            accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
            id="candidate-document"
            name="document"
            type="file"
          />
          <button disabled={isUploading} type="submit">
            {isUploading ? "Uploading…" : "Upload document"}
          </button>
        </form>
      ) : (
        <p>Your Organisation role has read-only document access.</p>
      )}

      <FormError error={actionError} />
      {status !== null ? <p role="status">{status}</p> : null}
    </section>
  );
}

function formatFileType(mimeType: string): string {
  return mimeType === "application/pdf" ? "PDF" : "DOCX";
}

function formatBytes(bytes: number): string {
  if (bytes < 1024) {
    return `${bytes} B`;
  }

  return `${(bytes / 1024).toFixed(1)} KiB`;
}
