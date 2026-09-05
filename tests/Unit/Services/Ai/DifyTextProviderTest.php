<?php

declare(strict_types=1);

use App\Ai\Agents\BrandAnalyzer;
use Illuminate\Support\Facades\Http;

test('dify provider sends agent context and returns structured output', function (): void {
    config()->set([
        'services.dify.base_url' => 'https://dify.example/v1',
        'services.dify.api_key' => 'app-test-key',
    ]);

    Http::fake([
        'https://dify.example/v1/workflows/run' => Http::response([
            'data' => [
                'status' => 'succeeded',
                'outputs' => [
                    'result' => json_encode([
                        'name' => 'King Coffee',
                        'description' => 'Vietnamese coffee brand',
                        'voice_traits' => ['warm'],
                    ]),
                ],
            ],
        ]),
    ]);

    $response = (new BrandAnalyzer)->prompt('Analyze this brand.', provider: 'dify');

    expect($response->structured)
        ->toMatchArray(['name' => 'King Coffee'])
        ->and($response->meta->provider)->toBe('dify');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://dify.example/v1/workflows/run'
        && $request['inputs']['task'] === 'text_generation'
        && $request['inputs']['prompt'] === 'Analyze this brand.'
        && filled($request['inputs']['instructions'])
        && filled($request['inputs']['schema']));
});
