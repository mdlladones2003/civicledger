<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\ServiceRecord;

/**
 * Stable canonical payloads (TRD §6). Recomputed from the authoritative (live) rows at verification,
 * so editing an approved database value changes the commitment => Mismatch.
 * Payloads stay in MySQL only; the chain receives an HMAC commitment, never these fields.
 */
class CanonicalPayload
{
    public static function forServiceRecord(ServiceRecord $record, int $version): array
    {
        $record->loadMissing('attendance');
        $a = $record->attendance;

        return self::normalize([
            'type' => 'service_record',
            'version' => $version,
            'service_record_id' => $record->id,
            'student_id' => $record->student_id,
            'attendance_id' => $record->attendance_id,
            'session_id' => $a?->session_id,
            'check_in_at' => self::ts($a?->check_in_at),
            'check_out_at' => self::ts($a?->check_out_at),
            'approved_minutes' => (int) $record->approved_minutes,
            'approved_by' => $record->approved_by,
            'approved_at' => self::ts($record->approved_at),
        ]);
    }

    public static function forCertificate(Certificate $certificate): array
    {
        return self::normalize([
            'type' => 'certificate',
            'version' => 1,
            'certificate_id' => $certificate->id,
            'certificate_number' => $certificate->certificate_number,
            'student_id' => $certificate->student_id,
            'term_id' => $certificate->term_id,
            'issued_by' => $certificate->issued_by,
            'approved_minutes' => (int) $certificate->approved_minutes,
            'issued_at' => self::ts($certificate->issued_at),
            'supersedes_certificate_id' => $certificate->supersedes_certificate_id,
        ]);
    }

    public static function json(array $payload): string
    {
        return json_encode(self::normalize($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private static function normalize(array $data): array
    {
        ksort($data);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::normalize($value);
            }
        }

        return $data;
    }

    private static function ts($value): ?string
    {
        return $value?->copy()->utc()->format('Y-m-d\TH:i:s\Z');
    }
}
