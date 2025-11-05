<?php
// database/migrations/2025_11_05_000000_create_geo_cache_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGeoCacheTable extends Migration
{
    public function up()
    {
        Schema::create('geo_cache', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('addr_hash', 64)->unique();
            $table->string('label', 255);
            $table->double('lat')->nullable();
            $table->double('lon')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('geo_cache');
    }
}
