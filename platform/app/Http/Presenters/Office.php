<?php

namespace App\Http\Presenters;

use App\Models\Document;

class Office
{
    public static function document(Document $d): array
    {
        return [
            'id' => $d->uuid,
            'name' => $d->original_name,
            'label' => $d->label,
            'category' => $d->category,
            'categoryLabel' => Document::CATEGORIES[$d->category] ?? $d->category,
            'party' => $d->party,
            'partyLabel' => Document::PARTIES[$d->party] ?? $d->party,
            'size' => $d->size,
            'extension' => $d->extension,
            'scan' => $d->scan_status,
            'shared' => $d->shared_with_client,
            'uploadedAt' => $d->created_at->toIso8601String(),
            'url' => $d->isAvailable() ? route('files.show', $d) : null,
            'owner' => $d->documentable_type ? class_basename($d->documentable_type) : null,
        ];
    }

    public static function options(array $map): array
    {
        return collect($map)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all();
    }
}
