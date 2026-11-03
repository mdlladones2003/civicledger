<?php

namespace App\Http\Controllers;

use App\Enums\VerificationState;
use App\Models\Certificate;
use App\Services\CertificateService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * S16: narrow, read-only public endpoint. Route is rate-limited; token is unguessable.
 * Never exposes student ID, email, or attendance detail.
 */
class PublicVerificationController extends Controller
{
    public function show(Request $request, string $token, CertificateService $certificates): Response
    {
        $certificate = Str::length($token) <= 64
            ? Certificate::with(['student:id,first_name,middle_name,last_name', 'term:id,code', 'proof'])->where('public_token', $token)->first()
            : null;

        if (! $certificate) {
            return Inertia::render('Public/Verify', [
                'state' => VerificationState::NotFound->value,
                'certificate' => null,
                'evidence' => null,
                'disclaimer' => $this->disclaimer(),
            ])->toResponse($request)->setStatusCode(404);
        }

        $state = $certificates->verificationState($certificate);
        $proof = $certificate->proof;

        $response = Inertia::render('Public/Verify', [
            'state' => $state->value,
            'certificate' => [
                'number' => $certificate->certificate_number,
                'holder' => $this->mask($certificate->student->full_name),
                'term' => $certificate->term->code,
                'hours' => round($certificate->approved_minutes / 60, 2),
                'issued_at' => $certificate->issued_at->toDateString(),
                'status' => $certificate->status,
                'revoked_at' => $certificate->revoked_at?->toDateString(),
            ],
            // Optional expandable technical evidence (Design Brief §4).
            'evidence' => $proof ? [
                'proof_status' => $proof->proof_status,
                'validity_status' => $proof->validity_status,
                'chain_id' => $proof->chain_id,
                'contract_address' => $proof->contract_address,
                'tx_hash' => $proof->tx_hash,
            ] : null,
            'disclaimer' => $this->disclaimer(),
        ])->toResponse($request);

        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    private function mask(string $name): string
    {
        return collect(explode(' ', $name))
            ->filter()
            ->map(fn (string $part) => Str::substr($part, 0, 1).'***')
            ->implode(' ');
    }

    private function disclaimer(): string
    {
        return 'This check confirms the integrity of the record, not that the person physically took part. '
            .'The certificate is supplementary and not an official NSTP completion record unless the school adopts it.';
    }
}
