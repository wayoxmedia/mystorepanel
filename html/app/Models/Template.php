<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    // Mass-assignable attributes
    protected $fillable = ['slug', 'name', 'is_active', 'version', 'description'];

    public function sites()
    {
        return $this->hasMany(Site::class);
    }
}
