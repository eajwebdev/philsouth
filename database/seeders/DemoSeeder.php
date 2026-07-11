<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\Site;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
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

        $super = $make('Super Admin', 'super@philsouth', 'superadmin');
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

        // ==================== PHILSOUTH ITEMS ====================
        // Define the 10 unique item templates (these repeat across batches)
        $itemTemplates = [
            // Construction Materials (Category: Construction Materials)
            [
                'code' => 'PS-CO-CEM',
                'description' => 'Portland Cement #1',
                'uom' => 'Bag',
                'category' => 'Construction Materials',
                'subcategory' => 'Portland',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-CO-MAS',
                'description' => 'Masonry Cement #1',
                'uom' => 'Bag',
                'category' => 'Construction Materials',
                'subcategory' => 'Masonry',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-CO-SAND',
                'description' => 'Washed Sand #1',
                'uom' => 'Bag',
                'category' => 'Construction Materials',
                'subcategory' => 'Washed',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-CO-RSAND',
                'description' => 'River Sand #1',
                'uom' => 'Bag',
                'category' => 'Construction Materials',
                'subcategory' => 'River',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-CO-CSAND',
                'description' => 'Crushed Sand #1',
                'uom' => 'Bag',
                'category' => 'Construction Materials',
                'subcategory' => 'Crushed',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-CO-GRAVEL3',
                'description' => 'Gravel 3/4 #1',
                'uom' => 'Bag',
                'category' => 'Construction Materials',
                'subcategory' => 'Gravel',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-CO-GRAVEL1',
                'description' => 'Gravel 1/2 #1',
                'uom' => 'Bag',
                'category' => 'Construction Materials',
                'subcategory' => 'Gravel',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-CO-CRUSHER',
                'description' => 'Crusher Dust #1',
                'uom' => 'Bag',
                'category' => 'Construction Materials',
                'subcategory' => 'Crusher',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-CO-G1',
                'description' => 'G1 Base Course #1',
                'uom' => 'Bag',
                'category' => 'Construction Materials',
                'subcategory' => 'G1',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-CO-SUBBASE',
                'description' => 'Sub-base Aggregate #1',
                'uom' => 'Bag',
                'category' => 'Construction Materials',
                'subcategory' => 'Sub-base',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            // Asphalt (Category: Asphalt)
            [
                'code' => 'PS-AS-HOT',
                'description' => 'Hot Mix Asphalt #2',
                'uom' => 'Bag',
                'category' => 'Asphalt',
                'subcategory' => 'Hot',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-AS-COLD',
                'description' => 'Cold Mix Asphalt #2',
                'uom' => 'Bag',
                'category' => 'Asphalt',
                'subcategory' => 'Cold',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-AS-BINDER',
                'description' => 'Asphalt Binder #2',
                'uom' => 'Bag',
                'category' => 'Asphalt',
                'subcategory' => 'Asphalt',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-AS-BITUMEN',
                'description' => 'Bitumen Emulsion #2',
                'uom' => 'Bag',
                'category' => 'Asphalt',
                'subcategory' => 'Bitumen',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-AS-PRIME',
                'description' => 'Prime Coat #2',
                'uom' => 'Bag',
                'category' => 'Asphalt',
                'subcategory' => 'Prime',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-AS-TACK',
                'description' => 'Tack Coat #2',
                'uom' => 'Bag',
                'category' => 'Asphalt',
                'subcategory' => 'Tack',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-AS-SEALANT',
                'description' => 'Asphalt Sealant #2',
                'uom' => 'Bag',
                'category' => 'Asphalt',
                'subcategory' => 'Asphalt',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-AS-JOINT',
                'description' => 'Joint Filler #2',
                'uom' => 'Bag',
                'category' => 'Asphalt',
                'subcategory' => 'Joint',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-AS-PATCH',
                'description' => 'Asphalt Patch #2',
                'uom' => 'Bag',
                'category' => 'Asphalt',
                'subcategory' => 'Asphalt',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-AS-ANTI',
                'description' => 'Anti-strip Additive #2',
                'uom' => 'Bag',
                'category' => 'Asphalt',
                'subcategory' => 'Anti-strip',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            // Steel (Category: Steel)
            [
                'code' => 'PS-ST-REBAR',
                'description' => 'Rebar 10mm #3',
                'uom' => 'Piece',
                'category' => 'Steel',
                'subcategory' => 'Rebar',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => true, // Has variants for different sizes
            ],
            [
                'code' => 'PS-ST-TIE',
                'description' => 'Tie Wire #3',
                'uom' => 'Piece',
                'category' => 'Steel',
                'subcategory' => 'Tie',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-ST-MESH',
                'description' => 'Welded Wire Mesh #3',
                'uom' => 'Piece',
                'category' => 'Steel',
                'subcategory' => 'Welded',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-ST-ANGLE',
                'description' => 'Angle Bar #3',
                'uom' => 'Piece',
                'category' => 'Steel',
                'subcategory' => 'Angle',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-ST-FLAT',
                'description' => 'Flat Bar #3',
                'uom' => 'Piece',
                'category' => 'Steel',
                'subcategory' => 'Flat',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-ST-PLATE',
                'description' => 'Steel Plate #3',
                'uom' => 'Piece',
                'category' => 'Steel',
                'subcategory' => 'Steel',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-ST-GI',
                'description' => 'GI Wire #3',
                'uom' => 'Piece',
                'category' => 'Steel',
                'subcategory' => 'GI',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            // Drainage (Category: Drainage)
            [
                'code' => 'PS-DR-RCP300',
                'description' => 'RCP 300mm #4',
                'uom' => 'Piece',
                'category' => 'Drainage',
                'subcategory' => 'RCP',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-DR-RCP600',
                'description' => 'RCP 600mm #4',
                'uom' => 'Piece',
                'category' => 'Drainage',
                'subcategory' => 'RCP',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-DR-HDPE',
                'description' => 'HDPE Pipe #4',
                'uom' => 'Piece',
                'category' => 'Drainage',
                'subcategory' => 'HDPE',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-DR-PVC',
                'description' => 'PVC Pipe #4',
                'uom' => 'Piece',
                'category' => 'Drainage',
                'subcategory' => 'PVC',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-DR-CATCH',
                'description' => 'Catch Basin #4',
                'uom' => 'Piece',
                'category' => 'Drainage',
                'subcategory' => 'Catch',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-DR-MANHOLE',
                'description' => 'Manhole Cover #4',
                'uom' => 'Piece',
                'category' => 'Drainage',
                'subcategory' => 'Manhole',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-DR-CULVERT',
                'description' => 'Culvert Pipe #4',
                'uom' => 'Piece',
                'category' => 'Drainage',
                'subcategory' => 'Culvert',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-DR-GRATE',
                'description' => 'Drain Grate #4',
                'uom' => 'Piece',
                'category' => 'Drainage',
                'subcategory' => 'Drain',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-DR-GEOTEXTILE',
                'description' => 'Geotextile #4',
                'uom' => 'Piece',
                'category' => 'Drainage',
                'subcategory' => 'Geotextile',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-DR-GEOGRID',
                'description' => 'Geogrid #4',
                'uom' => 'Piece',
                'category' => 'Drainage',
                'subcategory' => 'Geogrid',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            // Safety (Category: Safety)
            [
                'code' => 'PS-SA-HARDHAT',
                'description' => 'Hard Hat #5',
                'uom' => 'Piece',
                'category' => 'Safety',
                'subcategory' => 'Hard',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-SA-VEST',
                'description' => 'Safety Vest #5',
                'uom' => 'Piece',
                'category' => 'Safety',
                'subcategory' => 'Safety',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-SA-GLOVES',
                'description' => 'Gloves #5',
                'uom' => 'Piece',
                'category' => 'Safety',
                'subcategory' => 'Gloves',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-SA-GOGGLES',
                'description' => 'Goggles #5',
                'uom' => 'Piece',
                'category' => 'Safety',
                'subcategory' => 'Goggles',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-SA-HARNESS',
                'description' => 'Harness #5',
                'uom' => 'Piece',
                'category' => 'Safety',
                'subcategory' => 'Harness',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-SA-EARPLUG',
                'description' => 'Ear Plug #5',
                'uom' => 'Piece',
                'category' => 'Safety',
                'subcategory' => 'Ear',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-SA-CONE',
                'description' => 'Traffic Cone #5',
                'uom' => 'Piece',
                'category' => 'Safety',
                'subcategory' => 'Traffic',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-SA-BARRICADE',
                'description' => 'Barricade #5',
                'uom' => 'Piece',
                'category' => 'Safety',
                'subcategory' => 'Barricade',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-SA-WARNING',
                'description' => 'Warning Tape #5',
                'uom' => 'Piece',
                'category' => 'Safety',
                'subcategory' => 'Warning',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-SA-REFLECT',
                'description' => 'Reflective Vest #5',
                'uom' => 'Piece',
                'category' => 'Safety',
                'subcategory' => 'Reflective',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            // Tools (Category: Tools) - ASSETS
            [
                'code' => 'PS-TO-SHOVEL',
                'description' => 'Shovel #6',
                'uom' => 'Unit',
                'category' => 'Tools',
                'subcategory' => 'Shovel',
                'classification' => 'Asset',
                'minimum_stock' => 5,
                'reorder_level' => 2,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-TO-PICK',
                'description' => 'Pick Mattock #6',
                'uom' => 'Unit',
                'category' => 'Tools',
                'subcategory' => 'Pick',
                'classification' => 'Asset',
                'minimum_stock' => 5,
                'reorder_level' => 2,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-TO-WHEEL',
                'description' => 'Wheelbarrow #6',
                'uom' => 'Unit',
                'category' => 'Tools',
                'subcategory' => 'Wheelbarrow',
                'classification' => 'Asset',
                'minimum_stock' => 5,
                'reorder_level' => 2,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-TO-HAMMER',
                'description' => 'Hammer #6',
                'uom' => 'Unit',
                'category' => 'Tools',
                'subcategory' => 'Hammer',
                'classification' => 'Asset',
                'minimum_stock' => 5,
                'reorder_level' => 2,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-TO-SLEDGE',
                'description' => 'Sledge Hammer #6',
                'uom' => 'Unit',
                'category' => 'Tools',
                'subcategory' => 'Sledge',
                'classification' => 'Asset',
                'minimum_stock' => 5,
                'reorder_level' => 2,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-TO-CROWBAR',
                'description' => 'Crowbar #6',
                'uom' => 'Unit',
                'category' => 'Tools',
                'subcategory' => 'Crowbar',
                'classification' => 'Asset',
                'minimum_stock' => 5,
                'reorder_level' => 2,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-TO-TAPE',
                'description' => 'Measuring Tape #6',
                'uom' => 'Unit',
                'category' => 'Tools',
                'subcategory' => 'Measuring',
                'classification' => 'Asset',
                'minimum_stock' => 5,
                'reorder_level' => 2,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-TO-LEVEL',
                'description' => 'Level Bar #6',
                'uom' => 'Unit',
                'category' => 'Tools',
                'subcategory' => 'Level',
                'classification' => 'Asset',
                'minimum_stock' => 5,
                'reorder_level' => 2,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-TO-BOLT',
                'description' => 'Bolt Cutter #6',
                'uom' => 'Unit',
                'category' => 'Tools',
                'subcategory' => 'Bolt',
                'classification' => 'Asset',
                'minimum_stock' => 5,
                'reorder_level' => 2,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-TO-PIPE',
                'description' => 'Pipe Wrench #6',
                'uom' => 'Unit',
                'category' => 'Tools',
                'subcategory' => 'Pipe',
                'classification' => 'Asset',
                'minimum_stock' => 5,
                'reorder_level' => 2,
                'has_variants' => false,
            ],
            // Power Tools (Category: Power Tools) - ASSETS
            [
                'code' => 'PS-PO-ANGLE',
                'description' => 'Angle Grinder #7',
                'uom' => 'Unit',
                'category' => 'Power Tools',
                'subcategory' => 'Angle',
                'classification' => 'Asset',
                'minimum_stock' => 5,
                'reorder_level' => 2,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-PO-ROTARY',
                'description' => 'Rotary Hammer #7',
                'uom' => 'Unit',
                'category' => 'Power Tools',
                'subcategory' => 'Rotary',
                'classification' => 'Asset',
                'minimum_stock' => 5,
                'reorder_level' => 2,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-PO-IMPACT',
                'description' => 'Impact Drill #7',
                'uom' => 'Unit',
                'category' => 'Power Tools',
                'subcategory' => 'Impact',
                'classification' => 'Asset',
                'minimum_stock' => 5,
                'reorder_level' => 2,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-PO-CIRCULAR',
                'description' => 'Circular Saw #7',
                'uom' => 'Unit',
                'category' => 'Power Tools',
                'subcategory' => 'Circular',
                'classification' => 'Asset',
                'minimum_stock' => 5,
                'reorder_level' => 2,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-PO-CONCRETE',
                'description' => 'Concrete Vibrator #7',
                'uom' => 'Unit',
                'category' => 'Power Tools',
                'subcategory' => 'Concrete',
                'classification' => 'Asset',
                'minimum_stock' => 5,
                'reorder_level' => 2,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-PO-GENERATOR',
                'description' => 'Generator #7',
                'uom' => 'Unit',
                'category' => 'Power Tools',
                'subcategory' => 'Generator',
                'classification' => 'Asset',
                'minimum_stock' => 5,
                'reorder_level' => 2,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-PO-WATER',
                'description' => 'Water Pump #7',
                'uom' => 'Unit',
                'category' => 'Power Tools',
                'subcategory' => 'Water',
                'classification' => 'Asset',
                'minimum_stock' => 5,
                'reorder_level' => 2,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-PO-CUTOFF',
                'description' => 'Cut-off Saw #7',
                'uom' => 'Unit',
                'category' => 'Power Tools',
                'subcategory' => 'Cut-off',
                'classification' => 'Asset',
                'minimum_stock' => 5,
                'reorder_level' => 2,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-PO-AIR',
                'description' => 'Air Compressor #7',
                'uom' => 'Unit',
                'category' => 'Power Tools',
                'subcategory' => 'Air',
                'classification' => 'Asset',
                'minimum_stock' => 5,
                'reorder_level' => 2,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-PO-WELDING',
                'description' => 'Welding Machine #7',
                'uom' => 'Unit',
                'category' => 'Power Tools',
                'subcategory' => 'Welding',
                'classification' => 'Asset',
                'minimum_stock' => 5,
                'reorder_level' => 2,
                'has_variants' => false,
            ],
            // Consumables (Category: Consumables)
            [
                'code' => 'PS-CO-DIESEL',
                'description' => 'Diesel #8',
                'uom' => 'Liter',
                'category' => 'Consumables',
                'subcategory' => 'Diesel',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-CO-ENGINE',
                'description' => 'Engine Oil #8',
                'uom' => 'Liter',
                'category' => 'Consumables',
                'subcategory' => 'Engine',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-CO-HYDRAULIC',
                'description' => 'Hydraulic Oil #8',
                'uom' => 'Liter',
                'category' => 'Consumables',
                'subcategory' => 'Hydraulic',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-CO-GREASE',
                'description' => 'Grease #8',
                'uom' => 'Liter',
                'category' => 'Consumables',
                'subcategory' => 'Grease',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-CO-CUTDISC',
                'description' => 'Cutting Disc #8',
                'uom' => 'Liter',
                'category' => 'Consumables',
                'subcategory' => 'Cutting',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-CO-WELDROD',
                'description' => 'Welding Rod #8',
                'uom' => 'Liter',
                'category' => 'Consumables',
                'subcategory' => 'Welding',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-CO-PAINT',
                'description' => 'Paint #8',
                'uom' => 'Liter',
                'category' => 'Consumables',
                'subcategory' => 'Paint',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-CO-THINNER',
                'description' => 'Paint Thinner #8',
                'uom' => 'Liter',
                'category' => 'Consumables',
                'subcategory' => 'Paint',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-CO-MARKING',
                'description' => 'Marking Paint #8',
                'uom' => 'Liter',
                'category' => 'Consumables',
                'subcategory' => 'Marking',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
            [
                'code' => 'PS-CO-CABLE',
                'description' => 'Cable Tie #8',
                'uom' => 'Liter',
                'category' => 'Consumables',
                'subcategory' => 'Cable',
                'classification' => 'Consumable',
                'minimum_stock' => 100,
                'reorder_level' => 50,
                'has_variants' => false,
            ],
        ];

        // Create all items
        $createdItems = collect();
        foreach ($itemTemplates as $template) {
            $item = Item::firstOrCreate(
                ['code' => $template['code']],
                [
                    'description' => $template['description'],
                    'uom' => $template['uom'],
                    'category' => $template['category'],
                    'has_variants' => $template['has_variants'],
                    // Store additional metadata in a JSON field if available
                    'metadata' => [
                        'subcategory' => $template['subcategory'],
                        'classification' => $template['classification'],
                        'minimum_stock' => $template['minimum_stock'],
                        'reorder_level' => $template['reorder_level'],
                    ],
                ]
            );
            $createdItems->push($item);
        }

        // ==================== REBAR VARIANTS ====================
        // Special handling for Rebar (has variants)
        $rebarItem = Item::where('code', 'PS-ST-REBAR')->first();
        if ($rebarItem) {
            // Rename the auto-default variant
            $rebarItem->variants()->where('is_default', true)->update([
                'sku' => 'PS-ST-REBAR-10',
                'label' => '10mm x 6m',
                'attributes' => ['size' => '10mm', 'length' => '6m'],
            ]);

            // Add other rebar sizes
            $rebarSizes = [
                ['sku' => 'PS-ST-REBAR-12', 'label' => '12mm x 6m', 'size' => '12mm'],
                ['sku' => 'PS-ST-REBAR-16', 'label' => '16mm x 6m', 'size' => '16mm'],
                ['sku' => 'PS-ST-REBAR-20', 'label' => '20mm x 6m', 'size' => '20mm'],
            ];

            foreach ($rebarSizes as $size) {
                ItemVariant::firstOrCreate(
                    ['sku' => $size['sku']],
                    [
                        'item_id' => $rebarItem->id,
                        'label' => $size['label'],
                        'is_default' => false,
                        'is_active' => true,
                        'attributes' => ['size' => $size['size'], 'length' => '6m'],
                    ]
                );
            }
        }

        // ==================== COLLECT ALL VARIANTS ====================
        $allVariants = collect();
        foreach ($createdItems as $item) {
            if ($item->has_variants) {
                $allVariants = $allVariants->concat($item->variants()->get());
            } else {
                $variant = $item->defaultVariant;
                if ($variant) {
                    $allVariants->push($variant);
                }
            }
        }

        // ==================== GENERATE BARCODES ====================
        foreach ($allVariants as $variant) {
            if (! $variant->barcode) {
                $variant->update(['barcode' => 'PS-'.$variant->sku]);
            }
        }

        // ==================== OPENING BALANCES ====================
        $stockService = app(StockService::class);
        foreach ([$makati, $qc, $cebu] as $site) {
            foreach ($allVariants as $variant) {
                // Skip if variant doesn't exist or is not active
                if (!$variant || !$variant->exists) {
                    continue;
                }

                // Different stock levels for consumables vs assets
                $item = $variant->item;
                $classification = $item->metadata['classification'] ?? 'Consumable';
                
                if ($classification === 'Asset') {
                    // Assets have lower stock (1-3 units)
                    $qty = fake()->numberBetween(1, 3);
                } else {
                    // Consumables have higher stock (50-400)
                    $qty = fake()->numberBetween(50, 400);
                }

                try {
                    $stockService->postMovement($site, $variant, 'in', 'purchase', $qty, [
                        'movement_date' => now()->subDays(20)->toDateString(),
                        'remarks' => 'Opening stock - ' . $item->description,
                        'created_by' => $admin->id,
                        'dr_ws_no' => 'OPEN-' . strtoupper(Str::random(6)),
                    ]);
                } catch (\Exception $e) {
                    // Log error but continue
                    \Log::error('Failed to post opening stock', [
                        'site' => $site->code,
                        'variant' => $variant->sku,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        unset($super);
    }
}