<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'invoice_id',
        'uuid',
        'type',
        'supplier',
        'customer',
        'amount',
        'issue_date',
        'content',
        'raw_data'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'issue_date' => 'date',
        'raw_data' => 'array'
    ];

    // Scope'lar
    public function scopeOutgoing($query)
    {
        return $query->where('type', 'OUT');
    }

    public function scopeIncoming($query)
    {
        return $query->where('type', 'IN');
    }

    public function scopeArchive($query)
    {
        return $query->where('type', 'ARCHIVE');
    }

    public function scopeLastThreeMonths($query)
    {
        return $query->where('issue_date', '>=', now()->subMonths(3));
    }
}
