import type { NextConfig } from "next";

/**
 * The admin panel is part of the backend, not of this student website. Anyone who types <student site>/admin is sent to
 * the real admin: https://api.<domain>/admin. On lms.<domain> this works from the address alone (nothing to configure);
 * the build-time NEXT_PUBLIC_API_URL covers any other address.
 */
const backendOrigin = (process.env.NEXT_PUBLIC_API_URL ?? "").replace(/\/api\/v1\/?$/, "");

/**
 * Browser protections for the student site. The page policy (CSP) is applied on the live build only (the dev server needs eval).
 * It lets the page load things only from this site, video players from YouTube/Vimeo, and talk only to this site and the API.
 */
const apiOrigin = /^https?:\/\//.test(backendOrigin) ? backendOrigin : "";
const csp = [
  "default-src 'self'",
  "script-src 'self' 'unsafe-inline'",
  "style-src 'self' 'unsafe-inline'",
  "img-src 'self' data: blob: https:",
  "font-src 'self' data:",
  `connect-src 'self' https://*.mymerchantnavy.com ${apiOrigin}`.trim(),
  "frame-src https://www.youtube.com https://www.youtube-nocookie.com https://player.vimeo.com",
  "object-src 'none'",
  "base-uri 'self'",
  "form-action 'self'",
  "frame-ancestors 'none'",
].join("; ");

const securityHeaders = [
  { key: "X-Content-Type-Options", value: "nosniff" },
  { key: "X-Frame-Options", value: "DENY" },
  { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
  { key: "Permissions-Policy", value: "camera=(), microphone=(), geolocation=(), payment=(), usb=()" },
  { key: "Cross-Origin-Opener-Policy", value: "same-origin" },
  { key: "Strict-Transport-Security", value: "max-age=31536000; includeSubDomains" },
  ...(process.env.NODE_ENV === "production" ? [{ key: "Content-Security-Policy", value: csp }] : []),
];

const nextConfig: NextConfig = {
  poweredByHeader: false,
  async headers() {
    return [{ source: "/:path*", headers: securityHeaders }];
  },
  eslint: {
    ignoreDuringBuilds: true,
  },
  async redirects() {
    return [
      { source: "/admin", has: [{ type: "host", value: "lms\\.(?<domain>.+)" }], destination: "https://api.:domain/admin", permanent: false },
      { source: "/admin/:path*", has: [{ type: "host", value: "lms\\.(?<domain>.+)" }], destination: "https://api.:domain/admin/:path*", permanent: false },
      ...(/^https?:\/\//.test(backendOrigin)
        ? [
            { source: "/admin", destination: `${backendOrigin}/admin`, permanent: false },
            { source: "/admin/:path*", destination: `${backendOrigin}/admin/:path*`, permanent: false },
          ]
        : []),
    ];
  },
};

export default nextConfig;
