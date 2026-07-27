<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSettings extends Model
{
    protected $table = 'app_settings';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'app_name',
        'logo_path',
        'favicon_path',
    ];
}
