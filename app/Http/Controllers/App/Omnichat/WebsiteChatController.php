<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Omnichat;

use App\Enums\Omnichat\ChannelProvider;
use App\Enums\Omnichat\ChannelStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\Omnichat\StoreWebsiteChatChannelRequest;
use App\Http\Requests\App\Omnichat\UpdateWebsiteChatChannelRequest;
use App\Models\OmnichatChannel;
use App\Support\Omnichat\WebsiteChatService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WebsiteChatController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = $request->user()->currentWorkspace;
        $this->authorize('manageAccounts', $workspace);

        $channels = OmnichatChannel::query()
            ->where('workspace_id', $workspace->id)
            ->where('provider', ChannelProvider::Website)
            ->latest()
            ->get()
            ->map(fn (OmnichatChannel $channel): array => $this->channelData($channel));

        return Inertia::render('omnichat/WebsiteChat', ['channels' => $channels]);
    }

    public function store(StoreWebsiteChatChannelRequest $request, WebsiteChatService $websiteChat): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $websiteChat->createChannel($workspace, $request->validated());

        return back()->with('success', 'Đã tạo kênh Website Live Chat.');
    }

    public function update(
        UpdateWebsiteChatChannelRequest $request,
        OmnichatChannel $channel,
        WebsiteChatService $websiteChat,
    ): RedirectResponse {
        $this->authorizeChannel($request, $channel);
        $websiteChat->updateChannel($channel, $request->validated());

        return back()->with('success', 'Đã cập nhật kênh Website Live Chat.');
    }

    public function rotate(Request $request, OmnichatChannel $channel, WebsiteChatService $websiteChat): RedirectResponse
    {
        $this->authorizeChannel($request, $channel);
        $websiteChat->rotatePublicKey($channel);

        return back()->with('success', 'Đã xoay khóa công khai. Widget dùng khóa cũ sẽ ngừng kết nối.');
    }

    public function destroy(Request $request, OmnichatChannel $channel): RedirectResponse
    {
        $this->authorizeChannel($request, $channel);
        $channel->update(['status' => ChannelStatus::Disconnected, 'disconnected_at' => now()]);

        return back()->with('success', 'Đã tắt kênh Website Live Chat.');
    }

    private function authorizeChannel(Request $request, OmnichatChannel $channel): void
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless(
            $channel->workspace_id === $workspace->id && $channel->provider === ChannelProvider::Website,
            404,
        );
        $this->authorize('manageAccounts', $workspace);
    }

    private function channelData(OmnichatChannel $channel): array
    {
        return [
            'id' => $channel->id,
            'name' => $channel->name,
            'public_key' => $channel->access_token,
            'status' => $channel->status->value,
            'settings' => $channel->settings ?? [],
            'created_at' => $channel->created_at->toIso8601String(),
        ];
    }
}
