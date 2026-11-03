<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Program extends Model
{
    protected $fillable = ['code', 'name'];

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }
}
