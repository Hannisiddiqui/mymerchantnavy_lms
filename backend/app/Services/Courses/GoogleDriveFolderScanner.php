<?php

namespace App\Services\Courses;

use App\Enums\ContentType;
use App\Models\Chapter;
use App\Models\Subject;
use App\Services\Courses\CourseOutline;
use App\Services\Courses\RemotePdfDownloader;
use Illuminate\Support\Facades\Cache;
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
            || (str_contains($url, 'drive.google.com') && str_contains($url, 'folder'))
            || (str_contains($url, 'drive.google.com') && str_contains($url, 'open?id='));
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
     * Parse chapter numbering and clean title from a raw folder name.
     * e.g. "1. Structure of Atoms" => [num => 1, clean_title => "Structure of Atoms"]
     * e.g. "19. The solid State" => [num => 19, clean_title => "The Solid State"]
     *
     * @return array{num: int, clean_title: string}
     */
    public static function parseChapterInfo(string $rawTitle): array
    {
        $raw = trim($rawTitle);
        $num = 9999;
        $clean = $raw;

        if (preg_match('/^(?:chapter\s*|ch\s*)?(\d+)[\.\s\-_:]+(.+)$/i', $raw, $m)) {
            $num = (int) $m[1];
            $clean = trim($m[2]);
        } elseif (preg_match('/^(?:chapter\s*|ch\s*)(\d+)$/i', $raw, $m)) {
            $num = (int) $m[1];
            $clean = 'Chapter ' . $num;
        }

        // Clean up trailing dots, underscores, dashes
        $clean = preg_replace('/[\._\s-]+$/', '', $clean);
        $clean = preg_replace('/[_\s]+/', ' ', $clean);

        return [
            'num'         => $num,
            'clean_title' => self::beautifyTitle(trim($clean)),
        ];
    }

    /**
     * Score and beautify a PDF filename for proper ordering:
     * 10: Study Material / Theory (First)
     * 20: Practice Test / DPP (Second)
     * 30: Interview Questions / Past Papers (Third)
     *
     * @return array{title: string, weight: int}
     */
    public static function scoreAndBeautifyFilename(string $filename): array
    {
        $clean = preg_replace('/\.pdf$/i', '', $filename);
        $clean = str_replace('_', ' ', $clean);
        $clean = preg_replace('/\s+/', ' ', $clean);

        $lower = strtolower($filename);
        $weight = 50;

        if (str_contains($lower, 'interview')) {
            $weight = 30; // 3rd: Interview questions
        } elseif (str_contains($lower, 'practice') || str_contains($lower, 'test') || str_contains($lower, 'dpp') || str_contains($lower, 'exercise') || str_contains($lower, 'quiz')) {
            $weight = 20; // 2nd: Practice tests
        } else {
            $weight = 10; // 1st: Notes, Theory, Main study material
        }

        $title = self::beautifyTitle(trim($clean));

        return [
            'title'  => $title,
            'weight' => $weight,
        ];
    }

    /**
     * Beautify a title string, capitalizing words while preserving standard acronyms.
     */
    public static function beautifyTitle(string $raw): string
    {
        $clean = str_replace('_', ' ', $raw);
        $clean = preg_replace('/\s+/', ' ', $clean);

        $acronyms = [
            'dns'    => 'DNS',
            'imucet' => 'IMUCET',
            'imu'    => 'IMU',
            'cet'    => 'CET',
            'bsc'    => 'BSc',
            'dpp'    => 'DPP',
            'pyq'    => 'PYQ',
            'pdf'    => 'PDF',
            'gp'     => 'G.P.',
        ];

        $words = explode(' ', trim($clean));
        $cased = [];

        foreach ($words as $w) {
            $wLower = strtolower($w);
            if (isset($acronyms[$wLower])) {
                $cased[] = $acronyms[$wLower];
            } elseif (strlen($w) <= 3 && ctype_upper($w)) {
                $cased[] = $w;
            } else {
                $cased[] = ucfirst($wLower);
            }
        }

        return Str::limit(implode(' ', $cased), 140);
    }

    /**
     * Scan a Google Drive folder and return its title, files, and subfolders.
     * Results are cached for 2 minutes to keep Livewire forms ultra-responsive.
     *
     * @return array{id: string, title: string, files: array<int, array{id: string, name: string, url: string, raw_name: string, weight: int}>, subfolders: array<int, array>}
     *
     * @throws ValidationException
     */
    public static function scanFolder(string $urlOrId, bool $recursive = false, int $depth = 0, int $maxDepth = 2): array
    {
        $folderId = self::extractFolderId($urlOrId);
        if (! $folderId) {
            throw ValidationException::withMessages([
                'folder_url' => 'Invalid Google Drive folder link. Please provide a link in the format https://drive.google.com/drive/folders/...',
            ]);
        }

        $cacheKey = "gdrive_scan_v2_{$folderId}_{$recursive}_{$depth}";
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        $endpoint = 'https://drive.google.com/embeddedfolderview?id=' . urlencode($folderId);

        try {
            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
                'Accept'     => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
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
                    $scored = self::scoreAndBeautifyFilename($name);
                    $files[] = [
                        'id'       => $fileId,
                        'name'     => $scored['title'],
                        'raw_name' => $name,
                        'weight'   => $scored['weight'],
                        'url'      => 'https://drive.google.com/file/d/' . $fileId . '/view',
                    ];
                }
                continue;
            }

            // 2. Subfolder link: /drive/folders/{id}
            if (preg_match('~drive\.google\.com/drive/folders/([a-zA-Z0-9_-]{20,})~', $href, $m)) {
                $subId = $m[1];
                if (! isset($seenIds[$subId]) && filled($name)) {
                    $seenIds[$subId] = true;
                    $info = self::parseChapterInfo($name);
                    $subfolders[] = [
                        'id'          => $subId,
                        'raw_title'   => $name,
                        'title'       => $info['clean_title'],
                        'chapter_num' => $info['num'],
                        'url'         => 'https://drive.google.com/drive/folders/' . $subId,
                    ];
                }
            }
        }

        // Sort files within this folder: Notes first (10), Practice Tests second (20), Interview third (30)
        usort($files, function ($a, $b) {
            if ($a['weight'] !== $b['weight']) {
                return $a['weight'] <=> $b['weight'];
            }
            return strnatcasecmp($a['name'], $b['name']);
        });

        // Sort subfolders in strict numerical chapter order: 1, 2, ... 19!
        usort($subfolders, function ($a, $b) {
            if ($a['chapter_num'] !== $b['chapter_num']) {
                return $a['chapter_num'] <=> $b['chapter_num'];
            }
            return strnatcasecmp($a['title'], $b['title']);
        });

        // If recursive scanning is requested and we haven't reached max depth
        if ($recursive && $depth < $maxDepth) {
            foreach ($subfolders as $idx => $sub) {
                try {
                    $subScanned = self::scanFolder($sub['id'], true, $depth + 1, $maxDepth);
                    $subfolders[$idx]['title'] = $sub['title'] ?: $subScanned['title'];
                    $subfolders[$idx]['files'] = $subScanned['files'];
                    $subfolders[$idx]['subfolders'] = $subScanned['subfolders'];
                } catch (\Throwable) {
                    $subfolders[$idx]['files'] = [];
                    $subfolders[$idx]['subfolders'] = [];
                }
            }
        }

        $result = [
            'id'         => $folderId,
            'title'      => $folderTitle,
            'files'      => $files,
            'subfolders' => $subfolders,
        ];

        Cache::put($cacheKey, $result, now()->addMinutes(2));

        return $result;
    }

    /**
     * Get a flattened list of all files in a folder for the chapter file selection picker.
     *
     * @return array<int, array{id: string, name: string, url: string, subfolder: string, weight: int}>
     */
    public static function getFilesList(string $folderUrl): array
    {
        $scanned = self::scanFolder($folderUrl, false);
        $all = [];

        foreach ($scanned['files'] as $f) {
            $all[] = [
                'id'        => $f['id'],
                'name'      => $f['name'],
                'url'       => $f['url'],
                'subfolder' => 'Root Folder',
                'weight'    => $f['weight'],
            ];
        }

        // If root has no files but has subfolders, scan direct subfolders (max 10)
        if (empty($all) && ! empty($scanned['subfolders'])) {
            foreach (array_slice($scanned['subfolders'], 0, 10) as $sub) {
                try {
                    $subScanned = self::scanFolder($sub['id'], false);
                    foreach ($subScanned['files'] ?? [] as $f) {
                        $all[] = [
                            'id'        => $f['id'],
                            'name'      => $f['name'],
                            'url'       => $f['url'],
                            'subfolder' => $sub['title'],
                            'weight'    => $f['weight'],
                        ];
                    }
                } catch (\Throwable) {
                }
            }
        }

        // Sort all files by category and name
        usort($all, function ($a, $b) {
            if ($a['weight'] !== $b['weight']) {
                return $a['weight'] <=> $b['weight'];
            }
            return strnatcasecmp($a['name'], $b['name']);
        });

        return $all;
    }

    /**
     * Get an organized list of detected chapters (arranged 1 to 19) for subject import preview.
     * Fast non-recursive scan: extracts all 19 chapter subfolders in ~1 second.
     *
     * @return array<int, array{id: string, chapter_num: int, clean_title: string, display_name: string, files_count: int, files: array}>
     */
    public static function getChaptersList(string $folderUrl): array
    {
        $scanned = self::scanFolder($folderUrl, false);

        // If the Drive folder has subfolders: each subfolder is a chapter (sorted 1 to 19)
        if (! empty($scanned['subfolders'])) {
            $chapters = [];
            foreach ($scanned['subfolders'] as $sub) {
                $chNum = $sub['chapter_num'] !== 9999 ? "Chapter {$sub['chapter_num']}: " : '';
                $chapters[] = [
                    'id'           => $sub['id'],
                    'chapter_num'  => $sub['chapter_num'],
                    'clean_title'  => $sub['title'],
                    'display_name' => "{$chNum}{$sub['title']}",
                    'files_count'  => 0,
                    'files'        => [],
                ];
            }
            return $chapters;
        }

        // If no subfolders: single chapter
        $count = count($scanned['files']);
        return [
            [
                'id'           => $scanned['id'],
                'chapter_num'  => 1,
                'clean_title'  => $scanned['title'],
                'display_name' => "{$scanned['title']} ({$count} " . Str::plural('file', $count) . ')',
                'files_count'  => $count,
                'files'        => $scanned['files'],
            ],
        ];
    }

    /**
     * Import an entire Google Drive folder directly into a specific Chapter.
     * Optionally restricts import to selected file IDs.
     *
     * @param  array<int, string>|null  $selectedFileIds
     * @return array{folder_title: string, imported: int, skipped: int, errors: array<int, string>}
     */
    public static function importIntoChapter(Chapter $chapter, string $folderUrl, CourseOutline $outline, ?array $selectedFileIds = null, bool $recursive = true): array
    {
        @ini_set('max_execution_time', '300');
        @set_time_limit(300);

        $scanned = self::scanFolder($folderUrl, $recursive);
        $files = self::getFilesList($folderUrl);

        if (empty($files)) {
            throw ValidationException::withMessages([
                'folder_url' => "No files found in folder “{$scanned['title']}”. Make sure files are inside and access is set to 'Anyone with the link can view'.",
            ]);
        }

        // Filter by selected file IDs if specified
        if (! empty($selectedFileIds)) {
            $selectedMap = array_fill_keys($selectedFileIds, true);
            $files = array_values(array_filter($files, fn ($f) => isset($selectedMap[$f['id']])));
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
     * Import an entire Google Drive folder into a Subject with automated chapter arrangement.
     * Sorts 19 chapters numerically (1, 2, ... 19), cleans titles, organizes materials, and downloads files.
     *
     * @param  array<int, string>|null  $selectedChapterIds
     * @return array{folder_title: string, chapters_created: int, files_imported: int, errors: array<int, string>}
     */
    public static function importIntoSubject(Subject $subject, string $folderUrl, CourseOutline $outline, ?array $selectedChapterIds = null, ?string $fallbackChapterTitle = null): array
    {
        @ini_set('max_execution_time', '600');
        @set_time_limit(600);
        @ignore_user_abort(true);

        $scanned = self::scanFolder($folderUrl, false);
        $chaptersCreated = 0;
        $filesImported = 0;
        $errors = [];

        $selectedMap = ! empty($selectedChapterIds) ? array_fill_keys($selectedChapterIds, true) : null;

        // Scenario A: Drive folder has subfolders (the 19 chapters)
        if (! empty($scanned['subfolders'])) {
            // They are already sorted in strict numerical order 1..19 by scanFolder()!
            foreach ($scanned['subfolders'] as $sub) {
                if ($selectedMap !== null && ! isset($selectedMap[$sub['id']])) {
                    continue; // Skipped by user
                }

                $cleanTitle = $sub['title'];

                // Check if chapter already exists under this subject (by clean title or raw title)
                $chapter = $subject->chapters()
                    ->where(function ($q) use ($cleanTitle, $sub) {
                        $q->where('title', $cleanTitle)
                          ->orWhere('title', $sub['raw_title'])
                          ->orWhere('title', 'like', "%{$cleanTitle}%");
                    })
                    ->first();

                if (! $chapter) {
                    $chapter = $outline->addChapter($subject, $cleanTitle);
                    $chaptersCreated++;
                }

                // Scan this specific chapter folder for its files
                try {
                    $subScanned = self::scanFolder($sub['id'], false);
                    $subFiles = $subScanned['files'] ?? [];
                } catch (\Throwable $e) {
                    $errors[] = "Chapter {$sub['title']}: " . $e->getMessage();
                    continue;
                }

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

            // If root folder also contains loose files, import into general chapter
            if (! empty($scanned['files'])) {
                $rootTitle = $fallbackChapterTitle ?: ($scanned['title'] ?: 'General Materials');
                $rootChapter = $subject->chapters()->where('title', $rootTitle)->first();
                if (! $rootChapter) {
                    $rootChapter = $outline->addChapter($subject, $rootTitle);
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
            // Scenario B: Drive folder contains only direct files without subfolders
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
}
