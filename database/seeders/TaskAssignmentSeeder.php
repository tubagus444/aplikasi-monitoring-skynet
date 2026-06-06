<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TaskAssignmentSeeder extends Seeder
{
    public function run(): void
    {
        // report_id => technician_id
        $assignments = [
            ['report_id' => 1, 'technician_id' => 2], // Budi → selesai
            ['report_id' => 2, 'technician_id' => 3], // Andi → sedang
            ['report_id' => 3, 'technician_id' => 4], // Riko → ditugaskan
            ['report_id' => 4, 'technician_id' => 2], // Budi → ditugaskan
            ['report_id' => 5, 'technician_id' => 3], // Andi → selesai
            ['report_id' => 6, 'technician_id' => 4], // Riko → sedang
        ];

        foreach ($assignments as $assignment) {
            DB::table('task_assignments')->insert([
                'report_id'      => $assignment['report_id'],
                'technician_id'  => $assignment['technician_id'],
                'assigned_at'    => now(),
            ]);
        }
    }
}