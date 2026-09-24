<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebPushSubscriptionController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048'],
            'publicKey' => ['nullable', 'string'],
            'authToken' => ['nullable', 'string'],
            'contentEncoding' => ['nullable', 'string'],
        ]);

        $request->user()->updatePushSubscription(
            $validated['endpoint'],
            $validated['publicKey'] ?? null,
            $validated['authToken'] ?? null,
            $validated['contentEncoding'] ?? null,
        );

        return response()->json(['subscribed' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $endpoint = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048'],
        ])['endpoint'];

        $request->user()->deletePushSubscription($endpoint);

        return response()->json(['subscribed' => false]);
    }
}
