<?php

namespace App\Http\Controllers;

use App\Http\Requests\IssueCertificateRequest;
use App\Models\Certificate;
use App\Models\Term;
use App\Models\User;
use App\Models\UserSectionAssignment;
use App\Services\CertificateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class CertificateController extends Controller
{
    public function __construct(private CertificateService $certificates) {}

    /** Students: own certificates (S07). Signers: eligible candidates + issued list in scope (S13). */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->hasPermission('users.manage')) {
            return Inertia::render('Certificates/Index', [
                'certificates' => Certificate::query()
                    ->with(['student:id,first_name,middle_name,last_name', 'term:id,code', 'proof:id,certificate_id,proof_status,validity_status'])
                    ->latest('issued_at')
                    ->paginate(20)
                    ->withQueryString()
                    ->through(fn (Certificate $certificate) => $certificate
                        ->setAttribute('public_url', $certificate->publicUrl())),
            ]);
        }

        if ($user->hasPermission('certificates.issue')) {
            $studentsInScope = UserSectionAssignment::query()
                ->whereIn('section_id', $user->assignedSectionIds('signer'))
                ->where('scope_role', 'student')
                ->select('user_id');

            return Inertia::render('Certificates/Signer', [
                'candidates' => $this->certificates->eligibleCandidates($user),
                'canRevoke' => $user->hasPermission('certificates.revoke'),
                'issued' => Certificate::whereIn('student_id', $studentsInScope)
                    ->with(['student:id,first_name,middle_name,last_name', 'term:id,code', 'proof:id,certificate_id,proof_status,validity_status'])
                    ->latest('issued_at')
                    ->paginate(20)
                    ->withQueryString()
                    ->through(fn (Certificate $certificate) => $certificate
                        ->setAttribute('public_url', $certificate->publicUrl())),
            ]);
        }

        $mine = Certificate::where('student_id', $user->id)
            ->with(['term:id,code', 'proof:id,certificate_id,proof_status,validity_status'])
            ->latest('issued_at')
            ->get()
            ->map(fn (Certificate $c) => $c->makeVisible('public_token')->setAttribute('public_url', $c->publicUrl()));

        return Inertia::render('Certificates/Mine', ['certificates' => $mine]);
    }

    public function store(IssueCertificateRequest $request)
    {
        $student = User::findOrFail($request->integer('student_id'));
        $term = Term::findOrFail($request->integer('term_id'));

        Gate::authorize('issueFor', [Certificate::class, $student, $term]);

        $this->certificates->issue($request->user(), $student, $term);

        return back()->with('success', 'Certificate issued. Proof queued.');
    }

    /** F5.4: superseding retires a certificate and issues another, so it needs both authorities. */
    public function supersede(Request $request, Certificate $certificate)
    {
        Gate::authorize('revoke', $certificate);
        Gate::authorize('issueFor', [Certificate::class, $certificate->student, $certificate->term]);

        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']]);
        $this->certificates->supersede($request->user(), $certificate, $data['reason']);

        return back()->with('success', 'Certificate superseded. Proof queued for the replacement.');
    }

    public function revoke(Request $request, Certificate $certificate)
    {
        Gate::authorize('revoke', $certificate);

        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']]);
        $this->certificates->revoke($request->user(), $certificate, $data['reason']);

        return back()->with('success', 'Certificate revoked.');
    }
}
