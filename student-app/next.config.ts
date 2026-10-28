import type { NextConfig } from "next";

/**
 * The admin panel is part of the backend, not of this student website. Anyone who types <student site>/admin is sent to
 * the real admin: https://api.<domain>/admin. On lms.<domain> this works from the address alone (nothing to configure);
 * the build-time NEXT_PUBLIC_API_URL covers any other address.
 */
const backendOrigin = (process.env.NEXT_PUBLIC_API_URL ?? "").replace(/\/api\/v1\/?$/, "");

const nextConfig: NextConfig = {
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
