<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AiConfiguration
{
    public const CONTENT_CLONE_PROVIDER = 'content_clone.ai_provider';

    public const TEXT_PROVIDER = 'ai.default';

    public const IMAGE_PROVIDER = 'ai.default_for_images';

    public const DIFY_BASE_URL = 'services.dify.base_url';

    public const DIFY_API_KEY = 'services.dify.api_key';

    public const DIFY_TEXT_API_KEY = 'services.dify.text_api_key';

    public const DIFY_IMAGE_API_KEY = 'services.dify.image_api_key';

    public const DIFY_CONTENT_CLONE_API_KEY = 'services.dify.content_clone_api_key';

    public const DIFY_CONNECT_TIMEOUT = 'services.dify.connect_timeout';

    public const DIFY_TIMEOUT = 'services.dify.timeout';

    /** @var array<string, string> */
    private const PROVIDER_KEY_PATHS = [
        'openai' => 'ai.providers.openai.key',
        'anthropic' => 'ai.providers.anthropic.key',
        'gemini' => 'ai.providers.gemini.key',
        'openrouter' => 'ai.providers.openrouter.key',
    ];

    /** @var array<string, array<string, string>> */
    private const PROVIDER_MODEL_PATHS = [
        'openai' => [
            'text' => 'ai.providers.openai.models.text.default',
            'image' => 'ai.providers.openai.models.image.default',
        ],
        'anthropic' => [
            'text' => 'ai.providers.anthropic.models.text.default',
        ],
        'gemini' => [
            'text' => 'ai.providers.gemini.models.text.default',
            'image' => 'ai.providers.gemini.models.image.default',
        ],
        'openrouter' => [
            'text' => 'ai.providers.openrouter.models.text.default',
            'image' => 'ai.providers.openrouter.models.image.default',
        ],
    ];

    /** @return array<int, string> */
    public static function textProviders(): array
    {
        return ['dify', 'openai', 'anthropic', 'gemini', 'openrouter', 'xai', 'groq', 'mistral', 'deepseek'];
    }

    /** @return array<int, string> */
    public static function imageProviders(): array
    {
        return ['dify', 'openai', 'gemini', 'xai', 'openrouter'];
    }

    /** @return array<int, string> */
    public static function contentCloneProviders(): array
    {
        return ['openai', 'openrouter', 'dify', 'anthropic', 'gemini', 'deepseek'];
    }

    /** @return array<int, string> */
    public static function configurableSecretProviders(): array
    {
        return array_keys(self::PROVIDER_KEY_PATHS);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function providerModelCapabilities(): array
    {
        return collect(self::PROVIDER_MODEL_PATHS)
            ->map(fn (array $paths): array => array_keys($paths))
            ->all();
    }

    public function apply(): void
    {
        foreach ($this->allSettings() as $key => $value) {
            config([$key => $value]);
        }
    }

    /**
     * @return array{
     *   content_clone_ai_provider: string,
     *   ai_text_provider: string,
     *   ai_image_provider: string,
     *   dify_base_url: string,
     *   dify_connect_timeout: int,
     *   dify_timeout: int,
     *   dify_api_key_configured: bool,
     *   dify_text_api_key_configured: bool,
     *   dify_image_api_key_configured: bool,
     *   dify_content_clone_api_key_configured: bool,
     *   provider_keys: array<string, bool>,
     *   provider_models: array<string, array<string, string>>
     * }
     */
    public function formData(): array
    {
        return [
            'content_clone_ai_provider' => (string) config(self::CONTENT_CLONE_PROVIDER, 'openai'),
            'ai_text_provider' => (string) config(self::TEXT_PROVIDER, 'openai'),
            'ai_image_provider' => (string) config(self::IMAGE_PROVIDER, 'openai'),
            'dify_base_url' => (string) config(self::DIFY_BASE_URL, 'https://api.dify.ai/v1'),
            'dify_connect_timeout' => (int) config(self::DIFY_CONNECT_TIMEOUT, 10),
            'dify_timeout' => (int) config(self::DIFY_TIMEOUT, 120),
            'dify_api_key_configured' => filled(config(self::DIFY_API_KEY)),
            'dify_text_api_key_configured' => filled(config(self::DIFY_TEXT_API_KEY)),
            'dify_image_api_key_configured' => filled(config(self::DIFY_IMAGE_API_KEY)),
            'dify_content_clone_api_key_configured' => filled(config(self::DIFY_CONTENT_CLONE_API_KEY)),
            'provider_keys' => collect(self::PROVIDER_KEY_PATHS)
                ->mapWithKeys(fn (string $path, string $provider): array => [$provider => filled(config($path))])
                ->all(),
            'provider_models' => collect(self::PROVIDER_MODEL_PATHS)
                ->mapWithKeys(fn (array $paths, string $provider): array => [
                    $provider => collect($paths)
                        ->mapWithKeys(fn (string $path, string $capability): array => [$capability => (string) config($path, '')])
                        ->all(),
                ])
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(array $data): void
    {
        $this->set(self::CONTENT_CLONE_PROVIDER, (string) $data['content_clone_ai_provider']);
        $this->set(self::TEXT_PROVIDER, (string) $data['ai_text_provider']);
        $this->set(self::IMAGE_PROVIDER, (string) $data['ai_image_provider']);
        $this->set(self::DIFY_BASE_URL, (string) $data['dify_base_url']);
        $this->set(self::DIFY_CONNECT_TIMEOUT, (string) $data['dify_connect_timeout']);
        $this->set(self::DIFY_TIMEOUT, (string) $data['dify_timeout']);

        if (filled(data_get($data, 'dify_api_key'))) {
            $this->set(self::DIFY_API_KEY, (string) data_get($data, 'dify_api_key'));
        }

        if (filled(data_get($data, 'dify_text_api_key'))) {
            $this->set(self::DIFY_TEXT_API_KEY, (string) data_get($data, 'dify_text_api_key'));
        }

        if (filled(data_get($data, 'dify_image_api_key'))) {
            $this->set(self::DIFY_IMAGE_API_KEY, (string) data_get($data, 'dify_image_api_key'));
        }

        if (filled(data_get($data, 'dify_content_clone_api_key'))) {
            $this->set(self::DIFY_CONTENT_CLONE_API_KEY, (string) data_get($data, 'dify_content_clone_api_key'));
        }

        foreach (self::PROVIDER_KEY_PATHS as $provider => $path) {
            $key = "{$provider}_api_key";

            if (filled(data_get($data, $key))) {
                $this->set($path, (string) data_get($data, $key));
            }
        }

        foreach (self::PROVIDER_MODEL_PATHS as $provider => $paths) {
            foreach ($paths as $capability => $path) {
                $key = "{$provider}_{$capability}_model";

                if (array_key_exists($key, $data)) {
                    $this->setOrForget($path, data_get($data, $key));
                }
            }
        }

        $this->apply();
    }

    /**
     * @return array<string, mixed>
     */
    private function allSettings(): array
    {
        try {
            if (! Schema::hasTable('app_settings')) {
                return [];
            }

            return AppSetting::query()
                ->get()
                ->mapWithKeys(fn (AppSetting $setting): array => [$setting->key => $this->castValue($setting->key, $setting->value)])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function set(string $key, string $value): void
    {
        AppSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
    }

    private function setOrForget(string $key, mixed $value): void
    {
        if (blank($value)) {
            AppSetting::query()->where('key', $key)->delete();

            return;
        }

        $this->set($key, (string) $value);
    }

    private function castValue(string $key, mixed $value): mixed
    {
        if (in_array($key, [self::DIFY_CONNECT_TIMEOUT, self::DIFY_TIMEOUT], true)) {
            return (int) $value;
        }

        return $value;
    }
}
