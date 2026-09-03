<?php

namespace Database\Seeders;

use App\Support\Bootstrap;
use Illuminate\Database\Seeder;

/**
 * `db:seed` and the application's own boot do the same thing, because they call
 * the same code. There is one description of a working installation — see
 * App\Support\Bootstrap — and no seeder holds a second copy of any of it.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Bootstrap::run();
    }
}
