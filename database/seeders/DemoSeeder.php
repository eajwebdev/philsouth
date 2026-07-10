<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\Site;
use App\Models\User;
use App\Services\StockService;
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

        // Item master — simple items (each auto-creates one default variant).
        $simple = collect([
            ['code' => 'CEM-001', 'description' => 'Portland Cement 40kg', 'uom' => 'bag', 'category' => 'Cement'],
            ['code' => 'AGG-020', 'description' => 'Washed Sand', 'uom' => 'cu.m', 'category' => 'Aggregates'],
            ['code' => 'AGG-021', 'description' => 'Gravel 3/4"', 'uom' => 'cu.m', 'category' => 'Aggregates'],
            ['code' => 'PLY-018', 'description' => 'Marine Plywood 18mm', 'uom' => 'pc', 'category' => 'Finishing'],
            ['code' => 'ELE-101', 'description' => 'THHN Wire #12 (per box)', 'uom' => 'box', 'category' => 'Electrical'],
            ['code' => 'PLM-055', 'description' => 'PVC Pipe 4" x 3m', 'uom' => 'pc', 'category' => 'Plumbing'],
        ])->map(fn ($i) => Item::firstOrCreate(['code' => $i['code']], $i));

        // A variant item: one product, several stockable specs.
        $bar = Item::firstOrCreate(
            ['code' => 'STL-DB'],
            ['description' => 'Deformed Reinforcing Bar', 'uom' => 'pc', 'category' => 'Steel', 'has_variants' => true],
        );
        // Relabel the auto default and add the real specs.
        $bar->variants()->where('is_default', true)->update(['sku' => 'STL-DB-10', 'label' => '10mm x 6m']);
        $barVariants = collect([
            ['sku' => 'STL-DB-12', 'label' => '12mm x 6m', 'attributes' => ['size' => '12mm', 'length' => '6m']],
            ['sku' => 'STL-DB-16', 'label' => '16mm x 6m', 'attributes' => ['size' => '16mm', 'length' => '6m']],
        ])->map(fn ($v) => ItemVariant::firstOrCreate(
            ['sku' => $v['sku']],
            [...$v, 'item_id' => $bar->id, 'is_default' => false, 'is_active' => true],
        ));

        // Every stockable variant across all items.
        $variants = $simple->map(fn ($i) => $i->defaultVariant)
            ->concat([$bar->variants()->where('is_default', true)->first()])
            ->concat($barVariants)
            ->filter();

        // Give each variant a scannable barcode (demo).
        foreach ($variants as $variant) {
            if (! $variant->barcode) {
                $variant->update(['barcode' => 'PS-'.$variant->sku]);
            }
        }

        // Opening balances posted through the ledger so reports reconcile.
        $stockService = app(StockService::class);
        foreach ([$makati, $qc, $cebu] as $site) {
            foreach ($variants as $variant) {
                $stockService->postMovement($site, $variant, 'in', 'purchase', fake()->numberBetween(50, 400), [
                    'movement_date' => now()->subDays(20)->toDateString(),
                    'remarks' => 'Opening stock',
                    'created_by' => $admin->id,
                    'dr_ws_no' => 'OPEN',
                ]);
            }
        }

        unset($super);
    }
}
