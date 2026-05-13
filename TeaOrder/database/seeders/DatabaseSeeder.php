<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $now = now();

        $employees = [
            [// 3个店长 (manager)
                'store_id' => 1,
                'name' => '张伟',
                'phone' => '13800138001',
                'password' => Hash::make('123456'),
                'role' => 'manager',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'store_id' => 2,
                'name' => '李娜',
                'phone' => '13800138002',
                'password' => Hash::make('123456'),
                'role' => 'manager',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'store_id' => 3,
                'name' => '王强',
                'phone' => '13800138003',
                'password' => Hash::make('123456'),
                'role' => 'manager',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            // 3个员工 (staff)
            [
                'store_id' => 1,
                'name' => '刘芳',
                'phone' => '13900139001',
                'password' => Hash::make('123456'),
                'role' => 'staff',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'store_id' => 2,
                'name' => '陈明',
                'phone' => '13900139002',
                'password' => Hash::make('123456'),
                'role' => 'staff',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'store_id' => 3,
                'name' => '杨洋',
                'phone' => '13900139003',
                'password' => Hash::make('123456'),
                'role' => 'staff',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($employees as $employee) {
            Employee::create($employee);
        }

        $this->command->info('✅ 成功创建 3 个店长和 3 个员工');
    }
}
