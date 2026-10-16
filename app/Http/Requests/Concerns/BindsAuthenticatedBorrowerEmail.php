<?php

namespace App\Http\Requests\Concerns;

use App\Models\User;

/**
 * Bind borrower identity on create/update according to role.
 *
 * - Admin / Super Admin may submit borrower_name + borrower_email for someone else.
 * - Everyone else is forced to the authenticated account (request spoofing ignored).
 */
trait BindsAuthenticatedBorrowerEmail
{
    protected function canProxyBorrowerIdentity(?User $user = null): bool
    {
        $user ??= $this->user();

        return $user !== null && $user->hasAnyRole(['super-admin', 'admin']);
    }

    /**
     * Create flows: force identity for non-proxy users; normalize manual input for admins.
     */
    protected function bindAuthenticatedBorrowerEmail(): void
    {
        $this->bindBorrowerIdentityForCreate();
    }

    protected function bindBorrowerIdentityForCreate(): void
    {
        if ($this->canProxyBorrowerIdentity()) {
            $this->mergeNormalizedBorrowerIdentity(
                $this->input('borrower_name'),
                $this->input('borrower_email'),
            );

            return;
        }

        $user = $this->user();
        $this->mergeNormalizedBorrowerIdentity(
            $user?->name,
            $user?->email,
        );
    }

    /**
     * Update flows: non-proxy keep historical identity; admins may change it.
     */
    protected function bindBorrowerIdentityForUpdate(string $historicalName, ?string $historicalEmail): void
    {
        if ($this->canProxyBorrowerIdentity()) {
            $this->mergeNormalizedBorrowerIdentity(
                $this->input('borrower_name', $historicalName),
                $this->input('borrower_email', $historicalEmail),
            );

            return;
        }

        $this->mergeNormalizedBorrowerIdentity($historicalName, $historicalEmail);
    }

    protected function mergeNormalizedBorrowerIdentity(mixed $name, mixed $email): void
    {
        $name = trim((string) ($name ?? ''));
        $email = strtolower(trim((string) ($email ?? '')));

        $this->merge([
            'borrower_name' => $name !== '' ? $name : null,
            'borrower_email' => $email !== '' ? $email : null,
        ]);
    }
}
