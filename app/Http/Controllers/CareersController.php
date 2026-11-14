<?php

namespace App\Http\Controllers;

use App\Models\JobPosting;
use App\Services\RecruitmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class CareersController extends Controller
{
    public function index(): View
    {
        $vacancies = JobPosting::query()->publiclyOpen()
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('companies')
                ->whereColumn('companies.id', 'job_postings.company_id')->where('companies.is_active', true))
            ->with(['branches' => fn ($query) => $query->withoutGlobalScopes()->where('branches.is_active', true)])
            ->latest('published_at')->paginate(9);

        return view('careers.index', compact('vacancies'));
    }

    public function show(string $slug): View
    {
        $vacancy = JobPosting::query()->publiclyOpen()
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('companies')
                ->whereColumn('companies.id', 'job_postings.company_id')->where('companies.is_active', true))
            ->with(['branches' => fn ($query) => $query->withoutGlobalScopes()->where('branches.is_active', true)])
            ->where('slug', $slug)->firstOrFail();

        return view('careers.show', compact('vacancy'));
    }

    public function apply(Request $request, string $slug, RecruitmentService $recruitment): RedirectResponse
    {
        $vacancy = JobPosting::query()->publiclyOpen()
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('companies')
                ->whereColumn('companies.id', 'job_postings.company_id')->where('companies.is_active', true))
            ->with(['branches' => fn ($query) => $query->withoutGlobalScopes()->where('branches.is_active', true)])
            ->where('slug', $slug)->firstOrFail();
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:2000'],
            'preferred_branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
            'experience' => ['nullable', 'string', 'max:10000'],
            'motivation' => ['required', 'string', 'max:10000'],
            'cv' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
        ]);

        $cvPath = $request->file('cv')?->store('careers/cvs', 'local');
        try {
            $recruitment->submitApplication($vacancy, [...$validated, 'cv_path' => $cvPath]);
        } catch (Throwable $exception) {
            if ($cvPath !== null) {
                Storage::disk('local')->delete($cvPath);
            }
            throw $exception;
        }

        return redirect()->route('careers.show', $vacancy->slug)
            ->with('application_submitted', 'Your application has been received. PawHaven will contact you if you are selected.');
    }
}
