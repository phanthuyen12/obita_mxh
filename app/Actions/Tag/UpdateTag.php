<?php

declare(strict_types=1);

namespace App\Actions\Tag;

use App\Models\Tag;

class UpdateTag
{
    /** @param array{name: string, color: string} $data */
    public static function execute(Tag $tag, array $data): Tag
    {
        $tag->update([
            'name' => $data['name'],
            'slug' => str($data['name'])->slug()->toString(),
            'color' => $data['color'],
        ]);

        return $tag;
    }
}
