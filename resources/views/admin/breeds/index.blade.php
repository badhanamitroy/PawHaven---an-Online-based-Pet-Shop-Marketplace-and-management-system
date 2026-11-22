@extends('layouts.admin')

@section('title', 'Breed Encyclopedia')

@section('content')
<main style="flex:1;min-width:0;padding:24px 32px 40px;display:flex;flex-direction:column;gap:20px;box-sizing:border-box">
    {{-- Page Header --}}
    <header style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
        <div style="flex:1;min-width:260px">
            <div style="font-size:12px;color:#5B6460">Business / Breed Profiles</div>
            <h1 style="margin:2px 0 0;font-size:24px;font-weight:700;letter-spacing:-.01em;color:#101814">Breed encyclopedia</h1>
            <div style="font-size:13px;color:#5B6460;margin-top:2px">Manage standard breed profiles, physical characteristics, temperament notes and care information.</div>
        </div>
        <a class="btn" href="{{ route('public.home') }}" target="_blank" style="background:#fff;border:1px solid #D3D9D5;color:#1B2420">
            View public store ↗
        </a>
    </header>

    <div class="cols2">
        {{-- Left Column: Add Breed Profile Form (Sticky) --}}
        <section class="card" style="padding:24px;position:sticky;top:20px">
            <h2 style="margin:0;font-size:16px;font-weight:700;color:#101814">Add breed profile</h2>
            <p style="margin:2px 0 18px;font-size:13px;color:#5B6460">Profiles appear in the public breed directory.</p>

            <form method="POST" action="{{ route('admin.breeds.store') }}" id="f" novalidate>
                @csrf
                <div class="f">
                    <label for="pet_category_id">Pet category <span style="color:#B42318" aria-hidden="true">*</span></label>
                    <select class="inp sel" id="pet_category_id" name="pet_category_id" required>
                        <option value="">Select category</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ old('pet_category_id') == $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('pet_category_id')
                        <div class="err" role="alert">{{ $message }}</div>
                    @enderror
                </div>

                <div class="f" style="margin-top:16px">
                    <label for="name">Breed name <span style="color:#B42318" aria-hidden="true">*</span></label>
                    <input class="inp" id="name" name="name" value="{{ old('name') }}" placeholder="e.g. Golden Retriever" maxlength="80" required autocomplete="off">
                    @error('name')
                        <div class="err" role="alert">{{ $message }}</div>
                    @enderror
                </div>

                <div class="f" style="margin-top:16px">
                    <label for="description">Overview &amp; temperament</label>
                    <textarea class="inp" id="description" name="description" placeholder="Intelligent, friendly, and devoted…">{{ old('description') }}</textarea>
                </div>

                <div class="f" style="margin-top:16px">
                    <label for="care_information">Care &amp; exercise needs</label>
                    <textarea class="inp" id="care_information" name="care_information" placeholder="Requires daily vigorous exercise and regular grooming…">{{ old('care_information') }}</textarea>
                </div>

                <button class="btn" type="submit" style="width:100%;justify-content:center;background:#17603F;color:#fff;border:0;cursor:pointer;margin-top:14px">
                    Save breed profile
                </button>
            </form>
        </section>

        {{-- Right Column: Filter Bar & Breeds Cards Grid --}}
        <div style="display:flex;flex-direction:column;gap:16px;min-width:0">
            {{-- Filter Bar --}}
            <form class="card fb" id="ff" method="GET" action="{{ route('admin.breeds.index') }}" role="search">
                <div class="f">
                    <label for="q">Search</label>
                    <input class="inp" id="q" name="search" type="search" value="{{ request('search') }}" placeholder="Search breed by name">
                </div>
                <div class="f">
                    <label for="fc">Category</label>
                    <select class="inp sel" id="fc" name="category">
                        <option value="">All categories</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ request('category') == $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div style="display:flex;gap:8px">
                    <button class="btn" type="submit" style="background:#17603F;color:#fff;border:0;cursor:pointer">
                        Filter
                    </button>
                    @if(request()->hasAny(['search', 'category']))
                        <a class="btn" href="{{ route('admin.breeds.index') }}" style="background:#fff;border:1px solid #D3D9D5;color:#1B2420">
                            Reset
                        </a>
                    @endif
                </div>
            </form>

            <div style="font-size:13px;color:#5B6460" id="cnt" aria-live="polite">
                {{ $breeds->total() }} {{ $breeds->total() === 1 ? 'breed profile' : 'breed profiles' }}
            </div>

            @if($breeds->isEmpty())
                <section class="card" id="empty" style="padding:48px 24px;text-align:center">
                    <div style="font-weight:600;font-size:15px;color:#101814">
                        {{ request()->hasAny(['search', 'category']) ? 'No matching breed profiles' : 'No breed profiles found' }}
                    </div>
                    <div style="font-size:13px;color:#5B6460;margin-top:4px">
                        {{ request()->hasAny(['search', 'category']) ? 'Try a different search, or reset the filters.' : 'Add the first breed using the form on the left.' }}
                    </div>
                </section>
            @else
                <div class="grid" id="grid">
                    @foreach($breeds as $breed)
                        <article class="card bc">
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px">
                                <div>
                                    <span class="cp">{{ $breed->petCategory?->name ?? 'General' }}</span>
                                    <h3 style="margin:8px 0 0;font-size:16px;font-weight:700;color:#101814">{{ $breed->name }}</h3>
                                    <div style="font-size:12px;color:#5B6460;margin-top:2px">
                                        {{ $breed->listings_count }} {{ $breed->listings_count === 1 ? 'listing' : 'listings' }}
                                    </div>
                                </div>
                                <form
                                    method="POST"
                                    action="{{ route('admin.breeds.destroy', $breed) }}"
                                    onsubmit="return confirm('Delete breed \'{{ $breed->name }}\'?');"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rm" aria-label="Delete {{ $breed->name }}">
                                        Delete
                                    </button>
                                </form>
                            </div>

                            @if($breed->description)
                                <div style="margin-top:4px">
                                    <div class="k">Overview &amp; temperament</div>
                                    <p>{{ $breed->description }}</p>
                                </div>
                            @endif

                            @if($breed->care_information)
                                <div style="margin-top:4px">
                                    <div class="k">Care &amp; exercise</div>
                                    <p>{{ $breed->care_information }}</p>
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>

                @if($breeds->hasPages())
                    <div style="padding:16px 0">
                        {{ $breeds->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</main>

<style>
.cols2 { display:grid; grid-template-columns:380px minmax(0,1fr); gap:20px; align-items:start; }
@media(max-width:1000px){ .cols2 { grid-template-columns:1fr; } }
.fb { display:grid; grid-template-columns:minmax(0,1fr) 200px auto; gap:12px; align-items:end; padding:16px 20px; }
@media(max-width:700px){ .fb { grid-template-columns:1fr; } }
.grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:16px; }
.bc { padding:18px; display:flex; flex-direction:column; gap:10px; }
.cp { display:inline-flex; padding:3px 10px; border-radius:999px; background:#E3F3E9; color:#14573A; font-size:12px; font-weight:600; }
.bc h3 { margin:0; font-size:16px; color:#101814; }
.bc .k { font-size:11px; letter-spacing:.08em; text-transform:uppercase; color:#5B6460; font-weight:600; margin-bottom:2px; }
.bc p { margin:0; font-size:13.5px; line-height:1.55; color:#3B4540; }
.rm { min-height:36px; padding:0 10px; border:0; background:transparent; color:#9B1C12; font:inherit; font-size:13px; font-weight:600; cursor:pointer; border-radius:8px; }
.rm:hover { background:#FDECEA; }
</style>
@endsection
