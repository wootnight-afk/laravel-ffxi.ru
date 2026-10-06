<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AdminAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Records administrative create/update/delete mutations in the audit log.
 *
 * The action code is `{model}.{verb}` (for example `news.created`). Only the
 * changed attributes are stored, PII is redacted and secrets/MFA fields are
 * never written (R4).
 */
final class AdminActivityLogger
{
    /**
     * Attributes replaced by a `[changed]` marker in the payload.
     *
     * @var array<int, string>
     */
    private const REDACTED_ATTRIBUTES = ['email', 'password', 'phone'];

    /**
     * Attributes that must never be written to the audit payload.
     *
     * @var array<int, string>
     */
    private const IGNORED_ATTRIBUTES = [
        'remember_token',
        'password_confirmation',
        'app_authentication_secret',
        'app_authentication_recovery_codes',
    ];

    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     * @param  array<int, string>  $redacted
     * @param  array<int, string>  $ignored
     */
    public function record(
        Model $subject,
        string $verb,
        ?array $old = null,
        ?array $new = null,
        ?string $key = null,
        array $redacted = [],
        array $ignored = [],
    ): ?AdminAuditLog {
        $key ??= Str::snake(class_basename($subject));
        $redacted = $redacted !== [] ? $redacted : self::REDACTED_ATTRIBUTES;
        $ignored = $ignored !== [] ? $ignored : self::IGNORED_ATTRIBUTES;

        $old = $this->redact($old, $redacted, $ignored);
        $new = $this->redact($new, $redacted, $ignored);

        // An update that touched nothing worth recording is skipped, so that
        // one user action produces exactly one audit entry.
        if ($verb === 'updated' && $new === null && $old === null) {
            return null;
        }

        return $this->audit->log(
            action: "{$key}.{$verb}",
            subject: $subject,
            old: $old,
            new: $new,
        );
    }

    /**
     * @param  array<string, mixed>|null  $attributes
     * @param  array<int, string>  $redacted
     * @param  array<int, string>  $ignored
     * @return array<string, mixed>|null
     */
    private function redact(?array $attributes, array $redacted, array $ignored): ?array
    {
        if ($attributes === null) {
            return null;
        }

        $result = [];

        foreach ($attributes as $attribute => $value) {
            if (in_array($attribute, $ignored, true)) {
                continue;
            }

            $result[$attribute] = in_array($attribute, $redacted, true)
                ? '[changed]'
                : $value;
        }

        return $result === [] ? null : $result;
    }
}
