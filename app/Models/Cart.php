<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = ['products', 'user_id', 'location', 'mobile', 'status', 'result'];

    protected $casts = [
        'products' => 'array', // تحويل products إلى مصفوفة تلقائياً
    ];

    public function Users()
    {
        return $this->belongsTo(User::class);
    }
}
