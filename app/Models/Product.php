<?php

namespace App\Models;

use App\Enums\ProductType;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'sku',
    'name',
    'type',
    'unit',
    'price',
    'cost',
    'is_active',
    'is_stock_tracked',
    'stock_quantity',
    'minimum_stock',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'price' => 'integer',
            'cost' => 'integer',
            'is_active' => 'boolean',
            'is_stock_tracked' => 'boolean',
            'stock_quantity' => 'integer',
            'minimum_stock' => 'integer',
        ];
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function servicePackageItems(): HasMany
    {
        return $this->hasMany(ServicePackageItem::class);
    }
}
