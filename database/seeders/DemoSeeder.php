<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    /**
     * Seeds accounts, sites, assignments, and the site roster only.
     * Items, variants, and stock are NOT seeded — users build their own
     * catalogue from the Items page (UoM is free text: bag, pcs, unit…).
     */
    public function run(): void
    {
        // ==================== USERS ====================
        $make = function (string $name, string $email, string $role): User {
            $user = User::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => Hash::make('password')],
            );
            $user->syncRoles([$role]);

            return $user;
        };

        $make('Super Admin', 'super@philsouth', 'superadmin');
        $admin = $make('Ana Administrator', 'admin@philsouth', 'administrator');
        $eng1 = $make('Ed Engineer', 'engineer@philsouth', 'engineer');
        $eng2 = $make('Elly Engineer', 'engineer2@philsouth', 'engineer');
        $ics1 = $make('Ivan ICS', 'ics@philsouth', 'ics');
        $ics2 = $make('Iris ICS', 'ics2@philsouth', 'ics');

        // ==================== SITES ====================
        $sites = collect([
            ['code' => 'MKT-01', 'name' => 'Makati Tower', 'address' => 'Ayala Ave, Makati City'],
            ['code' => 'QC-02', 'name' => 'Quezon City Residences', 'address' => 'Commonwealth Ave, Quezon City'],
            ['code' => 'CEB-03', 'name' => 'Cebu Business Park', 'address' => 'Cardinal Rosales Ave, Cebu City'],
        ])->map(fn ($s) => Site::firstOrCreate(['code' => $s['code']], $s));

        [$makati, $qc, $cebu] = [$sites[0], $sites[1], $sites[2]];

        // ==================== SITE ASSIGNMENTS ====================
        // Administrator assigns engineer 1 to two sites
        $makati->users()->syncWithoutDetaching([$eng1->id => ['assigned_by' => $admin->id]]);
        $qc->users()->syncWithoutDetaching([$eng1->id => ['assigned_by' => $admin->id]]);
        // Engineer 2 on Cebu
        $cebu->users()->syncWithoutDetaching([$eng2->id => ['assigned_by' => $admin->id]]);

        // Engineer 1 assigns ICS 1 to Makati
        $makati->users()->syncWithoutDetaching([$ics1->id => ['assigned_by' => $eng1->id]]);
        // ICS 2 on QC and Cebu
        $qc->users()->syncWithoutDetaching([$ics2->id => ['assigned_by' => $eng1->id]]);
        $cebu->users()->syncWithoutDetaching([$ics2->id => ['assigned_by' => $eng2->id]]);

        // ==================== SITE ROSTER ====================
        // Named personnel (name + position) used on withdrawal slips.
        $roster = [
            $makati->id => [['Ramon Cruz', 'Foreman'], ['Jose Dela Peña', 'Mason'], ['Nena Villar', 'Timekeeper']],
            $qc->id => [['Carlo Reyes', 'Foreman'], ['Bert Santos', 'Electrician']],
            $cebu->id => [['Danny Lim', 'Site Supervisor'], ['Marites Uy', 'Laborer']],
        ];
        foreach ($roster as $siteId => $people) {
            foreach ($people as [$name, $position]) {
                Employee::firstOrCreate(
                    ['site_id' => $siteId, 'name' => $name],
                    ['position' => $position, 'created_by' => $eng1->id],
                );
            }
        }
    }
}
