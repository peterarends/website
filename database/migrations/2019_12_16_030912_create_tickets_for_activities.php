<?php

use App\Models\ActivityTicket;
use App\Models\Activity;
use App\Models\ActivityTicketPayment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CreateTicketsForActivities extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    public function up()
    {
        // Don't create stuff on error
        DB::beginTransaction();

        // Create stuff for each activity
        /** @var Activity $activity */
        foreach (Activity::cursor() as $activity) {
            // Activity does not enroll
            if ($activity->seats === null) {
                continue;
            }

            $paidEvent = $activity->price_member !== null || $activity->price_guest !== null;
            $singleTicket = (
                !$activity->is_public ||
                !$paidEvent ||
                $activity->price_member === $activity->price_guest
            );

            if ($singleTicket) {
                $this->addSingleTicket($activity, $paidEvent);
                continue;
            }

            $this->addSplitTickets($activity, $paidEvent);
        }

        // Send changes
        DB::commit();
    }

    public function down()
    {
        // Don't delete stuff on error
        DB::beginTransaction();
        $activities = Activity::with('tickets', 'tickets.ticket_payments')->cursor();

        // Restore stuff for each activity
        /** @var Activity $activity */
        foreach ($activities as $activity) {
            $payments = ActivityTicketPayment::whereIn('activity_ticket_id', $activity->tickets->pluck('id'))->get();

            // Come up with a statement
            $activity->statement = Str::limit(Str::ascii($activity->name), 16, '');

            // Check each payment
            foreach ($payments as $payment) {
                // Re-set member price
                if ($payment->for_member) {
                    $activity->price_member = $payment->price;
                }

                // Re-set guest price
                if ($payment->for_guest) {
                    $activity->price_guest = $payment->price;
                }

                // Set payment type
                $activity->payment_type = $payment->payment_type;

                // Set statement, if not empty
                if (!empty($payment->statement)) {
                    $activity->statement = $payment->statement;
                }
            }

            // Save changes
            $activity->save();
        }

        // Apply changes
        DB::commit();
    }

    /**
     * Creates a single ticket for both members and guest. Used when there's no
     * significant difference between the two.
     *
     * @param Activity $activity
     * @param bool $paidEvent
     * @return void
     */
    private function addSingleTicket(Activity $activity, bool $paidEvent): void
    {
        // Add ticket
        $ticket = ActivityTicket::create([
            'activity_id' => $activity->id,
            'name' => 'Regulier',
            'description' => "Regulier ticket voor {$activity->name}",
            'for_member' => true,
            'for_guest' => $activity->is_public
        ]);

        // Done if the event is free
        if (!$paidEvent) {
            return;
        }

        // Add single payment
        ActivityTicketPayment::create([
            'activity_ticket_id' => $ticket->id,
            'name' => "Standaard betaling voor {$activity->name}",
            'statement' => $activity->statement,
            'for_member' => true,
            'for_guest' => $activity->is_public,
            'price' => $activity->price_member
        ]);
    }

    /**
     * Creates twot tickets, one for members and one for guests.
     * Used when there are significant differences (free for one and paid for the other, for example)
     *
     * @param Activity $activity
     * @param bool $paidEvent
     * @return void
     */
    private function addSplitTickets(Activity $activity, bool $paidEvent): void
    {
        $ticketMeta = [
            'activity_id' => $activity->id,
            'name' => 'Regulier',
            'description' => "Ticket voor {$activity->name}",
        ];

        $memberTicket = ActivityTicket::create(
            array_merge($ticketMeta, ['for_member' => true])
        );

        if ($activity->is_public) {
            $guestTicket = ActivityTicket::create(
                array_merge($ticketMeta, ['for_guest' => true])
            );
        }

        // Skip if free
        if (!$paidEvent) {
            return;
        }

        // Shared stuff
        $paymentMeta = [
            'statement' => $activity->statement ?? null,
        ];

        // Add member ticket
        if ($activity->price_member > 0) {
            ActivityTicketPayment::create(array_merge($paymentMeta, [
                'activity_ticket_id' => $memberTicket->id,
                'name' => "Leden betaling voor {$activity->name}",
                'for_member' => true,
                'for_guest' => false,
                'price' => $activity->price_member,
                'total_price' => $activity->total_price_member
            ]));
        }

        // Add guest ticket
        if ($activity->is_public && $activity->price_guest > 0) {
            ActivityTicketPayment::create(array_merge($paymentMeta, [
                'activity_ticket_id' => $guestTicket->id,
                'name' => "Bezoekers betaling voor {$activity->name}",
                'for_member' => false,
                'for_guest' => true,
                'price' => $activity->price_guest,
                'total_price' => $activity->total_price_guest
            ]));
        }
    }
}
