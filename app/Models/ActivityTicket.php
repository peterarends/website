<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActivityTicket extends Model
{
    /**
     * @inheritDoc
     */
    protected $casts = [
        // Availability
        'for_member' => 'bool',
        'for_guest' => 'bool',
    ];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [
        'id',
        'created_at',
        'updated_at'
    ];

    /**
     * Return owning activity
     *
     * @return BelongsTo
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * Returns ticket payments
     *
     * @return HasMany
     */
    public function ticketPayments(): HasMany
    {
        return $this->hasMany(ActivityTicketPayment::class);
    }
}
