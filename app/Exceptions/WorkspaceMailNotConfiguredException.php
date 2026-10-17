<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a workspace tries to send marketing email before configuring its
 * own SMTP credentials. Marketing mail must never fall back to the platform
 * mailer: a spam complaint there would poison transactional delivery
 * (signup, password reset) for every workspace.
 */
class WorkspaceMailNotConfiguredException extends RuntimeException
{
    public static function forWorkspace(string $workspaceId): self
    {
        return new self("Workspace [{$workspaceId}] has no SMTP configuration. Configure it before sending campaigns.");
    }
}
