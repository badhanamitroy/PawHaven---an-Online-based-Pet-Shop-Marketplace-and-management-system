<?php

namespace App\Http\Controllers\Admin;

use App\Facades\Settings;
use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Services\MediaManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class BrandIdentityController extends Controller
{
    /**
     * Display the Brand Identity and Media Management settings page.
     */
    public function index(): View
    {
        return view('admin.brand.index', [
            'settings' => Settings::all(),
            'mediaAssets' => MediaManager::allAssetsDetails(),
        ]);
    }

    /**
     * Update brand configuration, social links, colors, custom paw cursor, and optional file replacements.
     */
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
            'custom_paw_cursor' => ['nullable', 'boolean'],
            'hero_banner' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:10240'],
            'logo_light' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:3072'],
            'logo_dark' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:3072'],
            'logo_navy' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:3072'],
            'favicon' => ['nullable', 'file', 'mimes:png,ico,svg', 'max:2048'],
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
            'brand.custom_paw_cursor' => $request->boolean('custom_paw_cursor'),
        ];

        $replacedAssets = [];

        try {
            // Process canonical asset uploads if submitted
            if ($request->hasFile('hero_banner')) {
                MediaManager::replaceCanonicalAsset('hero_banner', $request->file('hero_banner'));
                $replacedAssets[] = 'Hero Banner';
            }

            if ($request->hasFile('logo_light')) {
                MediaManager::replaceCanonicalAsset('logo_green', $request->file('logo_light'));
                $replacedAssets[] = 'Primary Green Logo';
            }

            if ($request->hasFile('logo_dark')) {
                MediaManager::replaceCanonicalAsset('logo_white', $request->file('logo_dark'));
                $replacedAssets[] = 'Dark/White Logo';
            }

            if ($request->hasFile('logo_navy')) {
                MediaManager::replaceCanonicalAsset('logo_navy', $request->file('logo_navy'));
                $replacedAssets[] = 'Navy Blue Logo';
            }

            if ($request->hasFile('favicon')) {
                MediaManager::replaceCanonicalAsset('logo_icon', $request->file('favicon'));
                $replacedAssets[] = 'Brand Icon & Favicon';
            }
        } catch (\Throwable $e) {
            Log::error('Canonical media replacement failed during brand settings update.', [
                'exception_type' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return back()->withInput()->withErrors([
                'media_upload' => 'Failed to store uploaded media assets: ' . $e->getMessage(),
            ]);
        }

        // Persist brand settings
        Settings::setMany($settingsToSave, 'brand');

        $message = 'Brand identity settings updated successfully.';
        if (! empty($replacedAssets)) {
            $message .= ' Replaced canonical files: ' . implode(', ', $replacedAssets) . '.';
        }

        AdminAuditLog::record(
            'brand.update',
            'Updated brand identity settings, cursor preference, and media assets.',
            [
                'name' => $validated['brand_name'],
                'custom_paw_cursor' => $request->boolean('custom_paw_cursor'),
                'replaced_assets' => $replacedAssets,
            ]
        );

        return redirect()->route('admin.brand.index')->with('success', $message);
    }

    /**
     * Dedicated single-asset canonical replacement endpoint.
     */
    public function replaceMedia(Request $request): RedirectResponse
    {
        $allowedTargets = implode(',', array_keys(MediaManager::CANONICAL_ASSETS));

        $validated = $request->validate([
            'target' => ['required', 'string', "in:{$allowedTargets}"],
            'media_file' => ['required', 'file'],
        ]);

        try {
            $result = MediaManager::replaceCanonicalAsset(
                $validated['target'],
                $request->file('media_file')
            );

            AdminAuditLog::record(
                'brand.media_replace',
                "Replaced canonical media asset: {$validated['target']}",
                ['target' => $validated['target'], 'path' => $result['path']]
            );

            return redirect()->route('admin.brand.index')->with('success', $result['message']);
        } catch (\Throwable $e) {
            Log::error("Failed to replace media asset [{$validated['target']}]: " . $e->getMessage());

            return redirect()->route('admin.brand.index')->with('error', $e->getMessage());
        }
    }
}
