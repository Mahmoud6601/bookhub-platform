<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    public const STATUSES = ['pending', 'approved', 'shipped', 'delivered', 'cancelled'];

    public const TRANSITIONS = [
        'pending' => ['approved', 'cancelled'],
        'approved' => ['shipped', 'cancelled'],
        'shipped' => ['delivered'],
        'delivered' => [],
        'cancelled' => [],
    ];

    protected $fillable = ['user_id', 'status', 'total'];

    protected $casts = ['total' => 'decimal:2'];

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
}
