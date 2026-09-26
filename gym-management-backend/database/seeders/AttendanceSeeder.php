<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Attendance;
use App\Models\Member;

/**
 * Seeds the `attendance` table with the 14 mock attendance records from the frontend.
 *
 * Source: gym-management-frontend/src/data/mockData.js → initialAttendance
 *
 * Members are resolved by email to get the correct member_id FK.
 * '—' placeholders used in the frontend for absent records are stored as null.
 */
class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $memberId = fn(string $email) => Member::where('email', $email)->value('id');

        $records = [
            // --- 2026-09-03 ---
            [
                'member_id'      => $memberId('dilani.fernando@example.com'),
                'member_name'    => 'Dilani Fernando',
                'email'          => 'dilani.fernando@example.com',
                'date'           => '2026-09-03',
                'check_in_time'  => '06:30 AM',
                'check_out_time' => '07:45 AM',
                'status'         => 'Present',
            ],
            [
                'member_id'      => $memberId('kasun.w@example.com'),
                'member_name'    => 'Kasun Wickramasinghe',
                'email'          => 'kasun.w@example.com',
                'date'           => '2026-09-03',
                'check_in_time'  => '07:15 AM',
                'check_out_time' => '08:30 AM',
                'status'         => 'Present',
            ],
            [
                'member_id'      => $memberId('ishara.perera@example.com'),
                'member_name'    => 'Ishara Perera',
                'email'          => 'ishara.perera@example.com',
                'date'           => '2026-09-03',
                'check_in_time'  => '08:00 AM',
                'check_out_time' => '09:15 AM',
                'status'         => 'Present',
            ],
            [
                'member_id'      => $memberId('nadeesha.r@example.com'),
                'member_name'    => 'Nadeesha Rathnayake',
                'email'          => 'nadeesha.r@example.com',
                'date'           => '2026-09-03',
                'check_in_time'  => '05:45 PM',
                'check_out_time' => '07:00 PM',
                'status'         => 'Present',
            ],
            [
                'member_id'      => $memberId('hasini.silva@example.com'),
                'member_name'    => 'Hasini Silva',
                'email'          => 'hasini.silva@example.com',
                'date'           => '2026-09-03',
                'check_in_time'  => '06:00 PM',
                'check_out_time' => '07:15 PM',
                'status'         => 'Present',
            ],
            [
                'member_id'      => $memberId('ruwan.bandara@example.com'),
                'member_name'    => 'Ruwan Bandara',
                'email'          => 'ruwan.bandara@example.com',
                'date'           => '2026-09-03',
                'check_in_time'  => '06:15 PM',
                'check_out_time' => '08:00 PM',
                'status'         => 'Present',
            ],
            // --- 2026-09-02 ---
            [
                'member_id'      => $memberId('dilani.fernando@example.com'),
                'member_name'    => 'Dilani Fernando',
                'email'          => 'dilani.fernando@example.com',
                'date'           => '2026-09-02',
                'check_in_time'  => '06:30 AM',
                'check_out_time' => '07:50 AM',
                'status'         => 'Present',
            ],
            [
                'member_id'      => $memberId('kasun.w@example.com'),
                'member_name'    => 'Kasun Wickramasinghe',
                'email'          => 'kasun.w@example.com',
                'date'           => '2026-09-02',
                'check_in_time'  => '07:30 AM',
                'check_out_time' => '08:45 AM',
                'status'         => 'Present',
            ],
            [
                'member_id'      => $memberId('ishara.perera@example.com'),
                'member_name'    => 'Ishara Perera',
                'email'          => 'ishara.perera@example.com',
                'date'           => '2026-09-02',
                'check_in_time'  => '08:15 AM',
                'check_out_time' => '09:30 AM',
                'status'         => 'Present',
            ],
            [
                // Absent record — frontend showed '—', stored as null here
                'member_id'      => $memberId('tharindu.j@example.com'),
                'member_name'    => 'Tharindu Jayasuriya',
                'email'          => 'tharindu.j@example.com',
                'date'           => '2026-09-02',
                'check_in_time'  => null,
                'check_out_time' => null,
                'status'         => 'Absent',
            ],
            [
                'member_id'      => $memberId('nadeesha.r@example.com'),
                'member_name'    => 'Nadeesha Rathnayake',
                'email'          => 'nadeesha.r@example.com',
                'date'           => '2026-09-02',
                'check_in_time'  => '06:00 PM',
                'check_out_time' => '07:15 PM',
                'status'         => 'Present',
            ],
            [
                'member_id'      => $memberId('ruwan.bandara@example.com'),
                'member_name'    => 'Ruwan Bandara',
                'email'          => 'ruwan.bandara@example.com',
                'date'           => '2026-09-02',
                'check_in_time'  => '07:00 PM',
                'check_out_time' => '08:30 PM',
                'status'         => 'Late',
            ],
            // --- 2026-09-01 ---
            [
                'member_id'      => $memberId('dilani.fernando@example.com'),
                'member_name'    => 'Dilani Fernando',
                'email'          => 'dilani.fernando@example.com',
                'date'           => '2026-09-01',
                'check_in_time'  => '06:45 AM',
                'check_out_time' => '08:00 AM',
                'status'         => 'Present',
            ],
            [
                'member_id'      => $memberId('hasini.silva@example.com'),
                'member_name'    => 'Hasini Silva',
                'email'          => 'hasini.silva@example.com',
                'date'           => '2026-09-01',
                'check_in_time'  => '06:10 PM',
                'check_out_time' => '07:20 PM',
                'status'         => 'Present',
            ],
        ];

        foreach ($records as $data) {
            Attendance::create($data);
        }
    }
}
