<?php

declare(strict_types=1);

namespace App\Http\Requests\App\ContentClone;

use App\Enums\Workspace\ImageStyle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContentCloneCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('createPost', $this->user()->currentWorkspace) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $workspaceId = $this->user()?->currentWorkspace?->id;

        return [
            'source_post_id' => [
                'required_without:manual_source_content',
                'nullable',
                Rule::when(fn ($input) => ! empty($input->source_post_id), ['uuid', Rule::exists('posts', 'id')->where(fn ($query) => $query->where('workspace_id', $workspaceId))]),
            ],
            'manual_source_content' => [
                'required_without:source_post_id',
                'nullable',
                'string',
            ],
            'manual_source_media' => ['nullable', 'array', 'max:10'],
            'manual_source_media.*.id' => ['nullable', 'string', 'max:255'],
            'manual_source_media.*.url' => ['required_with:manual_source_media', 'string', 'max:2048'],
            'manual_source_media.*.path' => ['nullable', 'string', 'max:2048'],
            'manual_source_media.*.type' => ['nullable', 'string', 'max:50'],
            'manual_source_media.*.mime_type' => ['nullable', 'string', 'max:100'],
            'manual_source_media.*.original_filename' => ['nullable', 'string', 'max:255'],
            'target_social_account_ids' => ['required', 'array', 'min:1'],
            'target_social_account_ids.*' => [
                'required',
                'uuid',
                'distinct',
                Rule::exists('social_accounts', 'id')->where(fn ($query) => $query->where('workspace_id', $workspaceId)->where('is_active', true)),
            ],
            'content_workflow_id' => [
                Rule::requiredIf(fn (): bool => $this->boolean('require_approval')),
                'nullable',
                'uuid',
                Rule::exists('content_workflows', 'id')->where(fn ($query) => $query->where('workspace_id', $workspaceId)->where('is_active', true)),
            ],
            'initial_content' => ['nullable', 'string', 'max:15000'],
            'initial_media' => ['nullable', 'array', 'max:10'],
            'initial_media.*.id' => ['nullable', 'string', 'max:255'],
            'initial_media.*.url' => ['required_with:initial_media', 'string', 'max:2048'],
            'initial_media.*.path' => ['nullable', 'string', 'max:2048'],
            'initial_media.*.type' => ['nullable', 'string', 'max:50'],
            'initial_media.*.mime_type' => ['nullable', 'string', 'max:100'],
            'initial_media.*.original_filename' => ['nullable', 'string', 'max:255'],
            'initial_media.*.source' => ['nullable', 'string', 'max:50'],
            'initial_media.*.source_meta' => ['nullable', 'array'],
            'initial_media.*.meta' => ['nullable', 'array'],
            'theme' => ['nullable', 'string', 'max:50000'],
            'prompt' => ['nullable', 'string', 'max:50000'],
            'image_prompt' => ['nullable', 'string', 'max:50000'],
            'total_posts' => ['required', 'integer', 'min:1', 'max:90'],
            'interval_days' => ['required', 'integer', 'min:1', 'max:30'],
            'start_at' => ['required', 'date', 'after:now'],
            'require_approval' => ['sometimes', 'boolean'],
            'ai_image_count' => ['nullable', 'integer', 'min:0', 'max:4'],
            'ai_image_style' => ['nullable', 'string', Rule::in(ImageStyle::values())],
            'ai_image_resolution' => ['nullable', 'string', 'max:50'],
            'ai_image_aspect_ratio' => ['nullable', 'string', 'max:50'],
            'ai_logo_path' => ['nullable', 'string', 'max:2048'],
            'diff_content_per_page' => ['sometimes', 'boolean'],
            'ai_content_mode' => ['nullable', 'string', Rule::in(['text_image', 'video_ai'])],
            'video_scenes' => ['nullable', 'array', 'max:20'],
            'video_scenes.*.duration' => ['required_with:video_scenes', 'integer', 'min:1', 'max:60'],
            'video_scenes.*.context_prompt' => ['required_with:video_scenes', 'string', 'max:50000'],
            'video_scenes.*.action_prompt' => ['nullable', 'string', 'max:50000'],
            'video_scenes.*.start_image' => ['nullable', 'string', 'max:2048'],
            'video_scenes.*.end_image' => ['nullable', 'string', 'max:2048'],
            'video_scenes.*.video_url' => ['nullable', 'string', 'max:2048'],
            'video_hook' => ['nullable', 'string', 'max:5000'],
            'video_target_duration' => ['nullable', 'integer', 'min:5', 'max:180'],
            'video_bgm_track' => ['nullable', 'string', 'max:255'],
            'video_bgm_url' => ['nullable', 'string', 'max:2048'],
            'video_bgm_volume' => ['nullable', 'integer', 'min:0', 'max:100'],
            'video_voiceover_voice' => ['nullable', 'string', 'max:100'],
            'video_auto_subtitles' => ['sometimes', 'boolean'],
            'video_resolution' => ['nullable', 'string', 'max:50'],
            'video_aspect_ratio' => ['nullable', 'string', 'max:50'],
        ];
    }
}
