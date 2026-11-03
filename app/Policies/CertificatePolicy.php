<?php

namespace App\Policies;

use App\Models\Certificate;
use App\Models\Term;
use App\Models\User;

class CertificatePolicy
{
    public function view(User $user, Certificate $certificate): bool
    {
        return $certificate->student_id === $user->id
            || ($user->hasPermission('certificates.issue') && $user->signerCoversStudent($certificate->student, $certificate->term_id));
    }

    /** F1.4 / F5.1: signer permission AND scope over the student + term. Usage: Gate::authorize('issueFor', [Certificate::class, $student, $term]) */
    public function issueFor(User $user, User $student, Term $term): bool
    {
        return $user->hasPermission('certificates.issue')
            && $user->id !== $student->id
            && $user->signerCoversStudent($student, $term->id);
    }

    public function revoke(User $user, Certificate $certificate): bool
    {
        return $user->hasPermission('certificates.revoke')
            && $user->signerCoversStudent($certificate->student, $certificate->term_id);
    }
}
