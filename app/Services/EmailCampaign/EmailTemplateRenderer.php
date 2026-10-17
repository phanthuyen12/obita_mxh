<?php

declare(strict_types=1);

namespace App\Services\EmailCampaign;

use App\Models\EmailCampaignRecipient;

/**
 * Replaces `{{ variable }}` placeholders in campaign subject/body with the
 * recipient's own values.
 *
 * Supported: `{{ name }}`, `{{ email }}`, and any column captured at import
 * time as `{{ attributes.<column> }}`. Values are HTML-escaped for the body so
 * a contact name cannot inject markup into the sent message.
 */
class EmailTemplateRenderer
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public function render(string $template, EmailCampaignRecipient $recipient, array $extra = [], bool $escape = true): string
    {
        $contact = $recipient->contact;

        $variables = [
            'name' => $recipient->name ?? $contact?->name ?? '',
            'email' => $recipient->email,
        ];

        foreach ((array) ($contact?->custom_fields ?? []) as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $variables["fields.{$key}"] = (string) $value;
            }
        }

        foreach ($extra as $key => $value) {
            $variables[$key] = (string) $value;
        }

        return preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/',
            function (array $matches) use ($variables, $escape): string {
                $value = $variables[$matches[1]] ?? '';

                return $escape ? e($value) : $value;
            },
            $template,
        ) ?? $template;
    }

    /**
     * The placeholders offered to the user in the campaign composer.
     *
     * @return array<int, string>
     */
    public function availableVariables(): array
    {
        return ['{{ name }}', '{{ email }}'];
    }
}
