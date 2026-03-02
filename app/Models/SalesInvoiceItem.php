<?php

namespace App\Models;

use App\Models\Concerns\HasOrganizationScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesInvoiceItem extends Model
{
    use HasFactory;
    use HasOrganizationScope;

    protected $fillable = [
        'organization_id',
        'sales_invoice_id',
        'product_id',
        'batch_id',
        'sold_qty',
        'unit_price',
        'cost_at_sale',
        'tax_percent',
        'discount_amount',
        'line_total',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'sold_qty' => 'decimal:3',
            'unit_price' => 'decimal:4',
            'cost_at_sale' => 'decimal:4',
            'tax_percent' => 'decimal:2',
            'discount_amount' => 'decimal:4',
            'line_total' => 'decimal:4',
            'metadata' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function returnItems(): HasMany
    {
        return $this->hasMany(SalesReturnItem::class);
    }
}

