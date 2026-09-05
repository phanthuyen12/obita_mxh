<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Actions\Tag\SyncTags;
use App\Enums\Folder\Permission as FolderPermission;
use App\Http\Requests\App\Asset\MoveAssetRequest;
use App\Http\Requests\App\Asset\StoreAssetFromUrlRequest;
use App\Http\Requests\App\Asset\StoreAssetRequest;
use App\Http\Requests\App\Asset\StoreChunkedAssetRequest;
use App\Http\Requests\App\Asset\UpdateAssetTagsRequest;
use App\Http\Resources\App\MediaResource;
use App\Models\Folder;
use App\Models\Media;
use App\Services\Brand\SafeHttpFetcher;
use App\Services\Media\ChunkedAssetReceiver;
use App\Services\UnsplashService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AssetController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('createPost', $workspace);

        return Inertia::render('assets/Index');
    }

    public function search(Request $request): AnonymousResourceCollection
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('createPost', $workspace);

        $term = trim((string) $request->input('search', ''));
        $type = $request->input('type');
        $from = $request->input('from');
        $to = $request->input('to');
        $folderId = $request->input('folder_id');
        $tag = trim((string) $request->input('tag', ''));
        $canManageAllFolders = $request->user()->can('manageTeam', $workspace);
        $accessibleFolderIds = collect();

        if (! $canManageAllFolders) {
            $accessibleFolderIds = Folder::query()
                ->forWorkspace($workspace)
                ->get()
                ->filter(fn (Folder $folder): bool => $folder->userHasPermission(
                    $request->user(),
                    FolderPermission::View,
                ))
                ->pluck('id');
        }

        if (is_string($folderId) && $folderId !== 'unfiled') {
            $folder = Folder::query()
                ->forWorkspace($workspace)
                ->findOrFail($folderId);

            $this->authorize('view', $folder);
        }

        $likeOperator = \DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $assets = $workspace->getMedia('assets')
            ->with('tags')
            ->when(! $canManageAllFolders, fn ($query) => $query->where(function ($accessible) use ($accessibleFolderIds): void {
                $accessible->whereNull('folder_id')
                    ->orWhereIn('folder_id', $accessibleFolderIds);
            }))
            ->when($term !== '', fn ($query) => $query->where('original_filename', $likeOperator, '%'.$term.'%'))
            ->when(in_array($type, ['image', 'video'], true), fn ($query) => $query->where('type', $type))
            ->when($from, fn ($query) => $query->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($to, fn ($query) => $query->where('created_at', '<=', Carbon::parse($to)->endOfDay()))
            ->when($folderId === 'unfiled', fn ($query) => $query->whereNull('folder_id'))
            ->when(is_string($folderId) && $folderId !== 'unfiled', fn ($query) => $query->where('folder_id', $folderId))
            ->when($tag !== '', fn ($query) => $query->whereHas('tags', fn ($tags) => $tags
                ->where('tags.workspace_id', $workspace->id)
                ->where('tags.name', $tag)))
            ->latest()
            ->paginate(config('app.pagination.default'));

        return MediaResource::collection($assets)->additional([
            'tag_options' => $workspace->tags()
                ->orderBy('name')
                ->pluck('name')
                ->values(),
        ]);
    }

    public function store(StoreAssetRequest $request): MediaResource
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('createPost', $workspace);

        $clientMeta = (array) $request->input('meta', []);

        $media = $workspace->addMedia($request->file('media'), 'assets', $clientMeta);

        return new MediaResource($media);
    }

    public function storeChunked(StoreChunkedAssetRequest $request, ChunkedAssetReceiver $receiver): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('createPost', $workspace);

        $folder = $request->validated('folder_id')
            ? Folder::query()->findOrFail($request->validated('folder_id'))
            : null;

        if ($folder !== null && ! $folder->userHasPermission($request->user(), FolderPermission::UploadMedia)) {
            abort(403);
        }

        $receipt = $receiver->receive(
            $workspace,
            $request->user(),
            $request->validated('file_name'),
            $request->getContent(),
            (int) $request->validated('range_start'),
            (int) $request->validated('range_end'),
            (int) $request->validated('total_size'),
            (string) $request->validated('upload_id'),
        );

        if ($receipt->done && $receipt->media !== null) {
            $receipt->media->update([
                'folder_id' => $folder?->id,
                'uploaded_by' => $request->user()->id,
            ]);
        }

        return $receipt->toResponse();
    }

    public function storeFromUrl(StoreAssetFromUrlRequest $request, UnsplashService $unsplash, SafeHttpFetcher $safeHttp): MediaResource
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('createPost', $workspace);

        $validated = $request->validated();

        if ($folderId = data_get($validated, 'folder_id')) {
            $folder = Folder::query()->findOrFail($folderId);
            abort_unless($folder->userHasPermission($request->user(), FolderPermission::UploadMedia), 403);
        }

        // Trigger Unsplash download tracking (required by API guidelines)
        if ($downloadLocation = data_get($validated, 'download_location')) {
            $unsplash->trackDownload($downloadLocation);
        }

        $url = data_get($validated, 'url');

        try {
            $response = $safeHttp->guardedRequest($url)->timeout(30)->get($url);
        } catch (RuntimeException) {
            abort(SymfonyResponse::HTTP_BAD_REQUEST, 'Failed to download image from URL');
        }

        if ($response->failed()) {
            abort(SymfonyResponse::HTTP_BAD_REQUEST, 'Failed to download image from URL');
        }

        $mimeType = $response->header('Content-Type', 'image/jpeg');
        $extension = match (true) {
            str_contains($mimeType, 'png') => 'png',
            str_contains($mimeType, 'gif') => 'gif',
            str_contains($mimeType, 'webp') => 'webp',
            default => 'jpg',
        };

        $filename = Str::uuid().'.'.$extension;
        $path = "medias/{$filename}";

        Storage::put($path, $response->body());

        $meta = [];
        $tempFile = tempnam(sys_get_temp_dir(), 'unsplash');
        file_put_contents($tempFile, $response->body());
        $imageInfo = @getimagesize($tempFile);
        if ($imageInfo) {
            $meta['width'] = $imageInfo[0];
            $meta['height'] = $imageInfo[1];
        }
        @unlink($tempFile);

        $media = $workspace->media()->create([
            'group_id' => Str::uuid()->toString(),
            'collection' => 'assets',
            'type' => 'image',
            'path' => $path,
            'original_filename' => data_get($validated, 'filename'),
            'mime_type' => $mimeType,
            'size' => strlen($response->body()),
            'order' => 0,
            'meta' => $meta,
            'folder_id' => data_get($validated, 'folder_id'),
            'uploaded_by' => $request->user()->id,
        ]);

        return new MediaResource($media);
    }

    public function destroy(Request $request, Media $media): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('createPost', $workspace);

        if ($media->mediable_type !== $workspace->getMorphClass() || $media->mediable_id !== $workspace->id) {
            abort(SymfonyResponse::HTTP_FORBIDDEN);
        }

        if ($media->folder_id !== null) {
            $folder = Folder::query()->findOrFail($media->folder_id);
            abort_unless($folder->userHasPermission($request->user(), FolderPermission::DeleteMedia), 403);
        }

        $media->delete();

        return back();
    }

    public function move(MoveAssetRequest $request, Media $media): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        abort_unless($media->mediable_type === $workspace->getMorphClass() && $media->mediable_id === $workspace->id, 404);

        $media->update(['folder_id' => $request->validated('folder_id')]);

        return response()->json(['data' => new MediaResource($media->fresh())]);
    }

    public function updateTags(UpdateAssetTagsRequest $request, Media $media): JsonResponse
    {
        $tags = collect($request->validated('tags'))
            ->map(fn (string $tag): string => trim($tag))
            ->filter()
            ->unique(fn (string $tag): string => mb_strtolower($tag))
            ->values()
            ->all();

        SyncTags::execute($request->user()->currentWorkspace, $media, $tags);

        return response()->json(['data' => new MediaResource($media->fresh()->load('tags'))]);
    }
}
