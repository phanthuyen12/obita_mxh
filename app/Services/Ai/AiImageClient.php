<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\Workspace\ContentLanguage;
use App\Enums\Workspace\ImageStyle;
use App\Services\Dify\DifyWorkflowClient;
use App\Support\HexColorName;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Image;
use Laravel\Ai\Responses\ImageResponse;
use Throwable;

class AiImageClient
{
    private const BRAND_DESCRIPTION_MAX = 200;

    public function __construct(
        private readonly ?DifyWorkflowClient $difyWorkflowClient = null,
    ) {}

    /**
     * Generate an image via the configured AI_IMAGE_PROVIDER (defaults to OpenAI).
     * Returns null on any failure so the caller can fall back to a stock photo
     * without throwing.
     *
     * @param  array<int, string>  $keywords
     * @return array{bytes: string, provider: string, model: string}|null
     */
    public function generate(
        array $keywords,
        ImageStyle $style,
        string $orientation = 'portrait',
        string $language = 'en',
        ?string $brandColor = null,
        ?string $backgroundColor = null,
        ?string $textColor = null,
        ?string $brandDescription = null,
        string $quality = 'low',
        int $timeout = 180,
        ?string $customPrompt = null,
        ?string $logoPath = null,
        ?string $customResolution = null,
        ?string $customAspectRatio = null,
    ): ?array {
        $keywords = $this->cleanKeywords($keywords);

        if ($keywords === []) {
            return null;
        }

        $prompt = $this->buildPrompt($keywords, $style, $language, $brandColor, $backgroundColor, $textColor, $brandDescription, $customPrompt, filled($logoPath));

        try {
            if (config('ai.default_for_images') === 'dify') {
                return $this->generateWithDify($prompt, $orientation, $quality, $logoPath, $customResolution, $customAspectRatio, $timeout);
            }

            $builder = Image::of($prompt)->quality($quality)->timeout($timeout);

            $builder = match ($orientation) {
                'portrait' => $builder->portrait(),
                'landscape' => $builder->landscape(),
                default => $builder->square(),
            };

            if (filled($logoPath)) {
                try {
                    $base64 = base64_encode(Storage::get($logoPath));
                    $mime = Storage::mimeType($logoPath) ?: 'image/png';
                    $key = config('ai.providers.openrouter.key', env('OPENROUTER_API_KEY'));

                    $aspectRatio = filled($customAspectRatio) ? $customAspectRatio : match ($orientation) {
                        'portrait' => '9:16',
                        'landscape' => '16:9',
                        default => '1:1',
                    };

                    $model = config('ai.providers.openrouter.models.image.default', 'x-ai/grok-imagine-image-2.0');
                    $resolution = filled($customResolution) ? $customResolution : '1K';

                    $response = Http::withToken($key)
                        ->timeout($timeout)
                        ->post('https://openrouter.ai/api/v1/images/generations', [
                            'model' => $model,
                            'prompt' => $prompt,
                            'resolution' => $resolution,
                            'aspect_ratio' => $aspectRatio,
                            'input_references' => [
                                [
                                    'type' => 'image_url',
                                    'image_url' => [
                                        'url' => "data:{$mime};base64,{$base64}",
                                    ],
                                ],
                            ],
                        ]);

                    if ($response->successful()) {
                        $data = $response->json();

                        if (isset($data['data'][0]['url'])) {
                            return [
                                'bytes' => Http::timeout($timeout)->get($data['data'][0]['url'])->body(),
                                'provider' => 'openrouter',
                                'model' => $model,
                            ];
                        } elseif (isset($data['data'][0]['b64_json'])) {
                            return [
                                'bytes' => base64_decode($data['data'][0]['b64_json']),
                                'provider' => 'openrouter',
                                'model' => $model,
                            ];
                        }
                    }

                    Log::warning('AiImageClient custom openrouter request failed', ['status' => $response->status(), 'body' => $response->body()]);
                } catch (Throwable $e) {
                    Log::warning('AiImageClient custom openrouter exception', ['error' => $e->getMessage()]);
                }

                return null;
            }

            return $this->toResult($builder->generate());
        } catch (Throwable $e) {
            Log::warning('AiImageClient: generation failed', [
                'style' => $style->value,
                'orientation' => $orientation,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** @return array{bytes: string, provider: string, model: string}|null */
    private function generateWithDify(
        string $prompt,
        string $orientation,
        string $quality,
        ?string $logoPath,
        ?string $customResolution,
        ?string $customAspectRatio,
        int $timeout,
    ): ?array {
        $inputs = [
            'content_type' => 'image',
            'requirement' => $prompt,
            'task' => 'image_generation',
            'prompt' => $prompt,
            'orientation' => $orientation,
            'quality' => $quality,
            'resolution' => $customResolution ?? '',
            'aspect_ratio' => $customAspectRatio ?? '',
        ];

        if (filled($logoPath) && Storage::exists($logoPath)) {
            $mime = Storage::mimeType($logoPath) ?: 'image/png';
            $inputs['logo_base64'] = "data:{$mime};base64,".base64_encode(Storage::get($logoPath));
        }

        $outputs = ($this->difyWorkflowClient ?? app(DifyWorkflowClient::class))->run($inputs, 'laravel-ai:image', (string) config('services.dify.image_api_key'));
        $bytes = $this->imageBytesFromOutputs($outputs, $timeout);

        if ($bytes === null) {
            Log::warning('AiImageClient: Dify workflow returned no usable image.');

            return null;
        }

        return [
            'bytes' => $bytes,
            'provider' => 'dify',
            'model' => (string) data_get($outputs, 'model', 'dify-workflow'),
        ];
    }

    /** @param array<string, mixed> $outputs */
    private function imageBytesFromOutputs(array $outputs, int $timeout): ?string
    {
        foreach (['image_base64', 'base64', 'b64_json', 'image', 'result'] as $key) {
            $value = data_get($outputs, $key);

            if (is_string($value) && $value !== '') {
                if (str_starts_with($value, 'data:image/')) {
                    $value = (string) preg_replace('/^data:image\/[a-zA-Z0-9.+-]+;base64,/', '', $value);
                }

                $decoded = base64_decode($value, true);

                if ($decoded !== false) {
                    return $decoded;
                }
            }
        }

        foreach (['image_url', 'url', 'images.0.url', 'files.0.url', 'result.url'] as $key) {
            $url = data_get($outputs, $key);

            if (is_string($url) && filter_var($url, FILTER_VALIDATE_URL)) {
                $response = Http::timeout($timeout)->get($url);

                return $response->successful() ? $response->body() : null;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $keywords
     * @return array<int, string>
     */
    private function cleanKeywords(array $keywords): array
    {
        return collect($keywords)
            ->map(fn (string $keyword) => trim($keyword))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $keywords
     */
    private function buildPrompt(
        array $keywords,
        ImageStyle $style,
        string $language,
        ?string $brandColor,
        ?string $backgroundColor,
        ?string $textColor,
        ?string $brandDescription,
        ?string $customPrompt = null,
        bool $hasLogo = false,
    ): string {
        $palette = $this->buildPaletteContext($brandColor, $backgroundColor, $textColor);

        $scene = implode(', ', $keywords);
        if (filled($customPrompt)) {
            $scene = $customPrompt.' (based on: '.$scene.')';
        }

        $lowerPrompt = strtolower((string) $customPrompt);
        $hasText = ($style === ImageStyle::Poster) || (filled($customPrompt) && (
            str_contains($lowerPrompt, 'include the text') ||
            str_contains($lowerPrompt, 'text:') ||
            str_contains($lowerPrompt, 'chữ') ||
            str_contains($lowerPrompt, 'nội dung')
        ));

        return view('prompts.post_image.generator', [
            'style' => $style->value,
            'scene' => $scene,
            'has_text' => $hasText,
            'language_name' => $this->languageName($language),
            'has_brand_palette' => data_get($palette, 'is_defined', false),
            'brand_color_name' => data_get($palette, 'brand_color_name'),
            'background_color_name' => data_get($palette, 'background_color_name'),
            'text_color_name' => data_get($palette, 'text_color_name'),
            'brand_context' => $this->resolveBrandContext($brandDescription),
            'has_logo' => $hasLogo,
        ])->render();
    }

    private function resolveBrandContext(?string $brandDescription): ?string
    {
        $trimmed = trim((string) $brandDescription);

        if ($trimmed === '') {
            return null;
        }

        return mb_strlen($trimmed) > self::BRAND_DESCRIPTION_MAX
            ? mb_substr($trimmed, 0, self::BRAND_DESCRIPTION_MAX).'…'
            : $trimmed;
    }

    /**
     * Extract the raw image bytes and the provider/model that produced them.
     * Called from inside generate()'s try block so a malformed response
     * (e.g. no images) is treated as a failure, not an uncaught exception.
     *
     * @return array{bytes: string, provider: string, model: string}|null
     */
    private function toResult(ImageResponse $response): ?array
    {
        $bytes = (string) $response;

        if ($bytes === '') {
            return null;
        }

        return [
            'bytes' => $bytes,
            'provider' => (string) $response->meta->provider,
            'model' => (string) $response->meta->model,
        ];
    }

    private function languageName(string $code): string
    {
        return (ContentLanguage::tryFrom($code) ?? ContentLanguage::DEFAULT)->englishName();
    }

    /**
     * @return array{
     *   is_defined: bool,
     *   brand_color_name: ?string,
     *   background_color_name: ?string,
     *   text_color_name: ?string
     * }
     */
    private function buildPaletteContext(
        ?string $brandColor,
        ?string $backgroundColor,
        ?string $textColor,
    ): array {
        $brandColorName = $this->resolveColorName($brandColor);
        $backgroundColorName = $this->resolveColorName($backgroundColor);
        $textColorName = $this->resolveColorName($textColor);

        return [
            'is_defined' => $brandColorName !== null || $backgroundColorName !== null || $textColorName !== null,
            'brand_color_name' => $brandColorName,
            'background_color_name' => $backgroundColorName,
            'text_color_name' => $textColorName,
        ];
    }

    private function resolveColorName(?string $hex): ?string
    {
        if ($hex === null || trim($hex) === '') {
            return null;
        }

        return HexColorName::approximate($hex);
    }
}
