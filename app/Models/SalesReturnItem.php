<?php

namespace App\Models;

use App\Models\Concerns\HasOrganizationScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReturnItem extends Model
{
    use HasFactory;
    use HasOrganizationScope;

    protected $fillable = [
        'organization_id',
        'sales_return_id',
        'sales_invoice_item_id',
        'product_id',
        'batch_id',
        'return_qty',
        'unit_price',
        'line_total',
        'reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'return_qty' => 'decimal:3',
            'unit_price' => 'decimal:4',
            'line_total' => 'decimal:4',
            'metadata' => 'array',
        ];
    }

    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class);
    }

    public function salesInvoiceItem(): BelongsTo
    {
        return $this->belongsTo(SalesInvoiceItem::class);
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

