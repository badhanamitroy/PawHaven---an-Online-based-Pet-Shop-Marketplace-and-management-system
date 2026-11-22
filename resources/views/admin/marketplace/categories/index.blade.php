@extends('layouts.admin')

@section('title', 'Pet Categories')

@section('content')
<main style="flex:1;min-width:0;padding:24px 32px 40px;display:flex;flex-direction:column;gap:20px;box-sizing:border-box">
    {{-- Page Header --}}
    <header style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
        <div style="flex:1;min-width:240px">
            <div style="font-size:12px;color:#5B6460">Business / Species Categories</div>
            <h1 style="margin:2px 0 0;font-size:24px;font-weight:700;letter-spacing:-.01em;color:#101814">Pet categories</h1>
            <div style="font-size:13px;color:#5B6460;margin-top:2px">Species classifications for adoption and marketplace listings (Dogs, Cats, Birds, Small Animals, etc.).</div>
        </div>
        <a class="btn" href="{{ route('public.home') }}" target="_blank" style="background:#fff;border:1px solid #D3D9D5;color:#1B2420">View public store ↗</a>
    </header>

    <div class="cols2">
        {{-- Left Column: Add Category Form --}}
        <section class="card" style="padding:24px">
            <h2 style="margin:0;font-size:16px;font-weight:700;color:#101814">Add category</h2>
            <p style="margin:2px 0 18px;font-size:13px;color:#5B6460">Categories appear as filters on the public store.</p>

            <form method="POST" action="{{ route('admin.marketplace.categories.store') }}" id="f" novalidate>
                @csrf
                <label class="lbl" for="name">Category name <span style="color:#B42318" aria-hidden="true">*</span></label>
                <input
                    class="inp @error('name') aria-invalid="true" @enderror"
                    id="name"
                    name="name"
                    value="{{ old('name') }}"
                    placeholder="e.g. Reptiles &amp; Amphibians"
                    maxlength="60"
                    required
                    autocomplete="off"
                >
                @error('name')
                    <div class="err" role="alert">{{ $message }}</div>
                @enderror

                <button class="btn" type="submit" style="width:100%;justify-content:center;background:#17603F;color:#fff;border:0;cursor:pointer;margin-top:12px">
                    Save category
                </button>
            </form>

            <div style="margin-top:22px;padding-top:18px;border-top:1px solid #EEF1EF">
                <div class="lbl">Quick add</div>
                <div style="display:flex;flex-wrap:wrap;gap:8px" id="chips">
                    <button type="button" class="chip" onclick="quickAdd('Dogs')">Dogs</button>
                    <button type="button" class="chip" onclick="quickAdd('Cats')">Cats</button>
                    <button type="button" class="chip" onclick="quickAdd('Birds')">Birds</button>
                    <button type="button" class="chip" onclick="quickAdd('Small Animals')">Small Animals</button>
                    <button type="button" class="chip" onclick="quickAdd('Fish')">Fish</button>
                </div>
            </div>
        </section>

        {{-- Right Column: Active Categories Table --}}
        <section class="card" style="overflow:hidden">
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:18px 20px;border-bottom:1px solid #E3E7E4">
                <h2 style="margin:0;font-size:16px;font-weight:700;color:#101814;flex:1">
                    Active categories
                    <span id="tot" style="font-weight:500;color:#5B6460;font-size:13px;margin-left:6px">{{ $categories->count() }} total</span>
                </h2>
                <input class="inp" id="q" type="search" placeholder="Search categories" aria-label="Search categories" style="max-width:220px">
            </div>

            @if($categories->isEmpty())
                <div id="empty" style="padding:48px 24px;text-align:center">
                    <div style="font-weight:600;font-size:15px;color:#101814">No pet categories defined yet</div>
                    <div style="font-size:13px;color:#5B6460;margin-top:4px">Add your first category, or use Quick add on the left.</div>
                </div>
            @else
                <div style="overflow-x:auto">
                    <table id="tb" style="width:100%;border-collapse:collapse;font-size:14px">
                        <thead>
                            <tr>
                                <th style="text-align:left;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#5B6460;font-weight:600;padding:12px 20px;background:#F4F6F5;border-bottom:1px solid #E3E7E4">Category</th>
                                <th style="text-align:left;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#5B6460;font-weight:600;padding:12px 20px;background:#F4F6F5;border-bottom:1px solid #E3E7E4">Slug</th>
                                <th style="text-align:center;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#5B6460;font-weight:600;padding:12px 20px;background:#F4F6F5;border-bottom:1px solid #E3E7E4">Breeds</th>
                                <th style="text-align:center;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#5B6460;font-weight:600;padding:12px 20px;background:#F4F6F5;border-bottom:1px solid #E3E7E4">Listings</th>
                                <th style="text-align:left;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#5B6460;font-weight:600;padding:12px 20px;background:#F4F6F5;border-bottom:1px solid #E3E7E4">Status</th>
                                <th style="text-align:right;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#5B6460;font-weight:600;padding:12px 20px;background:#F4F6F5;border-bottom:1px solid #E3E7E4"><span style="position:absolute;left:-9999px">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody id="rows">
                            @foreach($categories as $cat)
                                <tr class="cat-row" data-name="{{ strtolower($cat->name) }}" data-slug="{{ strtolower($cat->slug) }}">
                                    <td style="padding:14px 20px;border-bottom:1px solid #EEF1EF;font-weight:600;color:#1B2420">{{ $cat->name }}</td>
                                    <td style="padding:14px 20px;border-bottom:1px solid #EEF1EF;font-family:ui-monospace,Menlo,monospace;font-size:12.5px;color:#5B6460">{{ $cat->slug }}</td>
                                    <td style="padding:14px 20px;border-bottom:1px solid #EEF1EF;text-align:center">
                                        <a href="{{ route('admin.breeds.index', ['category' => $cat->id]) }}" class="chip" style="min-height:28px;padding:2px 10px;font-size:12px;font-weight:600">
                                            {{ $cat->breeds_count }}
                                        </a>
                                    </td>
                                    <td style="padding:14px 20px;border-bottom:1px solid #EEF1EF;text-align:center">
                                        <a href="{{ route('admin.marketplace.listings.index', ['category' => $cat->id]) }}" class="chip" style="min-height:28px;padding:2px 10px;font-size:12px;font-weight:600;color:#17603F;background:#E3F3E9;border-color:transparent">
                                            {{ $cat->pet_listings_count }}
                                        </a>
                                    </td>
                                    <td style="padding:14px 20px;border-bottom:1px solid #EEF1EF">
                                        <span style="display:inline-flex;padding:3px 10px;border-radius:999px;background:#E3F3E9;color:#14573A;font-size:12px;font-weight:600">Active</span>
                                    </td>
                                    <td style="padding:14px 20px;border-bottom:1px solid #EEF1EF;text-align:right">
                                        <form
                                            method="POST"
                                            action="{{ route('admin.marketplace.categories.destroy', $cat) }}"
                                            onsubmit="return confirm('Delete category \'{{ $cat->name }}\'?');"
                                            style="display:inline"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                style="width:40px;height:40px;border:0;border-radius:8px;background:transparent;color:#5B6460;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;transition:background-color .15s,color .15s"
                                                onmouseover="this.style.background='#FDECEA';this.style.color='#B42318'"
                                                onmouseout="this.style.background='transparent';this.style.color='#5B6460'"
                                                title="Delete {{ $cat->name }}"
                                                aria-label="Delete {{ $cat->name }}"
                                            >
                                                <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/></svg>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div id="filter-empty" style="display:none;padding:48px 24px;text-align:center">
                    <div style="font-weight:600;font-size:15px;color:#101814">No matching categories</div>
                    <div style="font-size:13px;color:#5B6460;margin-top:4px">Try adjusting your search query.</div>
                </div>
            @endif
        </section>
    </div>
</main>

<style>
.cols2 { display:grid; grid-template-columns:340px minmax(0,1fr); gap:20px; align-items:start; }
@media(max-width:1000px){ .cols2 { grid-template-columns:1fr; } }
</style>

<script>
function quickAdd(name) {
    var inp = document.getElementById('name');
    inp.value = name;
    inp.focus();
}

document.getElementById('q')?.addEventListener('input', function() {
    var query = this.value.toLowerCase().trim();
    var rows = document.querySelectorAll('.cat-row');
    var visibleCount = 0;

    rows.forEach(function(row) {
        var name = row.getAttribute('data-name') || '';
        var slug = row.getAttribute('data-slug') || '';
        if (!query || name.indexOf(query) > -1 || slug.indexOf(query) > -1) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    var tb = document.getElementById('tb');
    var empty = document.getElementById('filter-empty');
    var tot = document.getElementById('tot');

    if (empty) {
        empty.style.display = (visibleCount === 0 && rows.length > 0) ? 'block' : 'none';
    }
    if (tb) {
        tb.style.display = (visibleCount === 0 && rows.length > 0) ? 'none' : 'table';
    }
    if (tot) {
        tot.textContent = visibleCount + ' total';
    }
});
</script>
@endsection
