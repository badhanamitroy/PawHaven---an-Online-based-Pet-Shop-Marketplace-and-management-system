<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

@php($brandName = \App\Facades\Settings::get('brand.name', config('app.name', 'PawHaven')))
<title>
    {{ filled($title ?? null) ? $title.' - '.$brandName : $brandName }}
</title>

@include('partials.favicon')
@include('partials.custom-cursor')

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
