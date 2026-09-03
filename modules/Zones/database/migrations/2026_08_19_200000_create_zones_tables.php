<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zones — drawn areas, and the one table that attaches them to anything.
 *
 * **The ring is JSON, not a spatial type.** The obvious schema is a `POLYGON`
 * column and `ST_Contains`, which is what a MySQL-only app would write. This
 * installation runs SQLite, where neither exists, and adding a spatial
 * extension to make one query convenient is a poor trade against the app being
 * runnable from a single file.
 *
 * So the ring is stored as JSON and the containment test is ray-casting in
 * PHP — but *only* after an indexed bounding-box filter has thrown out the
 * zones that cannot possibly contain the point. The box is what makes this
 * cheap: a country's worth of zones narrows to one or two rows in SQL, and
 * only those get their rings walked. Without the box, resolving a point would
 * mean decoding every polygon in the table.
 *
 * `zonables` is polymorphic on purpose. The system this is modelled on grew
 * four separate mechanisms for the same idea — a `zone_id` column on providers,
 * a `category_zone` pivot, a `user_zones` pivot, another `zone_id` on
 * addresses — and each one needed its own query, its own form control and its
 * own migration when a fifth thing needed zoning. One table answers "what is in
 * this zone" and "which zones is this in" for every model at once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('organization_id')->index();

            $table->string('code', 20)->nullable();
            $table->string('name');
            $table->text('description')->nullable();

            // Drawn on the map and shown in every list that mentions the zone.
            $table->string('colour', 9)->default('#2f6f4e');

            /*
            | The ring, as [[lat, lng], ...] in drawing order.
            |
            | Not GeoJSON: GeoJSON orders coordinates lng-first, which is the
            | reverse of how Leaflet, this app's forms and every human here
            | write them. Storing one order and reading another is where the
            | "my zone is in the Indian Ocean" class of bug comes from.
            */
            $table->json('coordinates')->nullable();

            // The bounding box, so the database can rule a zone out without
            // anyone decoding its ring. Indexed together because the filter
            // always uses all four.
            $table->double('min_lat')->nullable();
            $table->double('max_lat')->nullable();
            $table->double('min_lng')->nullable();
            $table->double('max_lng')->nullable();

            // The centroid, for dropping a pin without walking the ring.
            $table->double('centre_lat')->nullable();
            $table->double('centre_lng')->nullable();

            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->index(['organization_id', 'active']);
            $table->index(['min_lat', 'max_lat']);
            $table->index(['min_lng', 'max_lng']);
        });

        Schema::create('zonables', function (Blueprint $table) {
            $table->id();

            $table->string('zone_id')->index();
            $table->string('zonable_type');
            $table->string('zonable_id');

            /*
            | What this zone means for this record.
            |
            | A provider's zones are where it will travel; an organization's are
            | where it trades; a shipment's is where it is going. Same pair of
            | ids, three different questions — without a role, "every zone this
            | provider touches" cannot be told apart from "the one it is based
            | in".
            */
            $table->string('role')->default('coverage');

            $table->timestamps();

            $table->unique(['zone_id', 'zonable_type', 'zonable_id', 'role'], 'zonables_unique');
            $table->index(['zonable_type', 'zonable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zonables');
        Schema::dropIfExists('zones');
    }
};
