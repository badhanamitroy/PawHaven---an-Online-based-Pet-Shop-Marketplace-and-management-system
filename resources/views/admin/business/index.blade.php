@extends('layouts.admin')

@section('title', 'Companies & Branch Access')

@section('content')
<main style="flex:1;min-width:0;padding:24px 32px 40px;display:flex;flex-direction:column;gap:20px;box-sizing:border-box">
    {{-- Page Header --}}
    <header style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
        <div style="flex:1;min-width:260px">
            <div style="font-size:12px;color:#5B6460">Business / Companies &amp; Branch Access</div>
            <h1 style="margin:2px 0 0;font-size:24px;font-weight:700;letter-spacing:-.01em;color:#101814">Companies &amp; branch access</h1>
            <div style="font-size:13px;color:#5B6460;margin-top:2px">Manage operational companies, locations and least-privilege staff assignments.</div>
        </div>
        <a class="btn" href="{{ route('public.home') }}" target="_blank" style="background:#fff;border:1px solid #D3D9D5;color:#1B2420">
            View public store ↗
        </a>
    </header>

    {{-- Note Box --}}
    <div class="note">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" style="flex:none;margin-top:1px">
            <circle cx="12" cy="12" r="9"/>
            <path d="M12 11v5M12 8h.01"/>
        </svg>
        <span>Payments are outside this workspace. Assignments here control operational access only.</span>
    </div>

    {{-- Validation Errors --}}
    @if ($errors->any())
        <div class="card" style="padding:16px 20px;background:#FEF2F2;border-color:#FCA5A5;color:#991B1B;font-size:13px">
            <ul style="margin:0;padding-left:20px;display:flex;flex-direction:column;gap:4px">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Add Company Card (Admins) --}}
    @if ($canCreateCompany)
        <section class="card" style="padding:24px">
            <h2 style="margin:0;font-size:16px;font-weight:700;color:#101814">Add a company</h2>
            <p style="margin:2px 0 16px;font-size:13px;color:#5B6460">Register a new merchant organization or subsidiary operating on PawHaven.</p>

            <form method="POST" action="{{ route('admin.business.companies.store') }}" style="display:grid;gap:14px;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr))">
                @csrf
                <div class="f">
                    <label for="comp_name">Company name <span style="color:#B42318">*</span></label>
                    <input class="inp" id="comp_name" name="name" required maxlength="150" value="{{ old('name') }}" placeholder="e.g. PawHaven Operations Ltd">
                </div>
                <div class="f">
                    <label for="comp_legal">Legal name (optional)</label>
                    <input class="inp" id="comp_legal" name="legal_name" maxlength="180" value="{{ old('legal_name') }}" placeholder="e.g. PawHaven Inc.">
                </div>
                <div class="f">
                    <label for="comp_email">Contact email</label>
                    <input class="inp" id="comp_email" name="contact_email" type="email" value="{{ old('contact_email') }}" placeholder="ops@pawhaven.com">
                </div>
                <div class="f">
                    <label for="comp_phone">Phone</label>
                    <input class="inp" id="comp_phone" name="phone" maxlength="50" value="{{ old('phone') }}" placeholder="+880 1XXX-XXXXXX">
                </div>
                <div class="f" style="grid-column:1 / -1">
                    <label for="comp_addr">Business address</label>
                    <textarea class="inp" id="comp_addr" name="address" maxlength="1000" style="min-height:72px" placeholder="Street, city, postal code...">{{ old('address') }}</textarea>
                </div>
                <div style="grid-column:1 / -1">
                    <button class="btn" type="submit" style="background:#17603F;color:#fff;border:0;cursor:pointer">
                        Create company
                    </button>
                </div>
            </form>
        </section>
    @endif

    {{-- Companies List --}}
    <div style="display:flex;flex-direction:column;gap:20px">
        @forelse ($companies as $company)
            <section class="card" style="padding:24px">
                {{-- Company Info Header --}}
                <div style="display:flex;align-items:center;gap:14px;margin-bottom:20px;flex-wrap:wrap">
                    <div style="width:44px;height:44px;border-radius:10px;background:#0F2A20;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:18px">
                        {{ strtoupper(substr($company->name, 0, 1)) }}
                    </div>
                    <div style="flex:1;min-width:200px">
                        <div style="font-size:18px;font-weight:700;color:#101814">{{ $company->name }}</div>
                        <div style="font-size:13px;color:#5B6460;margin-top:2px">
                            <span style="font-family:ui-monospace,Menlo,monospace">{{ $company->slug }}</span>
                            @if($company->legal_name) · <span>{{ $company->legal_name }}</span> @endif
                            · <span style="font-weight:600;color:{{ $company->is_active ? '#14573A' : '#9B1C12' }}">{{ $company->is_active ? 'Active' : 'Inactive' }}</span>
                        </div>
                    </div>
                    @can('update', $company)
                        <details style="margin-left:auto">
                            <summary class="btn" style="background:#fff;border:1px solid #D3D9D5;color:#1B2420;cursor:pointer;list-style:none;min-height:36px;font-size:13px">
                                Edit details ▾
                            </summary>
                            <div style="margin-top:12px;padding:16px;background:#F9FAF9;border:1px solid #E3E7E4;border-radius:8px">
                                <form method="POST" action="{{ route('admin.business.companies.update', $company) }}" style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr))">
                                    @csrf
                                    @method('PUT')
                                    <div class="f">
                                        <label>Company name</label>
                                        <input class="inp" name="name" required maxlength="150" value="{{ $company->name }}">
                                    </div>
                                    <div class="f">
                                        <label>Legal name</label>
                                        <input class="inp" name="legal_name" maxlength="180" value="{{ $company->legal_name }}">
                                    </div>
                                    <div class="f">
                                        <label>Contact email</label>
                                        <input class="inp" name="contact_email" type="email" value="{{ $company->contact_email }}">
                                    </div>
                                    <div class="f">
                                        <label>Phone</label>
                                        <input class="inp" name="phone" maxlength="50" value="{{ $company->phone }}">
                                    </div>
                                    <div class="f" style="grid-column:1 / -1">
                                        <label>Address</label>
                                        <input class="inp" name="address" maxlength="1000" value="{{ $company->address }}">
                                    </div>
                                    <div style="grid-column:1 / -1;display:flex;gap:8px">
                                        <button class="btn" type="submit" style="background:#17603F;color:#fff;border:0;min-height:36px;font-size:13px;cursor:pointer">
                                            Save details
                                        </button>
                                        <button
                                            class="btn"
                                            type="submit"
                                            name="is_active"
                                            value="{{ $company->is_active ? '0' : '1' }}"
                                            style="background:#fff;border:1px solid #D3D9D5;color:{{ $company->is_active ? '#9B1C12' : '#14573A' }};min-height:36px;font-size:13px;cursor:pointer"
                                        >
                                            {{ $company->is_active ? 'Deactivate company' : 'Activate company' }}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </details>
                    @endcan
                </div>

                {{-- Two Panel Grid: Branches & Staff --}}
                <div class="two">
                    {{-- Branches Panel --}}
                    <div class="pn">
                        <h3>
                            <span>Branches</span>
                            <span style="color:#5B6460;font-weight:600">{{ $company->branches->count() }}</span>
                        </h3>
                        <div>
                            @forelse ($company->branches as $branch)
                                <div class="it">
                                    <div style="flex:1;min-width:0">
                                        <div style="font-weight:600;font-size:14px;color:#1B2420">
                                            {{ $branch->name }}
                                            <span style="font-size:12px;color:#5B6460;font-weight:normal">({{ $branch->code }})</span>
                                        </div>
                                        <div style="font-size:12.5px;color:#5B6460;margin-top:2px">
                                            {{ $branch->city ?: 'No city' }} · {{ $branch->address }}
                                        </div>
                                    </div>
                                    <span class="rp" style="background:{{ $branch->is_active ? '#E3F3E9' : '#ECEEED' }};color:{{ $branch->is_active ? '#14573A' : '#5B6460' }}">
                                        {{ $branch->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                            @empty
                                <div class="em">No branches registered.</div>
                            @endforelse
                        </div>

                        @if($canManageBranches)
                            <div style="padding:14px 18px;border-top:1px solid #E3E7E4;background:#FAF8F5">
                                <details>
                                    <summary style="font-size:12.5px;font-weight:600;color:#17603F;cursor:pointer">
                                        + Add a new branch
                                    </summary>
                                    <form method="POST" action="{{ route('admin.business.branches.store') }}" style="margin-top:12px;display:grid;gap:10px;grid-template-columns:1fr 1fr">
                                        @csrf
                                        <input type="hidden" name="company_id" value="{{ $company->id }}">
                                        <input class="inp" name="name" required maxlength="100" placeholder="Branch name" style="min-height:38px;font-size:13px">
                                        <input class="inp" name="code" required maxlength="50" placeholder="Branch code (e.g. CTG-01)" style="min-height:38px;font-size:13px">
                                        <input class="inp" name="address" required maxlength="255" placeholder="Physical address" style="min-height:38px;font-size:13px;grid-column:1 / -1">
                                        <input class="inp" name="city" maxlength="100" placeholder="City" style="min-height:38px;font-size:13px">
                                        <input class="inp" name="phone" maxlength="50" placeholder="Phone" style="min-height:38px;font-size:13px">
                                        <input class="inp" name="email" type="email" placeholder="Branch email" style="min-height:38px;font-size:13px;grid-column:1 / -1">
                                        <button class="btn" type="submit" style="background:#17603F;color:#fff;border:0;min-height:38px;font-size:13px;cursor:pointer;grid-column:1 / -1;justify-content:center">
                                            Save branch
                                        </button>
                                    </form>
                                </details>
                            </div>
                        @endif
                    </div>

                    {{-- Staff Assignments Panel --}}
                    <div class="pn">
                        <h3>
                            <span>Staff assignments</span>
                            <span style="color:#5B6460;font-weight:600">{{ $company->memberships->count() }}</span>
                        </h3>
                        <div>
                            @forelse ($company->memberships as $membership)
                                <div class="it">
                                    <div class="av">
                                        {{ strtoupper(substr($membership->user->name ?? 'U', 0, 1)) }}
                                    </div>
                                    <div style="flex:1;min-width:0">
                                        <div style="font-weight:600;font-size:14px;color:#1B2420">
                                            {{ $membership->user->name ?? 'Unknown account' }}
                                        </div>
                                        <div style="font-size:12.5px;color:#5B6460;margin-top:2px">
                                            {{ $membership->branch->name ?? 'All company branches' }}
                                        </div>
                                    </div>
                                    <span class="rp">{{ ucfirst($membership->role) }}</span>
                                    @can('delete', $membership)
                                        <form method="POST" action="{{ route('admin.business.memberships.destroy', $membership) }}" onsubmit="return confirm('Revoke operational membership for {{ $membership->user->name ?? 'staff' }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rm" title="Revoke access" aria-label="Revoke {{ $membership->user->name }}">
                                                Revoke
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            @empty
                                <div class="em">No active staff memberships.</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- Assign Staff Form --}}
                @can('manageMemberships', [\App\Models\BusinessMembership::class, $company])
                    <form method="POST" action="{{ route('admin.business.memberships.store') }}" style="margin-top:24px;padding-top:20px;border-top:1px solid #EEF1EF">
                        @csrf
                        <input type="hidden" name="company_id" value="{{ $company->id }}">
                        <h2 style="margin:0 0 4px;font-size:16px;font-weight:700;color:#101814">Assign staff</h2>
                        <p style="margin:0 0 16px;font-size:13px;color:#5B6460">Grant a staff account a role, scoped to one branch or all branches.</p>

                        <div class="asg">
                            <div class="f">
                                <label for="staff_user_{{ $company->id }}">Staff account</label>
                                <select class="inp sel" id="staff_user_{{ $company->id }}" name="user_id" required>
                                    <option value="">Select staff account</option>
                                    @foreach ($staff as $staffUser)
                                        <option value="{{ $staffUser->id }}">{{ $staffUser->name }} ({{ $staffUser->email }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="f">
                                <label for="staff_branch_{{ $company->id }}">Branch scope</label>
                                <select class="inp sel" id="staff_branch_{{ $company->id }}" name="branch_id">
                                    <option value="">All company branches</option>
                                    @foreach ($company->branches as $branch)
                                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="f">
                                <label for="staff_role_{{ $company->id }}">Role</label>
                                <select class="inp sel" id="staff_role_{{ $company->id }}" name="role" required>
                                    <option value="manager">Branch Manager</option>
                                    <option value="seller">Employee Seller</option>
                                    @if($canAssignOwner)
                                        <option value="owner">Shop Owner</option>
                                    @endif
                                </select>
                            </div>
                            <div>
                                <button class="btn" type="submit" style="background:#17603F;color:#fff;border:0;cursor:pointer;width:100%;justify-content:center">
                                    Save assignment
                                </button>
                            </div>
                        </div>
                    </form>
                @endcan
            </section>
        @empty
            <div class="card" style="padding:48px 24px;text-align:center">
                <div style="font-weight:600;font-size:15px;color:#101814">No companies are configured</div>
                <div style="font-size:13px;color:#5B6460;margin-top:4px">An administrator can create the first company using the form above.</div>
            </div>
        @endforelse
    </div>
</main>

<style>
.two { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
@media(max-width:960px){ .two { grid-template-columns:1fr; } }
.pn { border:1px solid #E3E7E4; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; background:#fff; }
.pn h3 { margin:0; padding:14px 18px; font-size:13px; letter-spacing:.06em; text-transform:uppercase; color:#46504A; background:#F4F6F5; border-bottom:1px solid #E3E7E4; display:flex; justify-content:space-between; align-items:center; }
.it { display:flex; align-items:center; gap:12px; padding:12px 18px; border-bottom:1px solid #EEF1EF; }
.it:last-child { border-bottom:0; }
.em { padding:28px 18px; text-align:center; font-size:13.5px; color:#5B6460; }
.rp { display:inline-flex; padding:3px 10px; border-radius:999px; background:#E8EEF9; color:#1D4A94; font-size:12px; font-weight:600; white-space:nowrap; }
.av { width:34px; height:34px; border-radius:50%; background:#E3F3E9; color:#14573A; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:13px; flex:none; }
.rm { min-height:36px; padding:0 10px; border:0; background:transparent; color:#9B1C12; font:inherit; font-size:13px; font-weight:600; cursor:pointer; border-radius:8px; }
.rm:hover { background:#FDECEA; }
.asg { display:grid; grid-template-columns:repeat(3, minmax(0,1fr)) auto; gap:12px; align-items:end; }
@media(max-width:900px){ .asg { grid-template-columns:1fr; } }
.note { display:flex; gap:10px; align-items:flex-start; padding:12px 16px; border-radius:10px; background:#EAF2FB; color:#1D4A94; font-size:13px; line-height:1.5; }
</style>
@endsection
