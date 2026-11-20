<?php

namespace Tests\Feature\Admin;

use App\Facades\Settings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_brand_identity_settings(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($admin)->put(route('admin.brand.update'), [
            'brand_name' => 'PawHaven Pet Sanctuary',
            'brand_tagline' => 'Connecting Companions with Loving Hearts',
            'brand_primary_color' => '#1b4d3e',
            'brand_secondary_color' => '#0f2b23',
            'brand_accent_color' => '#e07a5f',
            'brand_contact_email' => 'contact@pawhavensanctuary.com',
            'brand_phone' => '+1 800 555 0199',
            'brand_address' => '789 Harbor View Blvd, Seattle, WA',
            'brand_social_facebook' => 'https://facebook.com/pawhavensanctuary',
            'brand_social_instagram' => 'https://instagram.com/pawhavensanctuary',
            'brand_social_x' => 'https://x.com/pawhavensanct',
            'brand_meta_description' => 'A premier community for pet adoption and organic nutrition.',
        ]);

        $response->assertRedirect(route('admin.brand.index'));
        $response->assertSessionHas('success');

        $this->assertSame('PawHaven Pet Sanctuary', Settings::get('brand.name'));
        $this->assertSame('Connecting Companions with Loving Hearts', Settings::get('brand.tagline'));
        $this->assertSame('#1b4d3e', Settings::get('brand.primary_color'));

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'brand.update',
            'user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_upload_light_and_dark_logos_and_favicon(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $greenBytes = file_get_contents(public_path('images/pawhaven-logo-green.png'));
        $whiteBytes = file_get_contents(public_path('images/pawhaven-logo-white.png'));

        $lightLogo = UploadedFile::fake()->createWithContent('light-logo.png', $greenBytes);
        $darkLogo = UploadedFile::fake()->createWithContent('dark-logo.png', $whiteBytes);
        $favicon = UploadedFile::fake()->create('favicon.ico', 32, 'image/x-icon');

        $response = $this->actingAs($admin)->put(route('admin.brand.update'), [
            'brand_name' => 'PawHaven',
            'brand_tagline' => 'Safe Ethical Pet Platform',
            'brand_primary_color' => '#245C45',
            'brand_secondary_color' => '#173F32',
            'brand_accent_color' => '#F28C75',
            'brand_contact_email' => 'hello@example.test',
            'brand_meta_description' => 'Meta description for testing',
            'logo_light' => $lightLogo,
            'logo_dark' => $darkLogo,
            'favicon' => $favicon,
        ]);

        $response->assertRedirect(route('admin.brand.index'));
        $response->assertSessionHas('success');

        $storedLight = Settings::get('brand.logo_light_path');
        $storedDark = Settings::get('brand.logo_dark_path');
        $storedFavicon = Settings::get('brand.favicon_path');

        $this->assertStringStartsWith('/storage/branding/', $storedLight);
        $this->assertStringStartsWith('/storage/branding/', $storedDark);
        $this->assertStringStartsWith('/storage/branding/', $storedFavicon);

        $lightRelative = str_replace('/storage/', '', $storedLight);
        $darkRelative = str_replace('/storage/', '', $storedDark);
        $faviconRelative = str_replace('/storage/', '', $storedFavicon);

        Storage::disk('public')->assertExists($lightRelative);
        Storage::disk('public')->assertExists($darkRelative);
        Storage::disk('public')->assertExists($faviconRelative);
    }

    public function test_replacing_logo_deletes_old_uploaded_file(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $greenBytes = file_get_contents(public_path('images/pawhaven-logo-green.png'));
        $whiteBytes = file_get_contents(public_path('images/pawhaven-logo-white.png'));

        // Upload first logo
        $firstLogo = UploadedFile::fake()->createWithContent('first-logo.png', $greenBytes);
        $this->actingAs($admin)->put(route('admin.brand.update'), [
            'brand_name' => 'PawHaven',
            'brand_tagline' => 'Tagline',
            'brand_primary_color' => '#245C45',
            'brand_secondary_color' => '#173F32',
            'brand_accent_color' => '#F28C75',
            'brand_contact_email' => 'hello@example.test',
            'brand_meta_description' => 'Meta description',
            'logo_light' => $firstLogo,
        ]);

        $firstStoredPath = Settings::get('brand.logo_light_path');
        $firstRelative = str_replace('/storage/', '', $firstStoredPath);
        Storage::disk('public')->assertExists($firstRelative);

        // Upload second replacement logo
        $secondLogo = UploadedFile::fake()->createWithContent('second-logo.png', $whiteBytes);
        $this->actingAs($admin)->put(route('admin.brand.update'), [
            'brand_name' => 'PawHaven',
            'brand_tagline' => 'Tagline',
            'brand_primary_color' => '#245C45',
            'brand_secondary_color' => '#173F32',
            'brand_accent_color' => '#F28C75',
            'brand_contact_email' => 'hello@example.test',
            'brand_meta_description' => 'Meta description',
            'logo_light' => $secondLogo,
        ]);

        $secondStoredPath = Settings::get('brand.logo_light_path');
        $secondRelative = str_replace('/storage/', '', $secondStoredPath);

        $this->assertNotSame($firstStoredPath, $secondStoredPath);
        Storage::disk('public')->assertMissing($firstRelative);
        Storage::disk('public')->assertExists($secondRelative);
    }

    public function test_default_logo_retained_when_no_custom_logo_uploaded(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        Settings::set('brand.logo_light_path', '/images/pawhaven-logo-green.png');

        $this->actingAs($admin)->put(route('admin.brand.update'), [
            'brand_name' => 'PawHaven Updated Name',
            'brand_tagline' => 'New Tagline',
            'brand_primary_color' => '#245C45',
            'brand_secondary_color' => '#173F32',
            'brand_accent_color' => '#F28C75',
            'brand_contact_email' => 'hello@example.test',
            'brand_meta_description' => 'Meta description',
        ]);

        $this->assertSame('/images/pawhaven-logo-green.png', Settings::get('brand.logo_light_path'));
        $this->assertSame('PawHaven Updated Name', Settings::get('brand.name'));
    }

    public function test_invalid_file_extension_and_oversized_file_rejected(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        // 1. Invalid file extension (e.g. text or pdf file)
        $invalidFile = UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');

        $response = $this->actingAs($admin)->put(route('admin.brand.update'), [
            'brand_name' => 'PawHaven',
            'brand_tagline' => 'Tagline',
            'brand_primary_color' => '#245C45',
            'brand_secondary_color' => '#173F32',
            'brand_accent_color' => '#F28C75',
            'brand_contact_email' => 'hello@example.test',
            'brand_meta_description' => 'Meta description',
            'logo_light' => $invalidFile,
        ]);

        $response->assertSessionHasErrors(['logo_light']);

        // 2. Oversized image (> 2MB = 2048KB)
        $greenBytes = file_get_contents(public_path('images/pawhaven-logo-green.png'));
        $oversizedBytes = $greenBytes.str_repeat('X', 3 * 1024 * 1024);
        $oversizedImage = UploadedFile::fake()->createWithContent('huge-logo.png', $oversizedBytes);

        $response2 = $this->actingAs($admin)->put(route('admin.brand.update'), [
            'brand_name' => 'PawHaven',
            'brand_tagline' => 'Tagline',
            'brand_primary_color' => '#245C45',
            'brand_secondary_color' => '#173F32',
            'brand_accent_color' => '#F28C75',
            'brand_contact_email' => 'hello@example.test',
            'brand_meta_description' => 'Meta description',
            'logo_light' => $oversizedImage,
        ]);

        $response2->assertSessionHasErrors(['logo_light']);

        // 3. Oversized dimensions (> 3000px)
        $oversizedDimBytes = $greenBytes;
        // In PNG, bytes 16-19 are width (big endian integer), bytes 20-23 are height (big endian integer)
        $oversizedDimBytes = substr_replace($oversizedDimBytes, pack('NN', 3500, 3500), 16, 8);
        $oversizedDimImage = UploadedFile::fake()->createWithContent('giant-dimensions.png', $oversizedDimBytes);

        $response3 = $this->actingAs($admin)->put(route('admin.brand.update'), [
            'brand_name' => 'PawHaven',
            'brand_tagline' => 'Tagline',
            'brand_primary_color' => '#245C45',
            'brand_secondary_color' => '#173F32',
            'brand_accent_color' => '#F28C75',
            'brand_contact_email' => 'hello@example.test',
            'brand_meta_description' => 'Meta description',
            'logo_light' => $oversizedDimImage,
        ]);

        $response3->assertSessionHasErrors(['logo_light']);
    }

    public function test_staff_user_cannot_update_brand_identity(): void
    {
        $staff = User::factory()->create([
            'role' => 'staff',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($staff)->put(route('admin.brand.update'), [
            'brand_name' => 'Attempted Hack',
            'brand_tagline' => 'Tagline',
            'brand_primary_color' => '#245C45',
            'brand_secondary_color' => '#173F32',
            'brand_accent_color' => '#F28C75',
            'brand_contact_email' => 'hacker@example.com',
            'brand_meta_description' => 'Meta description',
        ]);

        $response->assertForbidden();
    }

    public function test_invalid_color_format_is_rejected(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($admin)->put(route('admin.brand.update'), [
            'brand_name' => 'PawHaven',
            'brand_tagline' => 'Tagline',
            'brand_primary_color' => 'not-a-color',
            'brand_secondary_color' => '#173F32',
            'brand_accent_color' => '#F28C75',
            'brand_contact_email' => 'contact@example.test',
            'brand_meta_description' => 'Meta description',
        ]);

        $response->assertSessionHasErrors(['brand_primary_color']);
    }
}
