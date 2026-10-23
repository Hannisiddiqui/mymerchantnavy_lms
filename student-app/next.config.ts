import type { NextConfig } from "next";

/**
 * The admin panel is part of the backend, not of this student website. Anyone who types <student site>/admin is sent
 * to the real admin: <backend>/admin (the backend address is NEXT_PUBLIC_API_URL without "/api/v1").
 */
const backendOrigin = (process.env.NEXT_PUBLIC_API_URL ?? "").replace(/\/api\/v1\/?$/, "");

const nextConfig: NextConfig = {
  async redirects() {
    return /^https?:\/\//.test(backendOrigin)
      ? [
          { source: "/admin", destination: `${backendOrigin}/admin`, permanent: false },
          { source: "/admin/:path*", destination: `${backendOrigin}/admin/:path*`, permanent: false },
        ]
      : [];
  },
};

export default nextConfig;
