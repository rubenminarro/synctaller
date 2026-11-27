<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceptionPhoto extends Model
{
    protected $fillable = [
        'reception_id',
        'path',
        'original_name',
        'description',
    ];

    /** URL de la API que sirve el archivo (P8, historial 2.106): exige el token, no sirve para un `<img src>` directo. */
    public function fileUrl(): ?string
    {
        return $this->path ? route('receptions.photos.file', [$this->reception_id, $this->id]) : null;
    }

    public function reception(): BelongsTo
    {
        return $this->belongsTo(Reception::class);
    }
}
