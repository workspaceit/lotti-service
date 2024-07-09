<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeviceType extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_type_name',
        'device_type'
    ];

    public function devices()
    {
        return $this->hasMany(Device::class);
    }
}
