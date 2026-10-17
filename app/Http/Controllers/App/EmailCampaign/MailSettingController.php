<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\EmailCampaign;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\EmailCampaign\UpdateWorkspaceMailSettingRequest;
use App\Models\WorkspaceMailSetting;
use App\Services\Mail\WorkspaceMailerResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class MailSettingController extends Controller
{
    public function __construct(private readonly WorkspaceMailerResolver $mailerResolver) {}

    public function edit(Request $request): Response
    {
        $workspace = $request->user()->currentWorkspace;

        $settings = WorkspaceMailSetting::query()
            ->where('workspace_id', $workspace->id)
            ->first();

        return Inertia::render('email-campaigns/MailSettings', [
            'settings' => $settings === null ? null : [
                'host' => $settings->host,
                'port' => $settings->port,
                'encryption' => $settings->encryption,
                'username' => $settings->username,
                'from_address' => $settings->from_address,
                'from_name' => $settings->from_name,
                'reply_to' => $settings->reply_to,
                'daily_limit' => $settings->daily_limit,
                'verified_at' => $settings->verified_at?->toIso8601String(),
                'last_error' => $settings->last_error,
                'has_password' => $settings->password !== null && $settings->password !== '',
            ],
        ]);
    }

    public function update(UpdateWorkspaceMailSettingRequest $request): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $validated = $request->validated();

        $settings = WorkspaceMailSetting::query()->firstOrNew(['workspace_id' => $workspace->id]);

        $settings->fill([
            'workspace_id' => $workspace->id,
            'host' => data_get($validated, 'host'),
            'port' => (int) data_get($validated, 'port'),
            'encryption' => data_get($validated, 'encryption'),
            'username' => data_get($validated, 'username'),
            'from_address' => data_get($validated, 'from_address'),
            'from_name' => data_get($validated, 'from_name'),
            'reply_to' => data_get($validated, 'reply_to'),
            'daily_limit' => data_get($validated, 'daily_limit'),
        ]);

        // An empty password field means "keep the stored one", so an operator
        // can edit the host without retyping the secret.
        $password = data_get($validated, 'password');

        if (is_string($password) && $password !== '') {
            $settings->password = $password;
            $settings->verified_at = null;
        }

        $settings->save();

        session()->flash('flash.banner', __('Đã lưu cấu hình email.'));
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }

    public function test(Request $request): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $settings = WorkspaceMailSetting::query()->where('workspace_id', $workspace->id)->first();

        if ($settings === null) {
            return back()->withErrors(['host' => __('Chưa có cấu hình để gửi thử.')]);
        }

        try {
            $mailer = $this->mailerResolver->resolve($workspace);

            $mailer->raw(
                __('Đây là email kiểm tra cấu hình SMTP của workspace :name.', ['name' => $workspace->name]),
                fn ($message) => $message
                    ->to($request->user()->email)
                    ->subject(__('Kiểm tra cấu hình email')),
            );

            $settings->update(['verified_at' => now(), 'last_error' => null]);

            session()->flash('flash.banner', __('Đã gửi email thử tới :email.', ['email' => $request->user()->email]));
            session()->flash('flash.bannerStyle', 'success');

            return back();
        } catch (Throwable $e) {
            $settings->update(['verified_at' => null, 'last_error' => mb_substr($e->getMessage(), 0, 1000)]);

            return back()->withErrors(['host' => $e->getMessage()]);
        }
    }
}
