<?php

namespace App\Http\Controllers\Admin;

use App\Facades\Settings;
use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class BrandIdentityController extends Controller
{
    public function index(): View
    {
        return view('admin.brand.index', [
            'settings' => Settings::all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'brand_name' => ['required', 'string', 'max:100'],
            'brand_tagline' => ['required', 'string', 'max:255'],
            'brand_primary_color' => ['required', 'string', 'regex:/^#([a-fA-F0-9]{3}|[a-fA-F0-9]{6})$/'],
            'brand_secondary_color' => ['required', 'string', 'regex:/^#([a-fA-F0-9]{3}|[a-fA-F0-9]{6})$/'],
            'brand_accent_color' => ['required', 'string', 'regex:/^#([a-fA-F0-9]{3}|[a-fA-F0-9]{6})$/'],
            'brand_contact_email' => ['required', 'email', 'max:255'],
            'brand_phone' => ['nullable', 'string', 'max:50'],
            'brand_address' => ['nullable', 'string', 'max:500'],
            'brand_social_facebook' => ['nullable', 'url', 'max:255'],
            'brand_social_instagram' => ['nullable', 'url', 'max:255'],
            'brand_social_x' => ['nullable', 'url', 'max:255'],
            'brand_meta_description' => ['required', 'string', 'max:500'],
            'logo_light' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:max_width=3000,max_height=3000'],
            'logo_dark' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:max_width=3000,max_height=3000'],
            'favicon' => ['nullable', 'file', 'mimes:ico,png', 'max:512'],
        ]);

        $settingsToSave = [
            'brand.name' => $validated['brand_name'],
            'brand.tagline' => $validated['brand_tagline'],
            'brand.primary_color' => $validated['brand_primary_color'],
            'brand.secondary_color' => $validated['brand_secondary_color'],
            'brand.accent_color' => $validated['brand_accent_color'],
            'brand.contact_email' => $validated['brand_contact_email'],
            'brand.phone' => $validated['brand_phone'] ?? '',
            'brand.address' => $validated['brand_address'] ?? '',
            'brand.social_facebook' => $validated['brand_social_facebook'] ?? '',
            'brand.social_instagram' => $validated['brand_social_instagram'] ?? '',
            'brand.social_x' => $validated['brand_social_x'] ?? '',
            'brand.meta_description' => $validated['brand_meta_description'],
        ];

        try {
            if ($lightLogoPath = $this->handleFileUpload($request, 'logo_light', 'brand.logo_light_path')) {
                $settingsToSave['brand.logo_light_path'] = $lightLogoPath;
            }

            if ($darkLogoPath = $this->handleFileUpload($request, 'logo_dark', 'brand.logo_dark_path')) {
                $settingsToSave['brand.logo_dark_path'] = $darkLogoPath;
            }

            if ($faviconPath = $this->handleFileUpload($request, 'favicon', 'brand.favicon_path')) {
                $settingsToSave['brand.favicon_path'] = $faviconPath;
            }
        } catch (\Throwable $e) {
            Log::error('Branding file upload failed.', [
                'exception_type' => get_class($e),
            ]);

            return back()->withInput()->withErrors([
                'logo_light' => 'Failed to store uploaded branding assets. Please try again.',
            ]);
        }

        Settings::setMany($settingsToSave, 'brand');

        AdminAuditLog::record(
            'brand.update',
            'Updated brand identity and logo settings.',
            ['name' => $validated['brand_name']]
        );

        return redirect()->route('admin.brand.index')->with('success', 'Brand identity settings updated successfully.');
    }

    /**
     * Store an uploaded branding asset securely and clean up any previous managed file.
     */
    protected function handleFileUpload(Request $request, string $field, string $settingKey): ?string
    {
        if (! $request->hasFile($field)) {
            return null;
        }

        $file = $request->file($field);
        $storedPath = $file->store('branding', 'public');

        if ($storedPath === false) {
            throw new \RuntimeException("Failed to store uploaded {$field}.");
        }

        // Delete old application-managed file if one exists
        $currentPath = Settings::get($settingKey);
        if ($currentPath && is_string($currentPath)) {
            $this->cleanupOldBrandingFile($currentPath);
        }

        return '/storage/'.ltrim($storedPath, '/');
    }

    /**
     * Safely delete a previously uploaded branding file from the public disk if managed.
     */
    protected function cleanupOldBrandingFile(string $path): void
    {
        $parsed = parse_url($path, PHP_URL_PATH);
        $target = is_string($parsed) ? $parsed : $path;

        if (preg_match('#(?:^|/)storage/(branding/[A-Za-z0-9_.\-]+)$#', $target, $matches) === 1) {
            $storageRelative = $matches[1];
            if (Storage::disk('public')->exists($storageRelative)) {
                Storage::disk('public')->delete($storageRelative);
            }
        }
    }
}
