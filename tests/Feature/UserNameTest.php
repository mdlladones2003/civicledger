<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('formats first middle and last names as an appended full name', function () {
    $user = new User([
        'first_name' => 'Sofia',
        'middle_name' => 'Maria',
        'last_name' => 'Reyes',
    ]);

    expect($user->full_name)->toBe('Sofia Maria Reyes')
        ->and($user->toArray())->toHaveKey('full_name', 'Sofia Maria Reyes');
});

it('omits a missing middle name from the full name', function () {
    $user = new User([
        'first_name' => 'Cher',
        'middle_name' => null,
        'last_name' => '',
    ]);

    expect($user->full_name)->toBe('Cher');
});

it('splits existing full names into the new name fields', function () {
    $defaultConnection = config('database.default');

    config([
        'database.connections.user_name_migration_test' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
    ]);
    DB::purge('user_name_migration_test');
    DB::setDefaultConnection('user_name_migration_test');

    try {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        DB::table('users')->insert([
            ['id' => 1, 'name' => 'Sofia Maria Reyes'],
            ['id' => 2, 'name' => 'Cher'],
        ]);

        $migration = require base_path('database/migrations/2026_10_23_122214_split_name_fields_on_users_table.php');
        $migration->up();

        expect(Schema::hasColumn('users', 'name'))->toBeFalse()
            ->and(DB::table('users')->where('id', 1)->first())
            ->toMatchObject([
                'first_name' => 'Sofia',
                'middle_name' => 'Maria',
                'last_name' => 'Reyes',
            ])
            ->and(DB::table('users')->where('id', 2)->first())
            ->toMatchObject([
                'first_name' => 'Cher',
                'middle_name' => null,
                'last_name' => '',
            ]);

        $migration->down();

        expect(Schema::hasColumn('users', 'name'))->toBeTrue()
            ->and(DB::table('users')->where('id', 1)->value('name'))->toBe('Sofia Maria Reyes')
            ->and(DB::table('users')->where('id', 2)->value('name'))->toBe('Cher');
    } finally {
        DB::purge('user_name_migration_test');
        DB::setDefaultConnection($defaultConnection);
    }
});
