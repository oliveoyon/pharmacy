<?php

namespace App\Models;

use App\Models\Concerns\HasOrganizationScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;
    use HasOrganizationScope;

    protected $fillable = [
        'organization_id',
        'name',
        'sku',
        'generic_name',
        'strength',
        'dosage_form',
        'manufacturer_name',
        'purchase_unit_id',
        'stock_unit_id',
        'purchase_to_stock_factor',
        'purchase_price',
        'mrp',
        'selling_price',
        'tax_percent',
        'reorder_level',
        'is_controlled_drug',
        'requires_cold_chain',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'purchase_to_stock_factor' => 'decimal:6',
            'purchase_price' => 'decimal:4',
            'mrp' => 'decimal:4',
            'selling_price' => 'decimal:4',
            'tax_percent' => 'decimal:2',
            'reorder_level' => 'decimal:3',
            'is_controlled_drug' => 'boolean',
            'requires_cold_chain' => 'boolean',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function purchaseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'purchase_unit_id');
    }

    public function stockUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'stock_unit_id');
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(ProductBarcode::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }
}

