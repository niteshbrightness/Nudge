<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'label', 'offset', 'offset_minutes'])]
class Timezone extends Model
{
    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }
}
