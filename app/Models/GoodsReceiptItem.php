<?php

namespace App\Models;

use App\Models\Concerns\HasOrganizationScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptItem extends Model
{
    use HasFactory;
    use HasOrganizationScope;

    protected $fillable = [
        'organization_id',
        'goods_receipt_id',
        'purchase_order_item_id',
        'product_id',
        'batch_id',
        'batch_no',
        'expiry_date',
        'received_qty',
        'unit_cost',
        'tax_percent',
        'discount_amount',
        'line_total',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'received_qty' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'tax_percent' => 'decimal:2',
            'discount_amount' => 'decimal:4',
            'line_total' => 'decimal:4',
            'metadata' => 'array',
        ];
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }
}

