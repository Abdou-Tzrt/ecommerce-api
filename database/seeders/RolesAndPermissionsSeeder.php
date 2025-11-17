<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Define product permissions
        Permission::create(['name' => 'view products', 'guard_name' => 'sanctum']);
        Permission::create(['name' => 'create products', 'guard_name' => 'sanctum']);
        Permission::create(['name' => 'edit products', 'guard_name' => 'sanctum']);
        Permission::create(['name' => 'delete products', 'guard_name' => 'sanctum']);

        // Define order permissions
        Permission::create(['name' => 'view orders', 'guard_name' => 'sanctum']);
        Permission::create(['name' => 'create orders', 'guard_name' => 'sanctum']);
        Permission::create(['name' => 'update orders', 'guard_name' => 'sanctum']);
        Permission::create(['name' => 'cancel orders', 'guard_name' => 'sanctum']);

        // Define user permissions
        Permission::create(['name' => 'view users', 'guard_name' => 'sanctum']);
        Permission::create(['name' => 'edit users', 'guard_name' => 'sanctum']);

        // Define delivery permissions
        Permission::create(['name' => 'view deliveries', 'guard_name' => 'sanctum']);
        Permission::create(['name' => 'update delivery status', 'guard_name' => 'sanctum']);

        // Create Admin role and assign all permissions
        $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'sanctum']);
        $adminRole->givePermissionTo(Permission::all());

        // Create Customer role with limited permissions
        $customerRole = Role::create(['name' => 'customer', 'guard_name' => 'sanctum']);
        $customerRole->givePermissionTo([
            'view products',
            'view orders',
            'create orders',
            'cancel orders'
        ]);

        // Create Delivery role
        $deliveryRole = Role::create(['name' => 'delivery', 'guard_name' => 'sanctum']);
        $deliveryRole->givePermissionTo([
            'view deliveries',
            'update delivery status',
            'view orders',
            'view products'
        ]);
    }
}
