<?php

namespace App\Models;

use App\Models\Concerns\HasOrganizationScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    use HasFactory;
    use HasOrganizationScope;

    protected $fillable = [
        'organization_id',
        'purchase_order_id',
        'product_id',
        'ordered_qty',
        'received_qty',
        'unit_cost',
        'tax_percent',
        'discount_amount',
        'line_total',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'ordered_qty' => 'decimal:3',
            'received_qty' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'tax_percent' => 'decimal:2',
            'discount_amount' => 'decimal:4',
            'line_total' => 'decimal:4',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

