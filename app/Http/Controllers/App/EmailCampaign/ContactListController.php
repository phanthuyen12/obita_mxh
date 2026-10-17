<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\EmailCampaign;

use App\Enums\EmailCampaign\ImportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\EmailCampaign\StoreEmailContactListRequest;
use App\Jobs\EmailCampaign\ImportEmailContactsJob;
use App\Models\EmailContactList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ContactListController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = $request->user()->currentWorkspace;

        $lists = EmailContactList::query()
            ->where('workspace_id', $workspace->id)
            ->withCount('contacts')
            ->latest()
            ->paginate((int) config('app.pagination.default'));

        return Inertia::render('email-campaigns/Lists', [
            'lists' => Inertia::scroll(fn () => $lists),
        ]);
    }

    public function store(StoreEmailContactListRequest $request): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $file = $request->file('file');

        $path = $file->storeAs(
            'email-imports',
            Str::uuid().'.'.$file->getClientOriginalExtension(),
            'local',
        );

        $list = EmailContactList::query()->create([
            'workspace_id' => $workspace->id,
            'created_by_user_id' => $request->user()->id,
            'name' => $request->string('name')->trim()->toString(),
            'source_filename' => $file->getClientOriginalName(),
            'source_path' => $path,
            'status' => ImportStatus::Pending,
        ]);

        ImportEmailContactsJob::dispatch($list->id);

        session()->flash('flash.banner', __('Đang nhập danh sách, hệ thống sẽ cập nhật khi xong.'));
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }

    public function show(Request $request, EmailContactList $list): Response
    {
        $this->authorizeList($request, $list);

        $contacts = $list->contacts()
            ->when($request->string('search')->trim()->toString(), fn ($query, string $search) => $query
                ->where('email', 'like', "%{$search}%"))
            ->latest()
            ->paginate((int) config('app.pagination.default'));

        return Inertia::render('email-campaigns/ListDetail', [
            'list' => $list,
            'contacts' => Inertia::scroll(fn () => $contacts),
            'filters' => ['search' => $request->input('search', '')],
        ]);
    }

    public function destroy(Request $request, EmailContactList $list): RedirectResponse
    {
        $this->authorizeList($request, $list);

        if ($list->source_path !== null) {
            Storage::disk('local')->delete($list->source_path);
        }

        $list->delete();

        session()->flash('flash.banner', __('Đã xoá danh sách.'));
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }

    private function authorizeList(Request $request, EmailContactList $list): void
    {
        $workspace = $request->user()->currentWorkspace;

        abort_unless($workspace !== null && $list->workspace_id === $workspace->id, 403);
    }
}
