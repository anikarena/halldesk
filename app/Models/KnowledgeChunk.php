<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One searchable passage for Ask AI: a rulebook section or a notice.
 */
#[Fillable(['key', 'title', 'body', 'url', 'hash', 'embedding'])]
class KnowledgeChunk extends Model
{
    protected function casts(): array
    {
        return [
            'embedding' => 'array',
        ];
    }
}
