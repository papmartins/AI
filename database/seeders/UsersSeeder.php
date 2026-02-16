<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder {
    public function run(): void {
        $users = [
            ['name' => 'John Doe', 'email' => 'john@email.com', 'password' => Hash::make('123')],
            ['name' => 'Jane Smith', 'email' => 'jane@email.com', 'password' => Hash::make('123')],
            ['name' => 'Bob Johnson', 'email' => 'bob@email.com', 'password' => Hash::make('123')],
            ['name' => 'Alice Brown', 'email' => 'alice@email.com', 'password' => Hash::make('123')],
        ];
        
        foreach ($users as $user) {
            $user['created_at'] = $user['updated_at'] = now();
            DB::table('users')->insert($user);
        }
    }
}
