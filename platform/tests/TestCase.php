<?php

namespace Tests;

use App\Models\Client;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        File::ensureDirectoryExists(config('attachments.root'));
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path('storage/framework/testing/attachments'));
        parent::tearDown();
    }

    protected function demo(): void
    {
        $this->seed(DemoSeeder::class);
        auth()->logout();
    }

    protected function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    protected function lawyer(): User
    {
        return $this->user('lawyer@example.test');
    }

    protected function client(string $name): Client
    {
        return Client::where('name', 'like', $name.'%')->firstOrFail();
    }
}
