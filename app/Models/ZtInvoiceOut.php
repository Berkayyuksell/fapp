<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZtInvoiceOut extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'zt_invoices_out';

    protected $fillable = [
        'invoice_id',
        'uuid',
        'supplier',
        'customer',
        'amount',
        'issue_date',
        'content',
        'raw_data',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'issue_date' => 'date',
        'raw_data' => 'array',
    ];
}


