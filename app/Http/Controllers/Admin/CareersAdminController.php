<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\EmploymentOffer;
use App\Models\AdminAuditLog;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Services\BusinessAccessService;
use App\Services\RecruitmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CareersAdminController extends Controller
{
    public function vacancies(): View
    {
        $vacancies = JobPosting::query()->withCount('applications')->with('branches')->latest()->paginate(15);

        return view('admin.careers.vacancies.index', compact('vacancies'));
    }

    public function createVacancy(): View
    {
        return view('admin.careers.vacancies.form', [
            'vacancy' => new JobPosting(['status' => 'draft', 'openings' => 1]),
            'branches' => $this->activeBranches(),
        ]);
    }

    public function storeVacancy(Request $request, RecruitmentService $recruitment): RedirectResponse
    {
        $posting = $recruitment->createPosting($request->user(), $this->validatedVacancy($request));

        return redirect()->route('admin.careers.vacancies.edit', $posting)->with('success', 'Vacancy saved.');
    }

    public function editVacancy(JobPosting $vacancy): View
    {
        $vacancy->load(['branches' => fn ($query) => $query->withoutGlobalScopes()]);

        return view('admin.careers.vacancies.form', [
            'vacancy' => $vacancy,
            'branches' => $this->activeBranches(),
        ]);
    }

    public function updateVacancy(Request $request, JobPosting $vacancy, RecruitmentService $recruitment): RedirectResponse
    {
        $recruitment->updatePosting($vacancy, $request->user(), $this->validatedVacancy($request));

        return redirect()->route('admin.careers.vacancies.edit', $vacancy)->with('success', 'Vacancy updated.');
    }

    public function applications(Request $request): View
    {
        $query = JobApplication::query()->with(['posting', 'preferredBranch'])->latest('submitted_at');
        if ($request->filled('vacancy')) {
            $query->where('job_posting_id', $request->integer('vacancy'));
        }
        if ($request->filled('status') && in_array($request->string('status')->toString(), JobApplication::STATUSES, true)) {
            $query->where('status', $request->string('status')->toString());
        }

        return view('admin.careers.applications.index', [
            'applications' => $query->paginate(20)->withQueryString(),
            'vacancies' => JobPosting::query()->orderByDesc('created_at')->get(['id', 'title']),
            'statuses' => JobApplication::STATUSES,
        ]);
    }

    public function showApplication(JobApplication $application): View
    {
        $application->load([
            'posting',
            'preferredBranch' => fn ($query) => $query->withoutGlobalScopes(),
            'offers.branch' => fn ($query) => $query->withoutGlobalScopes(),
            'offers.employee',
        ]);

        return view('admin.careers.applications.show', [
            'application' => $application,
            'branches' => $this->activeBranches(),
        ]);
    }

    public function reviewApplication(Request $request, JobApplication $application, RecruitmentService $recruitment): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['shortlisted', 'selected', 'rejected'])],
            'internal_notes' => ['nullable', 'string', 'max:10000'],
        ]);
        $recruitment->reviewApplication($application, $request->user(), $validated['status'], $validated['internal_notes'] ?? null);

        return back()->with('success', 'Application review saved.');
    }

    public function downloadCv(JobApplication $application)
    {
        abort_unless($application->cv_path && Storage::disk('local')->exists($application->cv_path), 404);
        AdminAuditLog::record('careers.application.cv_downloaded', 'An authorized admin downloaded a private candidate CV.', [
            'job_posting_id' => $application->job_posting_id,
            'application_id' => $application->id,
        ]);
        $extension = pathinfo($application->cv_path, PATHINFO_EXTENSION);
        $downloadName = 'candidate-'.$application->id.($extension ? '.'.$extension : '');

        return Storage::disk('local')->download($application->cv_path, $downloadName, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function issueOffer(Request $request, JobApplication $application, RecruitmentService $recruitment): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')],
            'employment_terms' => ['required', 'string', 'max:20000'],
            'compensation' => ['nullable', 'string', 'max:10000'],
            'acceptance_deadline' => ['required', 'date', 'after:now'],
        ]);
        $recruitment->issueOffer(
            $application,
            $request->user(),
            (int) $validated['branch_id'],
            $validated['employment_terms'],
            $validated['compensation'] ?? null,
            new \DateTimeImmutable($validated['acceptance_deadline']),
        );

        return back()->with('success', 'A formal offer was created and emailed. The secure offer link was not retained in the admin screen.');
    }

    public function resendOffer(EmploymentOffer $offer, RecruitmentService $recruitment): RedirectResponse
    {
        $recruitment->resendOffer($offer);

        return back()->with('success', 'A fresh offer link was emailed; the previous link is no longer valid.');
    }

    public function revokeOffer(Request $request, EmploymentOffer $offer, RecruitmentService $recruitment): RedirectResponse
    {
        $recruitment->revokeOffer($offer, $request->user());

        return back()->with('success', 'Offer revoked.');
    }

    private function validatedVacancy(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'position_type' => ['required', Rule::in(JobPosting::POSITIONS)],
            'summary' => ['required', 'string', 'max:500'],
            'description' => ['required', 'string', 'max:20000'],
            'responsibilities' => ['nullable', 'string', 'max:20000'],
            'requirements' => ['nullable', 'string', 'max:20000'],
            'employment_type' => ['nullable', 'string', 'max:60'],
            'openings' => ['required', 'integer', 'min:1', 'max:999'],
            'application_deadline' => ['nullable', 'date', 'after_or_equal:today'],
            'status' => ['required', Rule::in(JobPosting::STATUSES)],
            'branch_ids' => ['nullable', 'array', 'max:50'],
            'branch_ids.*' => ['integer', Rule::exists('branches', 'id')],
        ]);
    }

    private function activeBranches()
    {
        $company = app(BusinessAccessService::class)->installationCompany();

        return Branch::query()->withoutGlobalScopes()->where('company_id', $company->id)
            ->where('is_active', true)->orderBy('name')->get();
    }
}
