<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Crear permisos por grupo del sidebar
        $permisos = [
            ['name' => 'dashboard.view', 'group' => 'Dashboard', 'description' => 'Acceder al panel principal'],
            ['name' => 'otros.view', 'group' => 'Otros', 'description' => 'Gestionar catálogos auxiliares (Directores, Presupuestos, Regionales, Municipios, Dependencias, Tarifas)'],
            ['name' => 'proveedores.view', 'group' => 'Proveedores', 'description' => 'Gestionar proveedores y su información tributaria'],
            ['name' => 'contratos.view', 'group' => 'Contratos', 'description' => 'Gestionar contratos, productos y asignaciones'],
            ['name' => 'facturar.view', 'group' => 'Facturar', 'description' => 'Crear facturas, listar facturas y actas de recibo'],
            ['name' => 'pagos.view', 'group' => 'Pagos', 'description' => 'Gestionar pagos y su impresión'],
            ['name' => 'tramite.view', 'group' => 'Trámite de Pagos', 'description' => 'Gestionar trámites de pago y plantilla de documentos'],
            ['name' => 'informes.view', 'group' => 'Informes', 'description' => 'Gestionar informes, obligaciones, riesgos'],
            ['name' => 'registros.view', 'group' => 'Registros', 'description' => 'Gestionar registros, adiciones y reducciones'],
            ['name' => 'registros.traslados', 'group' => 'Registros', 'description' => 'Proponer y autorizar traslados de saldo entre rubros'],
            ['name' => 'reportes.view', 'group' => 'Reportes', 'description' => 'Ver reportes de contratos, facturación, retenciones y pagos'],
            ['name' => 'admin.manage-roles', 'group' => 'Administración', 'description' => 'Gestionar roles y permisos del sistema'],
        ];

        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(
                ['name' => $permiso['name'], 'guard_name' => 'web'],
                [
                    'group' => $permiso['group'],
                    'description' => $permiso['description'],
                ]
            );
        }

        // Crear rol Admin con todos los permisos
        $admin = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web'],
            ['description' => 'Administrador con acceso total al sistema']
        );
        $admin->givePermissionTo(Permission::all());

        // Crear rol Usuario por defecto (sin permisos inicialmente)
        Role::firstOrCreate(
            ['name' => 'usuario', 'guard_name' => 'web'],
            ['description' => 'Usuario estándar del sistema']
        );

        // Crear rol Presupuesto (aprueba traslados)
        $presupuesto = Role::firstOrCreate(
            ['name' => 'presupuesto', 'guard_name' => 'web'],
            ['description' => 'Encargado de presupuesto, autoriza traslados de saldo entre rubros']
        );
        $presupuesto->givePermissionTo(['dashboard.view', 'registros.view', 'registros.traslados']);
    }
}
