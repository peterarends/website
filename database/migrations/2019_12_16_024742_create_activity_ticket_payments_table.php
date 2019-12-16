<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateActivityTicketPaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('activity_ticket_payments', function (Blueprint $table) {
            // Add Ids
            $table->bigIncrements('id');
            $table->unsignedBigInteger('activity_ticket_id');

            // Timestamps, for history
            $table->timestamps();

            // Add meta
            $table->string('name', 60);
            $table->string('statement', 16)->nullable()->default(null);
            $table->string('description')->nullable()->default(null);

            // Add availability
            $table->boolean('for_member')->default(1)->comment('Available for members');
            $table->boolean('for_guest')->default(1)->comment('Available for guests');

            // Add payment info
            $table->string('payment_type', 10)->default('intent');
            $table->unsignedSmallInteger('price')->default(0);
            $table->unsignedSmallInteger('total_price')->default(0);

            // Add due dates
            $table->timestamp('due_date')->nullable()->default(null);

            // Add constraint
            $table->foreign('activity_ticket_id')->references('id')->on('activity_tickets')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('activity_ticket_payments');
    }
}
