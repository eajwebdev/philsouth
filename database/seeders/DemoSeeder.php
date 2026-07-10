<?php

namespace Database\Seeders;

use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $make = function (string $name, string $email, string $role): User {
            $user = User::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => Hash::make('password')],
            );
            $user->syncRoles([$role]);

            return $user;
        };

        $super = $make('Super Admin', 'super@philsouth.test', 'superadmin');
        $admin = $make('Ana Administrator', 'admin@philsouth.test', 'administrator');
        $eng1 = $make('Ed Engineer', 'engineer@philsouth.test', 'engineer');
        $eng2 = $make('Elly Engineer', 'engineer2@philsouth.test', 'engineer');
        $ics1 = $make('Ivan ICS', 'ics@philsouth.test', 'ics');
        $ics2 = $make('Iris ICS', 'ics2@philsouth.test', 'ics');

        $sites = collect([
            ['code' => 'MKT-01', 'name' => 'Makati Tower', 'address' => 'Ayala Ave, Makati City'],
            ['code' => 'QC-02', 'name' => 'Quezon City Residences', 'address' => 'Commonwealth Ave, Quezon City'],
            ['code' => 'CEB-03', 'name' => 'Cebu Business Park', 'address' => 'Cardinal Rosales Ave, Cebu City'],
        ])->map(fn ($s) => Site::firstOrCreate(['code' => $s['code']], $s));

        [$makati, $qc, $cebu] = [$sites[0], $sites[1], $sites[2]];

        // administrator assigns engineer 1 to two sites.
        $makati->users()->syncWithoutDetaching([$eng1->id => ['assigned_by' => $admin->id]]);
        $qc->users()->syncWithoutDetaching([$eng1->id => ['assigned_by' => $admin->id]]);
        // engineer 2 on Cebu.
        $cebu->users()->syncWithoutDetaching([$eng2->id => ['assigned_by' => $admin->id]]);

        // engineer 1 assigns ICS 1 to Makati (one of the engineer's sites).
        $makati->users()->syncWithoutDetaching([$ics1->id => ['assigned_by' => $eng1->id]]);
        // ICS 2 on QC and Cebu.
        $qc->users()->syncWithoutDetaching([$ics2->id => ['assigned_by' => $eng1->id]]);
        $cebu->users()->syncWithoutDetaching([$ics2->id => ['assigned_by' => $eng2->id]]);

        unset($super);
    }
}
