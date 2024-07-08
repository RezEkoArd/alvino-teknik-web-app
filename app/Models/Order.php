<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class Order extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'alamat', 'phone','catatan', 'brand_ac','teknisi_id','jadwal_kunjungan','total_price','status'];

    public function items():hasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function teknisi(): BelongsTo
    {
        return $this->belongsTo(Teknisi::class);
    }

    public function calculatedTotalPrice(): float
    {
        $totalPrice = 0;
        foreach ($this->items as $item) {
            $totalPrice += $item->quantity * $item->unit_price;
        }

        return $totalPrice;
    }

    
}
