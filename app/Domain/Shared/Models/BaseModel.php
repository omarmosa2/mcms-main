<?php

namespace App\Domain\Shared\Models;

use App\Domain\Shared\Traits\HasClinic;
use Illuminate\Database\Eloquent\Model;

abstract class BaseModel extends Model
{
    use HasClinic;

    protected $guarded = [];
}
