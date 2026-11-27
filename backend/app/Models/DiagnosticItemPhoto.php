<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiagnosticItemPhoto extends Model
{
    protected $fillable = [
        'diagnostic_item_id',
        'path',
        'original_name',
        'description',
    ];

    /** URL de la API que sirve el archivo (P8, historial 2.106): exige el token, no sirve para un `<img src>` directo. */
    public function fileUrl(): ?string
    {
        return $this->path ? route('diagnostic-items.photos.file', [$this->diagnostic_item_id, $this->id]) : null;
    }

    public function diagnosticItem(): BelongsTo
    {
        return $this->belongsTo(DiagnosticItem::class);
    }
}
