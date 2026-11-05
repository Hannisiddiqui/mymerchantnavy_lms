<?php

namespace App\Services\Courses;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RemotePdfDownloader
{
    /** Maximum allowed PDF size: 100 MB */
    public const MAX_BYTES = 104857600;

    /**
     * Download a PDF from a Google Drive link or direct URL, store it privately, and return file info.
     *
     * @return array{file_path: string, title: string, size: int}
     *
     * @throws ValidationException
     */
    public static function download(string $url): array
    {
        $url = trim($url);
        if (empty($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            throw ValidationException::withMessages([
                'pdf_url' => 'Please provide a valid web URL or Google Drive link.',
            ]);
        }

        $gdriveId = self::extractGoogleDriveId($url);
        $result = $gdriveId ? self::fetchGoogleDrive($gdriveId) : self::fetchDirectUrl($url);

        $body = $result['body'];
        $contentType = strtolower($result['content_type'] ?? '');
        $suggestedName = $result['filename'] ?? null;

        // Magic bytes check: PDF files always start with '%PDF-'
        if (strncmp($body, '%PDF-', 5) !== 0) {
            self::handleNonPdfResponse($body, $gdriveId !== null);
        }

        if (strlen($body) > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'pdf_url' => 'The PDF is larger than 100MB. Please upload a compressed version.',
            ]);
        }

        $fileName = Str::random(40) . '.pdf';
        $path = "pdfs/{$fileName}";
        Storage::disk('local')->put($path, $body);

        $title = self::cleanTitle($suggestedName, $url);

        return [
            'file_path' => $path,
            'title'     => $title,
            'size'      => strlen($body),
        ];
    }

    /** Extract file ID from various Google Drive link formats. */
    public static function extractGoogleDriveId(string $url): ?string
    {
        if (! str_contains($url, 'drive.google.com') && ! str_contains($url, 'docs.google.com')) {
            return null;
        }

        if (preg_match('~/file/d/([a-zA-Z0-9_-]{20,})~', $url, $m)) {
            return $m[1];
        }
        if (preg_match('~[?&]id=([a-zA-Z0-9_-]{20,})~', $url, $m)) {
            return $m[1];
        }
        if (preg_match('~/d/([a-zA-Z0-9_-]{20,})~', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    /** Fetch a file from Google Drive via direct download endpoints, handling confirmation redirects. */
    private static function fetchGoogleDrive(string $fileId): array
    {
        $cookieFile = tempnam(sys_get_temp_dir(), 'gdrive_ck_');
        $headers = [];
        $filename = null;

        $urlsToTry = [
            "https://drive.usercontent.google.com/download?id={$fileId}&export=download&authuser=0",
            "https://docs.google.com/uc?export=download&id={$fileId}",
            "https://drive.google.com/uc?export=download&id={$fileId}",
        ];

        $body = '';
        $contentType = '';

        foreach ($urlsToTry as $endpoint) {
            $response = self::curlRequest($endpoint, $cookieFile);
            $body = $response['body'];
            $contentType = $response['content_type'];
            $headers = $response['headers'];
            $filename = self::extractFilenameFromHeaders($headers);

            // If it starts with %PDF-, we have the file!
            if (strncmp($body, '%PDF-', 5) === 0) {
                @unlink($cookieFile);
                return [
                    'body' => $body,
                    'content_type' => $contentType,
                    'filename' => $filename,
                ];
            }

            // Check if Google Drive returned a virus scan warning confirmation page (for files > 100MB or certain scopes)
            if (preg_match('~href="(/uc\?export=download[^"]+)"~i', $body, $m) ||
                preg_match('~href="(/download\?[^"]+)"~i', $body, $m) ||
                preg_match('~action="(/uc\?export=download[^"]+)"~i', $body, $m)) {
                $confirmUrl = 'https://drive.google.com' . html_entity_decode($m[1]);
                $confirmRes = self::curlRequest($confirmUrl, $cookieFile);
                if (strncmp($confirmRes['body'], '%PDF-', 5) === 0) {
                    @unlink($cookieFile);
                    return [
                        'body' => $confirmRes['body'],
                        'content_type' => $confirmRes['content_type'],
                        'filename' => self::extractFilenameFromHeaders($confirmRes['headers']) ?: $filename,
                    ];
                }
            }

            // Also check for confirm token in query or body
            if (preg_match('~confirm=([0-9a-zA-Z_-]+)~', $body, $m)) {
                $tokenUrl = "https://docs.google.com/uc?export=download&confirm={$m[1]}&id={$fileId}";
                $tokenRes = self::curlRequest($tokenUrl, $cookieFile);
                if (strncmp($tokenRes['body'], '%PDF-', 5) === 0) {
                    @unlink($cookieFile);
                    return [
                        'body' => $tokenRes['body'],
                        'content_type' => $tokenRes['content_type'],
                        'filename' => self::extractFilenameFromHeaders($tokenRes['headers']) ?: $filename,
                    ];
                }
            }
        }

        @unlink($cookieFile);

        return [
            'body' => $body,
            'content_type' => $contentType,
            'filename' => $filename,
        ];
    }

    /** Direct URL download. */
    private static function fetchDirectUrl(string $url): array
    {
        // Dropbox link conversion: ensure dl=1 so it serves raw binary
        if (str_contains($url, 'dropbox.com') && ! str_contains($url, 'dl=1')) {
            $url = str_replace(['?dl=0', '&dl=0'], '', $url);
            $url .= (str_contains($url, '?') ? '&' : '?') . 'dl=1';
        }

        $res = self::curlRequest($url);

        return [
            'body' => $res['body'],
            'content_type' => $res['content_type'],
            'filename' => self::extractFilenameFromHeaders($res['headers']) ?: basename(parse_url($url, PHP_URL_PATH) ?? ''),
        ];
    }

    /** cURL runner with header tracking, cookies, and follow redirects. */
    private static function curlRequest(string $url, ?string $cookieFile = null): array
    {
        $ch = curl_init($url);
        $responseHeaders = [];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT         => 120,
            CURLOPT_CONNECTTIMEOUT  => 20,
            CURLOPT_SSL_VERIFYPEER  => false,
            CURLOPT_USERAGENT       => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
            CURLOPT_HEADERFUNCTION  => function ($curl, $header) use (&$responseHeaders) {
                $len = strlen($header);
                $parts = explode(':', $header, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return $len;
            },
        ]);

        if ($cookieFile) {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        }

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $error = curl_error($ch);

        if ($body === false || $httpCode >= 400) {
            $err = $error ?: "HTTP error {$httpCode}";
            if ($httpCode === 404) {
                throw ValidationException::withMessages([
                    'pdf_url' => 'File not found (HTTP 404). Please verify that the link is correct.',
                ]);
            }
            if ($httpCode === 403) {
                throw ValidationException::withMessages([
                    'pdf_url' => 'Access denied (HTTP 403). For Google Drive links, make sure the file is shared with "Anyone with the link can view".',
                ]);
            }
        }

        return [
            'body'         => (string) $body,
            'content_type' => (string) $contentType,
            'headers'      => $responseHeaders,
            'http_code'    => $httpCode,
        ];
    }

    /** Parse filename from Content-Disposition header. */
    private static function extractFilenameFromHeaders(array $headers): ?string
    {
        $disp = $headers['content-disposition'] ?? '';
        if (empty($disp)) {
            return null;
        }

        // filename*=UTF-8''filename.pdf
        if (preg_match("/filename\*=UTF-8''([^;]+)/i", $disp, $m)) {
            return rawurldecode(trim($m[1], '"\''));
        }

        // filename="filename.pdf"
        if (preg_match('/filename="?([^";]+)"?/i', $disp, $m)) {
            return trim($m[1], '"\' ');
        }

        return null;
    }

    /** Translate non-PDF responses (like Google login pages) into clear, actionable error messages. */
    private static function handleNonPdfResponse(string $body, bool $isGoogleDrive): void
    {
        if ($isGoogleDrive) {
            if (stripos($body, 'accounts.google.com') !== false ||
                stripos($body, 'Sign in - Google Accounts') !== false ||
                stripos($body, 'You need access') !== false ||
                stripos($body, 'Request access') !== false) {
                throw ValidationException::withMessages([
                    'pdf_url' => 'Google Drive access denied: This file is restricted. In Google Drive, please right-click the file → Share → change General access to "Anyone with the link (Viewer)", then paste the link again.',
                ]);
            }

            if (stripos($body, 'File not found') !== false || stripos($body, 'Google Drive - Page Not Found') !== false) {
                throw ValidationException::withMessages([
                    'pdf_url' => 'The Google Drive file was not found. Please double-check the link.',
                ]);
            }

            throw ValidationException::withMessages([
                'pdf_url' => 'Could not download PDF from this Google Drive link. Please make sure the link is set to "Anyone with the link can view".',
            ]);
        }

        throw ValidationException::withMessages([
            'pdf_url' => 'The provided link did not return a valid PDF document.',
        ]);
    }

    /** Clean up the title derived from filename or URL. */
    private static function cleanTitle(?string $suggestedName, string $url): string
    {
        $raw = $suggestedName ?: basename(parse_url($url, PHP_URL_PATH) ?? '');
        $raw = preg_replace('/\.pdf$/i', '', $raw);
        $raw = str_replace(['_', '-'], ' ', $raw);
        $raw = trim(preg_replace('/\s+/', ' ', $raw));

        if (empty($raw) || strlen($raw) < 2) {
            return 'PDF Notes';
        }

        return Str::limit(ucwords($raw), 140);
    }
}

