<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An editorial point on the map, belonging to one city and one layer. */
class Place extends Model
{
    use HasFactory;

    protected $fillable = ['city_id', 'layer_id', 'name', 'description', 'address', 'lat', 'lng'];

    protected function casts(): array
    {
        return ['lat' => 'float', 'lng' => 'float'];
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function layer(): BelongsTo
    {
        return $this->belongsTo(Layer::class);
    }
}
