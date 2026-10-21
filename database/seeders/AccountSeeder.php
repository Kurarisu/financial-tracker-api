<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::first();
        if (! $user) {
            return;
        }

        $accounts = [
            [
                'name' => 'BCA Tahapan',
                'type' => 'bank',
                'account_number' => '8210982341',
                'initial_balance' => 10000000.00,
                'current_balance' => 12500000.00,
                'color' => '#00529B',
                'icon' => 'landmark',
                'is_active' => true,
            ],
            [
                'name' => 'Bank Mandiri',
                'type' => 'bank',
                'account_number' => '1370018273641',
                'initial_balance' => 5000000.00,
                'current_balance' => 5000000.00,
                'color' => '#002D62',
                'icon' => 'landmark',
                'is_active' => true,
            ],
            [
                'name' => 'GoPay',
                'type' => 'ewallet',
                'account_number' => '081234567890',
                'initial_balance' => 200000.00,
                'current_balance' => 650000.00,
                'color' => '#00AED6',
                'icon' => 'wallet',
                'is_active' => true,
            ],
            [
                'name' => 'OVO',
                'type' => 'ewallet',
                'account_number' => '081234567890',
                'initial_balance' => 100000.00,
                'current_balance' => 350000.00,
                'color' => '#4C3494',
                'icon' => 'wallet',
                'is_active' => true,
            ],
            [
                'name' => 'Dompet Tunai (Cash)',
                'type' => 'cash',
                'account_number' => null,
                'initial_balance' => 500000.00,
                'current_balance' => 750000.00,
                'color' => '#10B981',
                'icon' => 'coins',
                'is_active' => true,
            ],
        ];

        foreach ($accounts as $acc) {
            Account::firstOrCreate(
                ['user_id' => $user->id, 'name' => $acc['name']],
                $acc
            );
        }
    }
}
