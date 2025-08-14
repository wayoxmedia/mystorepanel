<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Page extends Model
{
    protected $fillable = [
        'tenant_id',
        'slug',
        'title',
        'content',
        'meta_title',
        'meta_description',
    ];

    protected $casts = [
        'content' => 'array',
    ];

    /**
     * @return BelongsTo
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
