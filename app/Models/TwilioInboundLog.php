<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['from_number', 'body', 'action', 'clients_affected', 'raw_payload'])]
class TwilioInboundLog extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'raw_payload' => AsArrayObject::class,
        ];
    }
}
