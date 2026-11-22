<img src="{{ \App\Services\MediaManager::iconUrl() }}" alt="{{ \App\Facades\Settings::get('brand.name', config('app.name', 'PawHaven')) }}" {{ $attributes->merge(['class' => 'object-contain']) }} />
