<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Member;

/**
 * Seeds the `members` table with the 8 mock members from the frontend.
 *
 * Source: gym-management-frontend/src/data/mockData.js → initialMembers
 *
 * These are the same records the React app shows on first load.
 * Once a real backend is in use, this seeder can be replaced with
 * a factory-based approach or removed entirely.
 */
class MemberSeeder extends Seeder
{
    public function run(): void
    {
        $members = [
            [
                'full_name'               => 'Dilani Fernando',
                'email'                   => 'dilani.fernando@example.com',
                'phone'                   => '077 210 4432',
                'dob'                     => '1994-03-12',
                'gender'                  => 'Female',
                'address'                 => '24 Galle Road, Colombo 03',
                'emergency_contact_name'  => 'Nuwan Fernando',
                'emergency_contact_phone' => '077 210 9981',
                'join_date'               => '2025-02-11',
                'status'                  => 'Active',
                'notes'                   => 'Prefers evening classes.',
            ],
            [
                'full_name'               => 'Kasun Wickramasinghe',
                'email'                   => 'kasun.w@example.com',
                'phone'                   => '071 556 2210',
                'dob'                     => '1990-07-02',
                'gender'                  => 'Male',
                'address'                 => '112 High Level Road, Nugegoda',
                'emergency_contact_name'  => 'Sanduni Wickramasinghe',
                'emergency_contact_phone' => '071 556 9012',
                'join_date'               => '2024-11-04',
                'status'                  => 'Active',
                'notes'                   => '',
            ],
            [
                'full_name'               => 'Ishara Perera',
                'email'                   => 'ishara.perera@example.com',
                'phone'                   => '070 812 3345',
                'dob'                     => '1998-01-27',
                'gender'                  => 'Female',
                'address'                 => '9 Station Road, Mount Lavinia',
                'emergency_contact_name'  => 'Chamari Perera',
                'emergency_contact_phone' => '070 812 7788',
                'join_date'               => '2025-05-19',
                'status'                  => 'Active',
                'notes'                   => 'Training for a half marathon.',
            ],
            [
                'full_name'               => 'Tharindu Jayasuriya',
                'email'                   => 'tharindu.j@example.com',
                'phone'                   => '076 445 1120',
                'dob'                     => '1987-09-15',
                'gender'                  => 'Male',
                'address'                 => '56 Kandy Road, Kadawatha',
                'emergency_contact_name'  => 'Ruwan Jayasuriya',
                'emergency_contact_phone' => '076 445 7723',
                'join_date'               => '2023-06-30',
                'status'                  => 'Suspended',
                'notes'                   => 'Suspended pending outstanding balance.',
            ],
            [
                'full_name'               => 'Nadeesha Rathnayake',
                'email'                   => 'nadeesha.r@example.com',
                'phone'                   => '072 990 6612',
                'dob'                     => '1996-12-08',
                'gender'                  => 'Female',
                'address'                 => '3 Lake Drive, Rajagiriya',
                'emergency_contact_name'  => 'Sampath Rathnayake',
                'emergency_contact_phone' => '072 990 1145',
                'join_date'               => '2025-08-01',
                'status'                  => 'Active',
                'notes'                   => '',
            ],
            [
                'full_name'               => 'Ashan Gunasekara',
                'email'                   => 'ashan.g@example.com',
                'phone'                   => '075 330 4471',
                'dob'                     => '1993-04-21',
                'gender'                  => 'Male',
                'address'                 => '78 Havelock Road, Colombo 05',
                'emergency_contact_name'  => 'Malki Gunasekara',
                'emergency_contact_phone' => '075 330 8890',
                'join_date'               => '2024-02-17',
                'status'                  => 'Inactive',
                'notes'                   => 'On a travel break, expected back in October.',
            ],
            [
                'full_name'               => 'Hasini Silva',
                'email'                   => 'hasini.silva@example.com',
                'phone'                   => '078 604 2298',
                'dob'                     => '2000-10-30',
                'gender'                  => 'Female',
                'address'                 => '15 Ward Place, Colombo 07',
                'emergency_contact_name'  => 'Priyantha Silva',
                'emergency_contact_phone' => '078 604 5567',
                'join_date'               => '2025-07-22',
                'status'                  => 'Active',
                'notes'                   => '',
            ],
            [
                'full_name'               => 'Ruwan Bandara',
                'email'                   => 'ruwan.bandara@example.com',
                'phone'                   => '071 223 8890',
                'dob'                     => '1989-05-05',
                'gender'                  => 'Male',
                'address'                 => '210 Negombo Road, Wattala',
                'emergency_contact_name'  => 'Iresha Bandara',
                'emergency_contact_phone' => '071 223 4432',
                'join_date'               => '2024-09-09',
                'status'                  => 'Active',
                'notes'                   => 'Strength-training focus, works with a coach on Tuesdays.',
            ],
        ];

        foreach ($members as $data) {
            Member::create($data);
        }
    }
}
