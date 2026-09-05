<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Settings;

use App\Http\Controllers\App\Controller;
use App\Http\Requests\App\Settings\UpdateAiSettingsRequest;
use App\Http\Requests\App\Settings\UpdatePageAiCareRequest;
use App\Models\SocialAccount;
use App\Services\Ai\AiConfiguration;
use App\Services\Dify\DifyChatClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AiSettingsController extends Controller
{
    public function edit(Request $request, AiConfiguration $configuration): Response
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless(
            $request->user()->isAccountOwner() || ($workspace && $request->user()->can('manageTeam', $workspace)),
            SymfonyResponse::HTTP_FORBIDDEN
        );

        $defaultAiCare = [
            'enabled' => false,
            'provider' => 'dify',
            'bot_name' => 'AI Chăm sóc Khách hàng',
            'dify_api_key' => '',
            'dify_base_url' => 'https://kingai.tnicorporation.com/v1',
            'reply_mode' => 'all',
            'reply_delay_seconds' => 3,
            'operating_hours' => [
                'mode' => '24/7',
                'days' => [1, 2, 3, 4, 5, 6, 7],
                'start_time' => '08:00',
                'end_time' => '22:00',
                'timezone' => 'Asia/Ho_Chi_Minh',
            ],
            'off_hours_behavior' => 'ai_reply',
            'off_hours_message' => 'Dạ xin chào! Hiện tại đang ngoài giờ làm việc chính thức, chúng tôi sẽ phản hồi sớm nhất.',
            'auto_tag_leads' => true,
            'lead_keywords' => ['cà phê', 'kingcoffee', 'báo giá', 'tư vấn', 'mua hàng', 'sđt', 'đặt hàng', 'pha máy'],
        ];

        $pages = $workspace ? $workspace->socialAccounts()
            ->where('is_active', true)
            ->get()
            ->map(function (SocialAccount $account) use ($defaultAiCare) {
                $meta = $account->meta ?? [];
                $savedAiCare = is_array($meta['ai_care'] ?? null) ? $meta['ai_care'] : [];
                $aiCare = array_merge($defaultAiCare, $savedAiCare);

                return [
                    'id' => $account->id,
                    'display_name' => $account->display_name ?: $account->username ?: 'Page #'.substr($account->id, 0, 6),
                    'username' => $account->username,
                    'platform' => $account->platform->value,
                    'avatar_url' => $account->avatar_url,
                    'ai_care' => $aiCare,
                ];
            }) : [];

        return Inertia::render('settings/account/Ai', [
            'settings' => $configuration->formData(),
            'pages' => $pages,
            'options' => [
                'contentCloneProviders' => AiConfiguration::contentCloneProviders(),
                'textProviders' => AiConfiguration::textProviders(),
                'imageProviders' => AiConfiguration::imageProviders(),
                'secretProviders' => AiConfiguration::configurableSecretProviders(),
                'providerModelCapabilities' => AiConfiguration::providerModelCapabilities(),
            ],
        ]);
    }

    public function update(UpdateAiSettingsRequest $request, AiConfiguration $configuration): RedirectResponse
    {
        $configuration->update($request->validated());

        session()->flash('flash.banner', 'Đã lưu cấu hình AI hệ thống.');
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }

    public function testDify(Request $request, DifyChatClient $client): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless(
            $request->user()->isAccountOwner() || ($workspace && $request->user()->can('manageTeam', $workspace)),
            SymfonyResponse::HTTP_FORBIDDEN
        );

        $apiKey = $request->string('dify_api_key')->trim()->toString() ?: null;
        $baseUrl = $request->string('dify_base_url')->trim()->toString() ?: null;

        $result = $client->testConnection($apiKey, $baseUrl);

        return response()->json($result);
    }

    public function updatePageAi(UpdatePageAiCareRequest $request, SocialAccount $account): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;
        if (! $workspace || $account->workspace_id !== $workspace->id) {
            abort(SymfonyResponse::HTTP_FORBIDDEN);
        }

        $validated = $request->validated();
        $meta = is_array($account->meta) ? $account->meta : [];
        $meta['ai_care'] = $validated;

        $account->forceFill(['meta' => $meta])->save();

        session()->flash('flash.banner', "Đã lưu cấu hình AI chăm sóc cho page {$account->display_name}.");
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }

    public function updateBatchPageAi(Request $request): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;
        if (! $workspace) {
            abort(SymfonyResponse::HTTP_FORBIDDEN);
        }

        abort_unless(
            $request->user()->isAccountOwner() || $request->user()->can('manageTeam', $workspace),
            SymfonyResponse::HTTP_FORBIDDEN
        );

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'provider' => ['nullable', 'string', 'max:50'],
            'persona' => ['nullable', 'string', 'max:10000'],
            'model' => ['nullable', 'string', 'max:100'],
            'dify_api_key' => ['nullable', 'string', 'max:500'],
            'dify_base_url' => ['nullable', 'string', 'max:255'],
            'suggested_questions' => ['nullable', 'array'],
            'reply_mode' => ['nullable', 'string'],
            'reply_delay_seconds' => ['nullable', 'integer', 'min:0', 'max:60'],
            'operating_hours' => ['nullable', 'array'],
            'off_hours_behavior' => ['nullable', 'string'],
            'off_hours_message' => ['nullable', 'string', 'max:1000'],
            'auto_tag_leads' => ['nullable', 'boolean'],
            'lead_keywords' => ['nullable', 'array'],
            'knowledge_base' => ['nullable', 'string', 'max:10000'],
        ]);

        $accounts = $workspace->socialAccounts()->where('is_active', true)->get();
        foreach ($accounts as $account) {
            $meta = $account->meta ?? [];
            $currentCare = $meta['ai_care'] ?? [];
            $meta['ai_care'] = array_merge($currentCare, $validated, [
                'bot_name' => $currentCare['bot_name'] ?? ('AI Chăm sóc '.($account->display_name ?: $account->username)),
            ]);
            $account->meta = $meta;
            $account->save();
        }

        session()->flash('flash.banner', "Đã cập nhật cấu hình AI chăm sóc cho toàn bộ {$accounts->count()} trang.");
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }
}
