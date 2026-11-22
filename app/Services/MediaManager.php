<?php

namespace App\Services;

use App\Facades\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class MediaManager
{
    /**
     * Whitelist of canonical managed assets with constraints and descriptions.
     *
     * @var array<string, array<string, mixed>>
     */
    public const CANONICAL_ASSETS = [
        'hero_banner' => [
            'key' => 'hero_banner',
            'relative_path' => 'Media/Images/Hero-Banner.png',
            'label' => 'Homepage Hero Banner',
            'description' => 'Main photographic banner displayed on the public homepage hero section.',
            'type' => 'banner',
            'allowed_extensions' => ['png', 'jpg', 'jpeg', 'webp'],
            'allowed_mimes' => ['image/png', 'image/jpeg', 'image/webp'],
            'max_kb' => 10240, // 10 MB
            'max_width' => 5000,
            'max_height' => 4000,
            'min_width' => 600,
            'min_height' => 300,
            'setting_key' => 'brand.hero_banner_path',
        ],
        'logo_green' => [
            'key' => 'logo_green',
            'relative_path' => 'Media/Logos/PawHeaven-Green-logo.png',
            'label' => 'Primary Green Logo (Light Backgrounds)',
            'description' => 'Brand logo used in the public website header, marketplace header, and light surfaces.',
            'type' => 'logo',
            'allowed_extensions' => ['png', 'jpg', 'jpeg', 'webp', 'svg'],
            'allowed_mimes' => ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'],
            'max_kb' => 3072, // 3 MB
            'max_width' => 3000,
            'max_height' => 3000,
            'min_width' => 40,
            'min_height' => 20,
            'setting_key' => 'brand.logo_light_path',
        ],
        'logo_white' => [
            'key' => 'logo_white',
            'relative_path' => 'Media/Logos/PawHeaven-White-logo.png',
            'label' => 'White / High-Contrast Logo (Dark Backgrounds)',
            'description' => 'Brand logo used in the website footer, dark admin sidebar, and authentication panels.',
            'type' => 'logo',
            'allowed_extensions' => ['png', 'jpg', 'jpeg', 'webp', 'svg'],
            'allowed_mimes' => ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'],
            'max_kb' => 3072, // 3 MB
            'max_width' => 3000,
            'max_height' => 3000,
            'min_width' => 40,
            'min_height' => 20,
            'setting_key' => 'brand.logo_dark_path',
        ],
        'logo_navy' => [
            'key' => 'logo_navy',
            'relative_path' => 'Media/Logos/PawHeaven-NavyBlue-logo.png',
            'label' => 'Navy Blue Logo (Alternate / Invoices)',
            'description' => 'Alternate brand variant used for receipts, customer invoices, and business printouts.',
            'type' => 'logo',
            'allowed_extensions' => ['png', 'jpg', 'jpeg', 'webp', 'svg'],
            'allowed_mimes' => ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'],
            'max_kb' => 3072, // 3 MB
            'max_width' => 3000,
            'max_height' => 3000,
            'min_width' => 40,
            'min_height' => 20,
            'setting_key' => 'brand.logo_navy_path',
        ],
        'logo_icon' => [
            'key' => 'logo_icon',
            'relative_path' => 'Media/Logos/PawHeaven-icon.png',
            'label' => 'Brand Icon & Browser Favicon',
            'description' => 'Square brand icon and browser tab favicon mark.',
            'type' => 'icon',
            'allowed_extensions' => ['png', 'ico', 'svg'],
            'allowed_mimes' => ['image/png', 'image/x-icon', 'image/vnd.microsoft.icon', 'image/svg+xml'],
            'max_kb' => 2048, // 2 MB
            'max_width' => 2000,
            'max_height' => 2000,
            'min_width' => 16,
            'min_height' => 16,
            'setting_key' => 'brand.favicon_path',
        ],
        'paw_cursor_svg' => [
            'key' => 'paw_cursor_svg',
            'relative_path' => 'Media/paw-cursor.svg',
            'label' => 'Custom Paw Cursor (SVG Vector)',
            'description' => 'Vector silhouette asset for the optional public site paw cursor.',
            'type' => 'cursor',
            'allowed_extensions' => ['svg'],
            'allowed_mimes' => ['image/svg+xml', 'text/plain', 'text/xml'],
            'max_kb' => 512,
            'max_width' => 128,
            'max_height' => 128,
            'min_width' => 16,
            'min_height' => 16,
            'setting_key' => null,
        ],
        'paw_cursor_png' => [
            'key' => 'paw_cursor_png',
            'relative_path' => 'Media/paw-cursor.png',
            'label' => 'Custom Paw Cursor (PNG Fallback)',
            'description' => 'Transparent raster fallback asset for legacy browser cursor rendering.',
            'type' => 'cursor',
            'allowed_extensions' => ['png'],
            'allowed_mimes' => ['image/png'],
            'max_kb' => 512,
            'max_width' => 128,
            'max_height' => 128,
            'min_width' => 16,
            'min_height' => 16,
            'setting_key' => null,
        ],
    ];

    /**
     * Resolve a public URL for a canonical key or relative path with automatic filemtime cache-busting.
     *
     * If the buyer replaces the file in public/Media with the same filename,
     * this dynamically appends ?v=<filemtime> so browsers load the updated asset immediately.
     */
    public static function url(string $keyOrPath, ?string $fallback = null): string
    {
        $relativePath = self::resolveRelativePath($keyOrPath);

        // Security check: prevent directory traversal
        if (str_contains($relativePath, '..') || str_starts_with($relativePath, '/') || str_starts_with($relativePath, '\\')) {
            $relativePath = ltrim(str_replace(['..', '\\'], ['', '/'], $relativePath), '/');
        }

        $fullPath = public_path($relativePath);

        if (file_exists($fullPath) && is_file($fullPath)) {
            $mtime = filemtime($fullPath);

            return asset($relativePath) . '?v=' . ($mtime !== false ? $mtime : '1');
        }

        if ($fallback !== null) {
            return $fallback;
        }

        // Return standard asset URL even if missing on disk, allowing standard browser 404 or image placeholder
        return asset($relativePath);
    }

    /**
     * Direct shortcut for the canonical homepage hero banner.
     */
    public static function heroBannerUrl(?string $fallback = null): string
    {
        return self::url('hero_banner', $fallback);
    }

    /**
     * Direct shortcut for canonical brand logos.
     *
     * @param string $variant 'green'|'light' (default), 'white'|'dark', or 'navy'
     */
    public static function logoUrl(string $variant = 'green'): string
    {
        $canonicalKey = match (strtolower($variant)) {
            'white', 'dark' => 'logo_white',
            'navy', 'blue', 'navyblue' => 'logo_navy',
            default => 'logo_green',
        };

        return self::url($canonicalKey);
    }

    /**
     * Direct shortcut for the canonical brand icon / favicon.
     */
    public static function iconUrl(): string
    {
        return self::url('logo_icon');
    }

    /**
     * Direct shortcut for the custom paw cursor asset.
     */
    public static function cursorUrl(string $format = 'svg'): string
    {
        $key = strtolower($format) === 'png' ? 'paw_cursor_png' : 'paw_cursor_svg';

        return self::url($key);
    }

    /**
     * Return comprehensive details and live metadata for all canonical assets.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function allAssetsDetails(): array
    {
        $details = [];
        foreach (self::CANONICAL_ASSETS as $key => $config) {
            $details[$key] = self::assetDetails($key);
        }

        return $details;
    }

    /**
     * Return metadata and health information for a specific canonical asset.
     *
     * @return array<string, mixed>
     */
    public static function assetDetails(string $key): array
    {
        if (! isset(self::CANONICAL_ASSETS[$key])) {
            throw new \InvalidArgumentException("Unknown canonical asset key: {$key}");
        }

        $config = self::CANONICAL_ASSETS[$key];
        $fullPath = public_path($config['relative_path']);
        $exists = file_exists($fullPath) && is_file($fullPath);
        $sizeBytes = $exists ? (filesize($fullPath) ?: 0) : 0;
        $mtime = $exists ? filemtime($fullPath) : null;

        $dimensions = 'N/A';
        if ($exists && ! str_ends_with(strtolower($fullPath), '.svg')) {
            $imgInfo = @getimagesize($fullPath);
            if ($imgInfo !== false) {
                $dimensions = "{$imgInfo[0]} × {$imgInfo[1]} px";
            }
        } elseif ($exists && str_ends_with(strtolower($fullPath), '.svg')) {
            $dimensions = 'Scalable Vector (SVG)';
        }

        return array_merge($config, [
            'full_path' => $fullPath,
            'exists' => $exists,
            'size_bytes' => $sizeBytes,
            'size_human' => self::formatBytes($sizeBytes),
            'dimensions' => $dimensions,
            'last_modified' => $mtime ? date('Y-m-d H:i:s', $mtime) : 'Never',
            'mtime' => $mtime,
            'url' => self::url($key),
        ]);
    }

    /**
     * Safely and atomically replace a canonical asset on disk with an uploaded file.
     *
     * Enforces strict validation, path whitelist verification, and transactional rollback.
     *
     * @throws \InvalidArgumentException|\RuntimeException
     * @return array{success: bool, key: string, path: string, url: string, message: string}
     */
    public static function replaceCanonicalAsset(string $targetKey, UploadedFile $file): array
    {
        if (! isset(self::CANONICAL_ASSETS[$targetKey])) {
            throw new \InvalidArgumentException("Invalid asset target: [{$targetKey}]. Upload rejected.");
        }

        $config = self::CANONICAL_ASSETS[$targetKey];

        if (! $file->isValid()) {
            throw new \RuntimeException('The uploaded file was corrupted or exceeded server upload limits.');
        }

        // 1. Extension & MIME validation
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, $config['allowed_extensions'], true)) {
            $allowed = implode(', ', array_map('strtoupper', $config['allowed_extensions']));
            throw new \InvalidArgumentException("Invalid file extension. [{$config['label']}] accepts: {$allowed}.");
        }

        $mime = strtolower((string) ($file->getMimeType() ?: ''));
        if (! in_array($mime, $config['allowed_mimes'], true)) {
            // Also inspect with finfo
            $finfoMime = @mime_content_type($file->getRealPath());
            if ($finfoMime && ! in_array(strtolower($finfoMime), $config['allowed_mimes'], true)) {
                throw new \InvalidArgumentException("Invalid image content. File MIME type [{$mime}] is not permitted.");
            }
        }

        // 2. File size validation
        $fileSizeBytes = $file->getSize();
        $maxSizeBytes = $config['max_kb'] * 1024;
        if ($fileSizeBytes > $maxSizeBytes) {
            $maxMb = round($config['max_kb'] / 1024, 1);
            throw new \InvalidArgumentException("File size exceeds maximum permitted limit of {$maxMb} MB.");
        }

        // 3. Image dimensions validation (for raster formats)
        if ($ext !== 'svg' && $ext !== 'ico') {
            $imageInfo = @getimagesize($file->getRealPath());
            if ($imageInfo === false) {
                throw new \InvalidArgumentException('Uploaded file is not a valid recognized image.');
            }

            [$width, $height] = $imageInfo;
            if ($width > $config['max_width'] || $height > $config['max_height']) {
                throw new \InvalidArgumentException("Image dimensions ({$width}×{$height}px) exceed maximum allowed ({$config['max_width']}×{$config['max_height']}px).");
            }
            if ($width < $config['min_width'] || $height < $config['min_height']) {
                throw new \InvalidArgumentException("Image dimensions ({$width}×{$height}px) are smaller than required minimum ({$config['min_width']}×{$config['min_height']}px).");
            }
        }

        // 4. Safe atomic replacement
        $destinationPath = public_path($config['relative_path']);
        $destinationDir = dirname($destinationPath);
        File::ensureDirectoryExists($destinationDir);

        $tempPath = $destinationPath . '.tmp.' . uniqid('', true);
        $backupPath = $destinationPath . '.bak';

        try {
            // Step A: Move uploaded file to temporary file in the target directory
            $file->move($destinationDir, basename($tempPath));

            // Step B: Backup current canonical file if it exists
            if (file_exists($destinationPath)) {
                @copy($destinationPath, $backupPath);
            }

            // Step C: Overwrite target canonical file
            if (! @copy($tempPath, $destinationPath)) {
                throw new \RuntimeException("Failed to write to canonical asset path: {$destinationPath}");
            }

            // Step D: Remove temporary file
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }

            // Step E: Invalidate PHP stat cache
            clearstatcache(true, $destinationPath);

            // Step F: Remove backup on verified success
            if (file_exists($backupPath)) {
                @unlink($backupPath);
            }

            // Step G: Synchronize corresponding setting if configured
            if (! empty($config['setting_key'])) {
                Settings::set($config['setting_key'], '/' . ltrim($config['relative_path'], '/'), 'brand');
            }

            Log::info("Canonical media asset replaced successfully: [{$targetKey}] -> {$config['relative_path']}");

            return [
                'success' => true,
                'key' => $targetKey,
                'path' => $config['relative_path'],
                'url' => self::url($targetKey),
                'message' => "Successfully updated [{$config['label']}]. Canonical file updated on disk.",
            ];
        } catch (\Throwable $e) {
            // Rollback from backup if write failed
            if (file_exists($backupPath)) {
                @copy($backupPath, $destinationPath);
                @unlink($backupPath);
                clearstatcache(true, $destinationPath);
            }
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }

            Log::error("Failed to replace canonical asset [{$targetKey}]: " . $e->getMessage());

            throw new \RuntimeException("Asset update failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Helper to resolve a canonical key or raw path to a relative public path.
     */
    protected static function resolveRelativePath(string $keyOrPath): string
    {
        if (isset(self::CANONICAL_ASSETS[$keyOrPath])) {
            return self::CANONICAL_ASSETS[$keyOrPath]['relative_path'];
        }

        return ltrim($keyOrPath, '/\\');
    }

    /**
     * Format byte values into human-readable strings.
     */
    protected static function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $i = (int) floor(log($bytes, 1024));

        return round($bytes / (1024 ** $i), 2) . ' ' . ($units[$i] ?? 'B');
    }
}
