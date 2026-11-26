<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * En el taller activo o, sin él, en el registrado con la base que se siembra (SeedingTenant, deuda #23).
     */
    public function run(): void
    {
        SeedingTenant::run(fn () => $this->call($this->seeders()));
    }

    /**
     * @return array<int, class-string>
     */
    private function seeders(): array
    {
        return [
            RolesAndPermissionsSeeder::class,
            AdminUserSeeder::class,
            MechanicSeeder::class,
            BrandSeeder::class,
            VehicleModelSeeder::class,
            ServiceCategorySeeder::class,
            ServiceSeeder::class,
            DiagnosticItemTemplateSeeder::class,
            MaintenancePlanSeeder::class,
            CheckListItemSeeder::class,
            CheckListItemServiceCategorySeeder::class,
            // Repuestos y stock antes del flujo del taller: los presupuestos y las OT los usan.
            PartCategorySeeder::class,
            SupplierSeeder::class,
            PartSeeder::class,
            InventorySeeder::class,
            // Compras antes del flujo del taller: el kardex se carga en orden cronológico.
            PurchaseSeeder::class,
            SupplierPaymentSeeder::class,
            ClientsSeeder::class,
            InsurerSeeder::class,
            VehiclesSeeder::class,
            // Flujo del taller por escenarios (ExampleScenarios).
            ReceptionSeeder::class,
            ClaimSeeder::class,
            ClaimDocumentSeeder::class,
            DiagnosticSeeder::class,
            DiagnosticItemSeeder::class,
            DiagnosticItemPhotoSeeder::class,
            BudgetSeeder::class,
            BudgetItemSeeder::class,
            WorkOrderSeeder::class,
            AllocationSeeder::class,
            ReceptionCancellationSeeder::class,
            // Facturación, cobro y entrega de los trabajos terminados.
            InvoiceStampSeeder::class,
            InvoiceSeeder::class,
            AllocationInvoiceSeeder::class,
            PaymentSeeder::class,
            CreditNoteSeeder::class,
            WithholdingSeeder::class,
            VehicleDeliverySeeder::class,
            // Ajustes de los últimos días, después de todos los consumos.
            InventoryAdjustmentSeeder::class,
            // Mantenimientos, comisiones liquidadas y estados de los avisos (al final: usan todo lo anterior).
            VehicleMaintenanceSeeder::class,
            CommissionSeeder::class,
            NotificationSeeder::class,
        ];
    }
}
