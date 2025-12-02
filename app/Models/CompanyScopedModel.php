<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

abstract class CompanyScopedModel extends Model
{
    use BelongsToCompany;
}
