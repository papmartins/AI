<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder {
    public function run(): void {
        $maleFirstNames = ['John', 'Michael', 'David', 'Robert', 'James', 'William', 'Richard', 'Joseph', 'Thomas', 'Daniel', 'Charles', 'Matthew', 'Anthony', 'Donald', 'Mark', 'Paul', 'Steven', 'Andrew', 'Kenneth', 'Joshua'];
        $femaleFirstNames = ['Mary', 'Jennifer', 'Lisa', 'Sarah', 'Susan', 'Karen', 'Nancy', 'Betty', 'Margaret', 'Sandra', 'Ashley', 'Jessica', 'Elizabeth', 'Emily', 'Amanda', 'Melissa', 'Deborah', 'Stephanie', 'Rebecca', 'Laura'];
        $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Miller', 'Davis', 'Garcia', 'Rodriguez', 'Wilson', 'Martinez', 'Anderson', 'Taylor', 'Thomas', 'Hernandez', 'Moore', 'Martin', 'Jackson', 'Thompson', 'White'];
        
        $users = [];
        $now = now();
        
        $users[] = [
            'name' => 'Alice Smith',
            'email' => 'alice@email.com',
            'password' => Hash::make('123'),
            'gender' => 'F',
            'birth_date' => '1990-05-15',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        for ($i = 1; $i <= 50; $i++) {
            // Alternate between male and female
            $isMale = $i % 2 === 0;
            $firstNames = $isMale ? $maleFirstNames : $femaleFirstNames;
            $gender = $isMale ? 'M' : 'F';
            
            $firstName = $firstNames[array_rand($firstNames)];
            $lastName = $lastNames[array_rand($lastNames)];
            
            // Generate unique email
            $email = strtolower($firstName[0] . $lastName . $i) . '@email.com';
            
            $birthYear = rand(1955, 2025);
            $birthMonth = str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT);
            $birthDay = str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT);
            $birthDate = "$birthYear-$birthMonth-$birthDay";
            
            $users[] = [
                'name' => $firstName . ' ' . $lastName,
                'email' => $email,
                'password' => Hash::make('123'),
                'gender' => $gender,
                'birth_date' => $birthDate,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        
        // Add anomalous users for testing anomaly detection
        // User 1: High rental frequency user (will rent many movies quickly)
        $users[] = [
            'name' => 'Rapid Renter',
            'email' => 'rapid@email.com',
            'password' => Hash::make('123'),
            'gender' => 'M',
            'birth_date' => '1985-03-22',
            'created_at' => $now,
            'updated_at' => $now,
        ];
        
        // User 2: Inconsistent rater (will give very variable ratings)
        $users[] = [
            'name' => 'Mood Swinger',
            'email' => 'mood@email.com',
            'password' => Hash::make('123'),
            'gender' => 'F',
            'birth_date' => '1992-07-14',
            'created_at' => $now,
            'updated_at' => $now,
        ];
        
        // User 3: Frequent late returner (will return movies late)
        $users[] = [
            'name' => 'Late Larry',
            'email' => 'late@email.com',
            'password' => Hash::make('123'),
            'gender' => 'M',
            'birth_date' => '1978-11-30',
            'created_at' => $now,
            'updated_at' => $now,
        ];
        
        // User 4: Suspicious activity (combination of all bad behaviors)
        $users[] = [
            'name' => 'Suspicious Sam',
            'email' => 'suspicious@email.com',
            'password' => Hash::make('123'),
            'gender' => 'M',
            'birth_date' => '1988-09-05',
            'created_at' => $now,
            'updated_at' => $now,
        ];
        
        // Insert all users at once for better performance
        DB::table('users')->insert($users);
    }
}
