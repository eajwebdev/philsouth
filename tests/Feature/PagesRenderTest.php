<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagesRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DemoSeeder::class);
    }

    public function test_core_pages_render_for_superadmin(): void
    {
        $super = User::where('email', 'super@philsouth.test')->firstOrFail();
        $item = Item::where('code', 'STL-DB')->firstOrFail();

        $pages = [
            ['dashboard', 'dashboard'],
            ['items.index', 'items/index'],
            ['inventory.index', 'inventory/index'],
            ['receiving.index', 'receiving/index'],
            ['withdrawals.index', 'withdrawals/index'],
            ['withdrawals.create', 'withdrawals/create'],
            ['sites.index', 'sites/index'],
            ['users.index', 'users/index'],
        ];

        foreach ($pages as [$routeName, $component]) {
            $this->actingAs($super)
                ->get(route($routeName))
                ->assertOk()
                ->assertInertia(fn ($page) => $page->component($component));
        }

        // Item detail + receiving create (bound / role-specific routes).
        $this->actingAs($super)->get(route('items.show', $item))
            ->assertOk()->assertInertia(fn ($p) => $p->component('items/show'));
    }

    public function test_ics_can_open_receiving_create(): void
    {
        $ics = User::where('email', 'ics@philsouth.test')->firstOrFail();

        $this->actingAs($ics)->get(route('receiving.create'))
            ->assertOk()->assertInertia(fn ($p) => $p->component('receiving/create'));
    }
}
