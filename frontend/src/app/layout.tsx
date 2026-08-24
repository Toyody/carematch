import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "CareMatch",
  description: "Healthcare workforce and recruitment management",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}
