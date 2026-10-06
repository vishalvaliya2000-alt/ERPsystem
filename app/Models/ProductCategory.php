<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCategory extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'slug', 'description'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }
}
