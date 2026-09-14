import { Suspense } from "react";

import { ResetPasswordForm } from "./reset-password-form";

export default function ResetPasswordPage() {
  return (
    <Suspense fallback={<p role="status">Loading reset form…</p>}>
      <ResetPasswordForm />
    </Suspense>
  );
}
