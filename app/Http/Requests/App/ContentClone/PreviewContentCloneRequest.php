<?php

declare(strict_types=1);

namespace App\Http\Requests\App\ContentClone;

use App\Enums\Workspace\ImageStyle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewContentCloneRequest extends FormRequest
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
            'theme' => ['nullable', 'string', 'max:50000'],
            'prompt' => ['nullable', 'string', 'max:50000'],
            'image_prompt' => ['nullable', 'string', 'max:50000'],
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
            'video_scenes.*.voiceover_text' => ['nullable', 'string', 'max:50000'],
            'video_scenes.*.start_image' => ['nullable', 'string', 'max:2048'],
            'video_scenes.*.end_image' => ['nullable', 'string', 'max:2048'],
            'video_scenes.*.transition' => ['nullable', 'string', 'max:50'],
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
            'character_enabled' => ['sometimes', 'boolean'],
            'character_id' => ['nullable', 'string', 'max:100'],
            'character_name' => ['nullable', 'string', 'max:255'],
            'character_dna' => ['nullable', 'string', 'max:5000'],
            'character_avatar' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
