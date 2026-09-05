<?php

declare(strict_types=1);

namespace App\Actions\Tag;

use App\Models\Tag;
use App\Models\Workspace;
use Illuminate\Support\Facades\Auth;

class CreateTag
{
    /** @param array{name: string, color: string} $data */
    public static function execute(Workspace $workspace, array $data): Tag
    {
        return $workspace->tags()->create([
            'name' => $data['name'],
            'slug' => str($data['name'])->slug()->toString(),
            'color' => $data['color'],
            'created_by' => Auth::id(),
        ]);
    }
}
