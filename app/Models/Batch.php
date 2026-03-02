<?php

namespace App\Models;

use App\Models\Concerns\HasOrganizationScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batch extends Model
{
    use HasFactory;
    use HasOrganizationScope;

    protected $fillable = [
        'organization_id',
        'product_id',
        'batch_no',
        'expiry_date',
        'manufactured_at',
        'received_at',
        'cost_price',
        'mrp',
        'selling_price',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'manufactured_at' => 'date',
            'received_at' => 'date',
            'cost_price' => 'decimal:4',
            'mrp' => 'decimal:4',
            'selling_price' => 'decimal:4',
            'metadata' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stockBalances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }
}

