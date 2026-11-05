<?php

namespace App\Services\Courses;

use App\Enums\ContentType;
use App\Models\Chapter;
use App\Models\Subject;
use App\Services\Courses\CourseOutline;
use App\Services\Courses\RemotePdfDownloader;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GoogleDriveFolderScanner
{
    /** Browser user-agent required by Google Drive to return embedded folder markup. */
    private const USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    /**
     * Determine if a given string or URL is a Google Drive folder link.
     */
    public static function isFolderUrl(string $url): bool
    {
        $url = trim($url);

        return str_contains($url, '/drive/folders/')
            || str_contains($url, '/folders/')
            || str_contains($url, 'embeddedfolderview')
            || (str_contains($url, 'drive.google.com') && str_contains($url, 'folder'));
    }

    /**
     * Extract the Google Drive folder ID from various folder URL formats.
     */
    public static function extractFolderId(string $url): ?string
    {
        $url = trim($url);

        // Standard folder URL: /drive/folders/{id} or /drive/u/0/folders/{id}
        if (preg_match('~/folders/([a-zA-Z0-9_-]{15,})~', $url, $matches)) {
            return $matches[1];
        }

        // Embedded folder view or open?id={id}
        if (preg_match('~[?&]id=([a-zA-Z0-9_-]{15,})~', $url, $matches)) {
            return $matches[1];
        }

        // Direct raw folder ID passed
        if (preg_match('~^[a-zA-Z0-9_-]{25,50}$~', $url)) {
            return $url;
        }

        return null;
    }

    /**
     * Scan a Google Drive folder and return its title, files, and subfolders.
     *
     * @return array{id: string, title: string, files: array<int, array{id: string, name: string, url: string}>, subfolders: array<int, array>}
     *
     * @throws ValidationException
     */
    public static function scanFolder(string $urlOrId, bool $recursive = true, int $depth = 0, int $maxDepth = 2): array
    {
        $folderId = self::extractFolderId($urlOrId);
        if (! $folderId) {
            throw ValidationException::withMessages([
                'folder_url' => 'Invalid Google Drive folder link. Please provide a valid link in the format https://drive.google.com/drive/folders/...',
            ]);
        }

        $endpoint = 'https://drive.google.com/embeddedfolderview?id=' . urlencode($folderId);

        try {
            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ])->timeout(20)->get($endpoint);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'folder_url' => 'Could not connect to Google Drive: ' . $e->getMessage(),
            ]);
        }

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'folder_url' => "Google Drive folder returned HTTP {$response->status()}. Please make sure the folder's General Access is set to “Anyone with the link can view”.",
            ]);
        }

        $html = $response->body();

        // If Google redirected to sign-in page
        if (str_contains($html, 'accounts.google.com/ServiceLogin') || str_contains($html, 'Sign in - Google Accounts')) {
            throw ValidationException::withMessages([
                'folder_url' => 'This Google Drive folder is private. Please set General Access to “Anyone with the link can view” in Google Drive sharing settings.',
            ]);
        }

        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);

        $titleNode = $xpath->query('//title')->item(0);
        $folderTitle = $titleNode ? trim($titleNode->textContent) : 'Untitled Folder';
        if ($folderTitle === 'Google Drive' || empty($folderTitle)) {
            $folderTitle = 'Drive Folder ' . substr($folderId, 0, 6);
        }

        $files = [];
        $subfolders = [];
        $seenIds = [$folderId => true];

        foreach ($xpath->query('//a[@href]') as $a) {
            /** @var \DOMElement $a */
            $href = $a->getAttribute('href');
            $name = trim($a->textContent);

            // 1. Direct file link: /file/d/{id} or /open?id={id}
            if (preg_match('~drive\.google\.com/file/d/([a-zA-Z0-9_-]{20,})~', $href, $m) ||
                preg_match('~docs\.google\.com/(?:document|spreadsheets|presentation)/d/([a-zA-Z0-9_-]{20,})~', $href, $m)) {
                $fileId = $m[1];
                if (! isset($seenIds[$fileId]) && filled($name)) {
                    $seenIds[$fileId] = true;
                    $files[] = [
                        'id'   => $fileId,
                        'name' => self::sanitizeTitle($name),
                        'url'  => 'https://drive.google.com/file/d/' . $fileId . '/view',
                    ];
                }
                continue;
            }

            // 2. Subfolder link: /drive/folders/{id}
            if (preg_match('~drive\.google\.com/drive/folders/([a-zA-Z0-9_-]{20,})~', $href, $m)) {
                $subId = $m[1];
                if (! isset($seenIds[$subId]) && filled($name)) {
                    $seenIds[$subId] = true;
                    $subfolders[] = [
                        'id'    => $subId,
                        'title' => self::sanitizeTitle($name),
                        'url'   => 'https://drive.google.com/drive/folders/' . $subId,
                    ];
                }
            }
        }

        // If recursive scanning is requested and we haven't reached max depth
        if ($recursive && $depth < $maxDepth) {
            foreach ($subfolders as $idx => $sub) {
                try {
                    $subScanned = self::scanFolder($sub['id'], true, $depth + 1, $maxDepth);
                    $subfolders[$idx]['title'] = $subScanned['title'] ?: $sub['title'];
                    $subfolders[$idx]['files'] = $subScanned['files'];
                    $subfolders[$idx]['subfolders'] = $subScanned['subfolders'];
                } catch (\Throwable) {
                    $subfolders[$idx]['files'] = [];
                    $subfolders[$idx]['subfolders'] = [];
                }
            }
        }

        return [
            'id'         => $folderId,
            'title'      => $folderTitle,
            'files'      => $files,
            'subfolders' => $subfolders,
        ];
    }

    /**
     * Flatten all files from a scanned folder (including subfolders) into a simple list.
     *
     * @return array<int, array{id: string, name: string, url: string, folder_name: string}>
     */
    public static function flattenFiles(array $folderData, string $prefix = ''): array
    {
        $all = [];
        $currentFolder = filled($prefix) ? "{$prefix} / {$folderData['title']}" : $folderData['title'];

        foreach ($folderData['files'] as $file) {
            $all[] = [
                'id'          => $file['id'],
                'name'        => $file['name'],
                'url'         => $file['url'],
                'folder_name' => $currentFolder,
            ];
        }

        foreach ($folderData['subfolders'] as $sub) {
            $subFiles = self::flattenFiles($sub, $currentFolder);
            foreach ($subFiles as $sf) {
                $all[] = $sf;
            }
        }

        return $all;
    }

    /**
     * Import an entire Google Drive folder directly into a specific Chapter.
     *
     * @return array{imported: int, skipped: int, errors: array<int, string>}
     */
    public static function importIntoChapter(Chapter $chapter, string $folderUrl, CourseOutline $outline, bool $recursive = true): array
    {
        @ini_set('max_execution_time', '300');
        @set_time_limit(300);

        $scanned = self::scanFolder($folderUrl, $recursive);
        $files = self::flattenFiles($scanned);

        if (empty($files)) {
            throw ValidationException::withMessages([
                'folder_url' => "No files found in folder “{$scanned['title']}”. Make sure files are inside and access is public.",
            ]);
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($files as $file) {
            try {
                $downloaded = RemotePdfDownloader::download($file['url']);
                $title = filled($file['name']) ? $file['name'] : $downloaded['title'];

                $outline->saveContent($chapter, ContentType::Pdf, [
                    'title' => $title,
                    'file'  => $downloaded['file_path'],
                ]);
                $imported++;
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = "{$file['name']}: " . $e->getMessage();
            }
        }

        return [
            'folder_title' => $scanned['title'],
            'imported'     => $imported,
            'skipped'      => $skipped,
            'errors'       => $errors,
        ];
    }

    /**
     * Import a Google Drive folder into a Subject.
     * If the folder contains subfolders (e.g. Chapter 1, Chapter 2), each subfolder becomes a Chapter!
     * If it only contains files, creates or uses a single chapter.
     *
     * @return array{chapters_created: int, files_imported: int, errors: array<int, string>}
     */
    public static function importIntoSubject(Subject $subject, string $folderUrl, CourseOutline $outline, ?string $fallbackChapterTitle = null): array
    {
        @ini_set('max_execution_time', '300');
        @set_time_limit(300);

        $scanned = self::scanFolder($folderUrl, true);

        $chaptersCreated = 0;
        $filesImported = 0;
        $errors = [];

        // Scenario A: The Drive folder has subfolders (e.g. Chemistry Material -> 1. Atoms, 2. Bonding...)
        if (! empty($scanned['subfolders'])) {
            // First, import each subfolder as its own chapter!
            foreach ($scanned['subfolders'] as $sub) {
                $chapterTitle = $sub['title'];
                // Check if chapter already exists under this subject, otherwise create it
                $chapter = $subject->chapters()->where('title', $chapterTitle)->first();
                if (! $chapter) {
                    $chapter = $outline->addChapter($subject, $chapterTitle);
                    $chaptersCreated++;
                }

                $subFiles = self::flattenFiles($sub);
                foreach ($subFiles as $file) {
                    try {
                        $downloaded = RemotePdfDownloader::download($file['url']);
                        $title = filled($file['name']) ? $file['name'] : $downloaded['title'];

                        $outline->saveContent($chapter, ContentType::Pdf, [
                            'title' => $title,
                            'file'  => $downloaded['file_path'],
                        ]);
                        $filesImported++;
                    } catch (\Throwable $e) {
                        $errors[] = "{$file['name']}: " . $e->getMessage();
                    }
                }
            }

            // If the root folder also contains direct files, add them to a root chapter
            if (! empty($scanned['files'])) {
                $rootChapterTitle = $scanned['title'] ?: ($fallbackChapterTitle ?: 'General Material');
                $rootChapter = $subject->chapters()->where('title', $rootChapterTitle)->first();
                if (! $rootChapter) {
                    $rootChapter = $outline->addChapter($subject, $rootChapterTitle);
                    $chaptersCreated++;
                }

                foreach ($scanned['files'] as $file) {
                    try {
                        $downloaded = RemotePdfDownloader::download($file['url']);
                        $title = filled($file['name']) ? $file['name'] : $downloaded['title'];

                        $outline->saveContent($rootChapter, ContentType::Pdf, [
                            'title' => $title,
                            'file'  => $downloaded['file_path'],
                        ]);
                        $filesImported++;
                    } catch (\Throwable $e) {
                        $errors[] = "{$file['name']}: " . $e->getMessage();
                    }
                }
            }
        } else {
            // Scenario B: The Drive folder has NO subfolders, just files.
            // Create a chapter named after the folder, or use the fallback title.
            $chapterTitle = $fallbackChapterTitle ?: $scanned['title'];
            $chapter = $subject->chapters()->where('title', $chapterTitle)->first();
            if (! $chapter) {
                $chapter = $outline->addChapter($subject, $chapterTitle);
                $chaptersCreated++;
            }

            foreach ($scanned['files'] as $file) {
                try {
                    $downloaded = RemotePdfDownloader::download($file['url']);
                    $title = filled($file['name']) ? $file['name'] : $downloaded['title'];

                    $outline->saveContent($chapter, ContentType::Pdf, [
                        'title' => $title,
                        'file'  => $downloaded['file_path'],
                    ]);
                    $filesImported++;
                } catch (\Throwable $e) {
                    $errors[] = "{$file['name']}: " . $e->getMessage();
                }
            }
        }

        return [
            'folder_title'     => $scanned['title'],
            'chapters_created' => $chaptersCreated,
            'files_imported'   => $filesImported,
            'errors'           => $errors,
        ];
    }

    private static function sanitizeTitle(string $title): string
    {
        $clean = preg_replace('/\.(pdf|docx|pptx|xlsx|txt)$/i', '', $title);
        $clean = preg_replace('/[_\s]+/', ' ', $clean);

        return Str::limit(trim($clean), 150);
    }
}
