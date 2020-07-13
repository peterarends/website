<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RecreatePasswordResetsTable extends Migration
{
    /**
     * Run the migrations.
     * @return void
     */
    public function up()
    {
        Schema::create('password_resets', static function (Blueprint $table) {
            $table->string('email', 185)->index();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('password_resets');
    }
}