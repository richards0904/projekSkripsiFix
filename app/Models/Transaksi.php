<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaksi extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'transaksi';

    // If using a composite primary key ['order_id', 'item'] as defined in the migration,
    // it's generally better not to specify a single $primaryKey here,
    // as Eloquent's default behavior (e.g., for find()) is for single primary keys.
    // protected $primaryKey = 'order_id'; // Comment out or remove
    // public $incrementing = false; // Related to $primaryKey, comment out or remove if $primaryKey is removed
    // protected $keyType = 'string'; // Related to $primaryKey, comment out or remove if $primaryKey is removed
    public $timestamps = false;
    protected $fillable = [
        'order_id', 'date', 'item',
    ];
    protected $casts = [
        'date' => 'date:d-m-Y', // Automatically format 'date' to dd-mm-yyyy on retrieval
    ];

}
