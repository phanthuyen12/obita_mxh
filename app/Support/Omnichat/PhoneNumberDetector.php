<?php

declare(strict_types=1);

namespace App\Support\Omnichat;

use Illuminate\Support\Str;

class PhoneNumberDetector
{
    public function detect(?string $message): ?string
    {
        if ($message === null || $message === '') {
            return null;
        }

        preg_match_all('/(?<!\d)(?:\+?84|0)(?:[\s.\-]?\d){9}(?!\d)/u', $message, $matches);
        $candidate = data_get($matches, '0.0');

        if (! is_string($candidate)) {
            return null;
        }

        $digits = Str::of($candidate)->replaceMatches('/\D+/', '')->toString();

        if (Str::startsWith($digits, '84')) {
            $digits = '0'.Str::after($digits, '84');
        }

        return preg_match('/^0(?:3|5|7|8|9)\d{8}$/', $digits) === 1 ? $digits : null;
    }
}
