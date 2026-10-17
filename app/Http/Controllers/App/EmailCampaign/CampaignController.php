<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\EmailCampaign;

use App\Enums\EmailCampaign\RecipientStatus;
use App\Enums\EmailCampaign\Status;
use App\Exceptions\WorkspaceMailNotConfiguredException;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\EmailCampaign\IndexEmailCampaignRequest;
use App\Http\Requests\App\EmailCampaign\SendTestEmailCampaignRequest;
use App\Http\Requests\App\EmailCampaign\ShowEmailCampaignRequest;
use App\Http\Requests\App\EmailCampaign\StoreEmailCampaignRequest;
use App\Http\Requests\App\EmailCampaign\UpdateEmailCampaignRequest;
use App\Mail\EmailCampaignMessage;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Models\EmailContactList;
use App\Services\EmailCampaign\EmailCampaignDispatcher;
use App\Services\Mail\WorkspaceMailerResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CampaignController extends Controller
{
    public function __construct(
        private readonly EmailCampaignDispatcher $dispatcher,
        private readonly WorkspaceMailerResolver $mailerResolver,
    ) {}

    public function index(IndexEmailCampaignRequest $request): Response
    {
        $workspace = $request->user()->currentWorkspace;

        $campaigns = EmailCampaign::query()
            ->where('workspace_id', $workspace->id)
            ->with('contactList:id,name')
            ->when($request->string('search')->trim()->toString(), fn ($query, string $search) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('subject', 'like', "%{$search}%"))
            ->when($request->string('status')->trim()->toString(), fn ($query, string $status) => $query
                ->where('status', $status))
            ->latest()
            ->paginate((int) config('app.pagination.default'));

        return Inertia::render('email-campaigns/Index', [
            'campaigns' => Inertia::scroll(fn () => $campaigns),
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', ''),
            ],
            'contactLists' => EmailContactList::query()
                ->where('workspace_id', $workspace->id)
                ->withCount('contacts')
                ->latest()
                ->get(['id', 'name']),
            'mailConfigured' => $this->mailConfigured($request),
            'statuses' => collect(Status::cases())
                ->map(fn (Status $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ])->values(),
        ]);
    }

    public function store(StoreEmailCampaignRequest $request): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $validated = $request->validated();

        $campaign = EmailCampaign::query()->create([
            'workspace_id' => $workspace->id,
            'created_by_user_id' => $request->user()->id,
            'email_contact_list_id' => data_get($validated, 'email_contact_list_id'),
            'name' => data_get($validated, 'name'),
            'subject' => data_get($validated, 'subject'),
            'preview_text' => data_get($validated, 'preview_text'),
            'body_html' => data_get($validated, 'body_html'),
            'from_name' => data_get($validated, 'from_name'),
            'reply_to' => data_get($validated, 'reply_to'),
            'delay_seconds' => (int) data_get($validated, 'delay_seconds', 2),
            'scheduled_at' => data_get($validated, 'scheduled_at'),
            'status' => data_get($validated, 'scheduled_at') ? Status::Scheduled : Status::Draft,
        ]);

        session()->flash('flash.banner', __('Đã tạo chiến dịch.'));
        session()->flash('flash.bannerStyle', 'success');

        return redirect()->route('app.email-campaigns.show', $campaign);
    }

    public function show(ShowEmailCampaignRequest $request, EmailCampaign $campaign): Response
    {
        $this->authorizeCampaign($request, $campaign);

        $recipients = $campaign->recipients()
            ->when($request->string('status')->trim()->toString(), fn ($query, string $status) => $query
                ->where('status', $status))
            ->when($request->string('search')->trim()->toString(), fn ($query, string $search) => $query
                ->where('email', 'like', "%{$search}%"))
            ->latest()
            ->paginate((int) config('app.pagination.default'));

        return Inertia::render('email-campaigns/Show', [
            'campaign' => $campaign->load('contactList:id,name'),
            'recipients' => Inertia::scroll(fn () => $recipients),
            'stats' => $campaign->stats ?? $campaign->recalculateStats(),
            'filters' => [
                'status' => $request->input('status', ''),
                'search' => $request->input('search', ''),
            ],
            'recipientStatuses' => collect(RecipientStatus::cases())
                ->map(fn (RecipientStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ])->values(),
            'mailConfigured' => $this->mailConfigured($request),
        ]);
    }

    public function update(UpdateEmailCampaignRequest $request, EmailCampaign $campaign): RedirectResponse
    {
        $this->authorizeCampaign($request, $campaign);
        abort_if($campaign->status === Status::Sending, 422, __('Chiến dịch đang gửi, không sửa được.'));

        $validated = $request->validated();

        $campaign->update([
            'email_contact_list_id' => data_get($validated, 'email_contact_list_id'),
            'name' => data_get($validated, 'name'),
            'subject' => data_get($validated, 'subject'),
            'preview_text' => data_get($validated, 'preview_text'),
            'body_html' => data_get($validated, 'body_html'),
            'from_name' => data_get($validated, 'from_name'),
            'reply_to' => data_get($validated, 'reply_to'),
            'delay_seconds' => (int) data_get($validated, 'delay_seconds', $campaign->delay_seconds),
            'scheduled_at' => data_get($validated, 'scheduled_at'),
            'status' => data_get($validated, 'scheduled_at') ? Status::Scheduled : $campaign->status,
        ]);

        session()->flash('flash.banner', __('Đã lưu chiến dịch.'));
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }

    public function send(Request $request, EmailCampaign $campaign): RedirectResponse
    {
        $this->authorizeCampaign($request, $campaign);

        if (! $campaign->status->isDispatchable()) {
            return back()->withErrors(['status' => __('Chiến dịch này không ở trạng thái gửi được.')]);
        }

        try {
            $this->mailerResolver->settingsFor($campaign->workspace);
        } catch (WorkspaceMailNotConfiguredException) {
            return back()->withErrors([
                'mail' => __('Workspace chưa cấu hình SMTP. Vào Cài đặt email để thiết lập trước khi gửi.'),
            ]);
        }

        $counts = $this->dispatcher->materializeRecipients($campaign);
        $queued = $this->dispatcher->dispatch($campaign->fresh());

        session()->flash('flash.banner', __('Đã đưa :queued email vào hàng đợi, bỏ qua :skipped địa chỉ.', [
            'queued' => $queued,
            'skipped' => data_get($counts, 'skipped', 0),
        ]));
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }

    public function sendTest(SendTestEmailCampaignRequest $request): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        try {
            $mailer = $this->mailerResolver->resolve($workspace);
            $validated = $request->validated();
            $campaign = new EmailCampaign($validated);
            $campaign->setRelation('workspace', $workspace);

            $recipient = new EmailCampaignRecipient([
                'email' => $request->user()->email,
                'name' => $request->user()->name,
                'unsubscribe_token' => Str::random(48),
            ]);

            $mailer->to($request->user()->email, $request->user()->name)->send(
                new EmailCampaignMessage(
                    $campaign,
                    $recipient,
                    route('email.unsubscribe', $recipient->unsubscribe_token),
                ),
            );
        } catch (WorkspaceMailNotConfiguredException) {
            return back()->withErrors(['mail' => __('Workspace chưa cấu hình SMTP.')]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['mail' => __('Không gửi được email thử. Vui lòng kiểm tra cấu hình SMTP.')]);
        }

        session()->flash('flash.banner', __('Đã gửi email thử tới :email.', ['email' => $request->user()->email]));
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }

    public function pause(Request $request, EmailCampaign $campaign): RedirectResponse
    {
        $this->authorizeCampaign($request, $campaign);
        abort_unless($campaign->status === Status::Sending, 422);

        $campaign->update(['status' => Status::Paused]);

        session()->flash('flash.banner', __('Đã tạm dừng chiến dịch.'));
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }

    public function cancel(Request $request, EmailCampaign $campaign): RedirectResponse
    {
        $this->authorizeCampaign($request, $campaign);
        abort_if(
            in_array($campaign->status, [Status::Completed, Status::Cancelled], true),
            422,
            __('Chiến dịch đã kết thúc.'),
        );

        $campaign->update(['status' => Status::Cancelled, 'completed_at' => now()]);

        session()->flash('flash.banner', __('Đã huỷ chiến dịch.'));
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }

    public function retryFailed(Request $request, EmailCampaign $campaign): RedirectResponse
    {
        $this->authorizeCampaign($request, $campaign);

        $queued = $this->dispatcher->retryFailed($campaign);

        session()->flash('flash.banner', __('Đã gửi lại :count email lỗi.', ['count' => $queued]));
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }

    public function destroy(Request $request, EmailCampaign $campaign): RedirectResponse
    {
        $this->authorizeCampaign($request, $campaign);
        abort_if($campaign->status === Status::Sending, 422, __('Chiến dịch đang gửi, không xoá được.'));

        $campaign->delete();

        session()->flash('flash.banner', __('Đã xoá chiến dịch.'));
        session()->flash('flash.bannerStyle', 'success');

        return redirect()->route('app.email-campaigns.index');
    }

    private function authorizeCampaign(Request $request, EmailCampaign $campaign): void
    {
        $workspace = $request->user()->currentWorkspace;

        abort_unless($workspace !== null && $campaign->workspace_id === $workspace->id, 403);
    }

    private function mailConfigured(Request $request): bool
    {
        try {
            $this->mailerResolver->settingsFor($request->user()->currentWorkspace);

            return true;
        } catch (WorkspaceMailNotConfiguredException) {
            return false;
        }
    }
}
