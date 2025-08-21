<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThemeSetting extends Model
{
  protected $fillable = ['tenant_id', 'template_id', 'key', 'value'];

  protected $casts = [
    'value' => 'array',
  ];
}
