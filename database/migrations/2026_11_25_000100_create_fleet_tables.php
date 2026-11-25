<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Podaci voznog parka nad kojima agent radi preko alata (tool calling).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $t) {
            $t->id();
            $t->string('registracija')->unique();
            $t->string('marka_model');
            $t->string('tip');            // osobno, dostavno, kamion
            $t->string('gorivo');         // dizel, benzin, elektro
            $t->unsignedSmallInteger('godina');
            $t->unsignedInteger('km_servis')->comment('Kilometraža na zadnjem servisu');
            $t->date('datum_servisa');
            $t->unsignedInteger('km_ukupno');
            $t->timestamps();
        });

        Schema::create('trips', function (Blueprint $t) {
            $t->id();
            $t->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $t->string('vozac');
            $t->date('datum');
            $t->time('pocetak');
            $t->unsignedSmallInteger('trajanje_min');
            $t->decimal('km', 7, 1);
            $t->decimal('gorivo_l', 6, 1);
            $t->unsignedSmallInteger('max_brzina');
            $t->index(['vehicle_id', 'datum']);
        });

        Schema::create('alarms', function (Blueprint $t) {
            $t->id();
            $t->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $t->dateTime('vrijeme');
            $t->string('tip');            // prekoracenje_brzine, ostro_kocenje, prazan_hod, niska_razina_goriva
            $t->string('opis');
            $t->index(['vehicle_id', 'vrijeme']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alarms');
        Schema::dropIfExists('trips');
        Schema::dropIfExists('vehicles');
    }
};
