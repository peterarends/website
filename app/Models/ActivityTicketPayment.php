<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class ActivityTicketPayment extends Model
{
    /**
     * @inheritDoc
     */
    protected $dates = [
        'due_date'
    ];

    /**
     * @inheritDoc
     */
    protected $casts = [
        // Availability
        'for_member' => 'bool',
        'for_guest' => 'bool',

        // Pricing
        'price' => 'int',
        'total_price' => 'int'
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
     * Return owning ticket
     *
     * @return BelongsTo
     */
    public function activityTicket(): BelongsTo
    {
        return $this->belongsTo(ActivityTicket::class);
    }

    /**
     * Returns activity
     *
     * @return HasOneThrough
     */
    public function activity(): HasOneThrough
    {
        return $this->hasOneThrough(Activity::class, ActivityTicket::class);
    }
}
