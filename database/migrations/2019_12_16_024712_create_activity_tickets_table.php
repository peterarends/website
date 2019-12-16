<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateActivityTicketsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('activity_tickets', function (Blueprint $table) {
            // Add IDs
            $table->bigIncrements('id');
            $table->unsignedBigInteger('activity_id');

            // Timestamps, for history
            $table->timestamps();

            // Add basic meta
            $table->string('name');
            $table->string('description')->nullable()->default(null);

            // Add availability
            $table->boolean('for_member')->default(1)->comment('Available for members');
            $table->boolean('for_guest')->default(1)->comment('Available for guests');

            // Add constraint
            $table->foreign('activity_id')->references('id')->on('activities')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('activity_tickets');
    }
}
