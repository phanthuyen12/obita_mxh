<?php

declare(strict_types=1);

namespace App\Actions\Omnichat;

use App\Models\OmnichatMessage;
use App\Models\OmnichatTag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DetectPhoneNumberFromMessage
{
    public function execute(OmnichatMessage $message): ?string
    {
        if ($message->direction !== 'inbound' || blank($message->body) || $message->sender_contact_id === null) {
            return null;
        }

        $phone = $this->extract((string) $message->body);
        if ($phone === null) {
            return null;
        }

        $contact = $message->senderContact()->first();
        if ($contact === null) {
            return null;
        }

        DB::transaction(function () use ($contact, $message, $phone): void {
            $detectedPhones = collect(data_get($contact->meta, 'detected_phones', []))
                ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
                ->push($phone)
                ->unique()
                ->values()
                ->all();

            $contact->update([
                'phone' => $contact->phone ?: $phone,
                'is_lead' => true,
                'phone_detected_at' => $contact->phone_detected_at ?? $message->sent_at ?? now(),
                'meta' => array_replace($contact->meta ?? [], [
                    'detected_phones' => $detectedPhones,
                    'phone_source_message_id' => $message->id,
                ]),
            ]);

            $phoneTag = OmnichatTag::query()->firstOrCreate(
                [
                    'workspace_id' => $message->workspace_id,
                    'name' => 'CÓ SĐT',
                ],
                ['color' => '#16A34A'],
            );

            $message->conversation()->first()?->tags()->syncWithoutDetaching([$phoneTag->id]);
        });

        return $phone;
    }

    public function extract(string $body): ?string
    {
        $normalizedBody = Str::of($body)->replaceMatches('/[\x{00A0}\x{2007}\x{202F}]/u', ' ')->toString();

        if (preg_match('/(?<!\d)(?:(?:\+?84|0)[\s.\-]?(?:\d[\s.\-]?){8,10})(?!\d)/u', $normalizedBody, $matches) !== 1
            && preg_match('/(?<!\d)\+[1-9](?:[\s.\-]?\d){7,14}(?!\d)/u', $normalizedBody, $matches) !== 1) {
            return null;
        }

        $phone = preg_replace('/[^\d+]/', '', $matches[0]);
        if (! is_string($phone)) {
            return null;
        }

        if (str_starts_with($phone, '+84')) {
            $phone = '0'.substr($phone, 3);
        } elseif (str_starts_with($phone, '84') && strlen($phone) >= 10) {
            $phone = '0'.substr($phone, 2);
        }

        return strlen(preg_replace('/\D/', '', $phone) ?? '') >= 9 ? $phone : null;
    }
}
