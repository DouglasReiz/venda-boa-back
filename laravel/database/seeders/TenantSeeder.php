<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ── Admin global (sem tenant — vê tudo) ──────────────────────────────
        User::updateOrCreate(
            ['email' => 'admin@sistema.com'],
            [
                'name'      => 'Admin Global',
                'password'  => Hash::make('admin123'),
                'role'      => 'admin_global',
                'tenant_id' => null,
            ]
        );

        // ── Tenant 1: Manelito's ──────────────────────────────────────────────
        $manelitos = Tenant::updateOrCreate(
            ['slug' => 'manelitos'],
            ['nome' => "Manelito's Salgados & Cia", 'cnpj' => '00.000.000/0001-00']
        );

        // Admin do Manelito's
        User::updateOrCreate(
            ['email' => 'admin@manelitos.com'],
            [
                'name'      => 'Douglas Admin',
                'password'  => Hash::make('manelitos123'),
                'role'      => 'admin',
                'tenant_id' => $manelitos->id,
            ]
        );

        // Operador do Manelito's
        User::updateOrCreate(
            ['email' => 'caixa@manelitos.com'],
            [
                'name'      => 'Operador Caixa',
                'password'  => Hash::make('caixa123'),
                'role'      => 'operador',
                'tenant_id' => $manelitos->id,
            ]
        );

        $this->command->info('Tenant e usuários criados com sucesso!');
        $this->command->table(
            ['Role', 'Email', 'Senha'],
            [
                ['admin_global', 'admin@sistema.com',  'admin123'],
                ['admin',        'admin@manelitos.com', 'manelitos123'],
                ['operador',     'caixa@manelitos.com', 'caixa123'],
            ]
        );
    }
}
