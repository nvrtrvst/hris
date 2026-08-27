<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AnnouncementUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('superadmin');

        return $admin;
    }

    public function test_store_dengan_file_lampiran_pdf(): void
    {
        Storage::fake('public');

        $pdf = UploadedFile::fake()->create('brosur.pdf', 200, 'application/pdf');

        $response = $this->actingAs($this->admin())->post(route('pengumuman.store'), [
            'title' => 'Pengumuman File',
            'body' => 'Isi',
            'published_at' => now()->format('Y-m-d H:i:s'),
            'file' => $pdf,
        ]);

        $response->assertRedirect();
        $ann = Announcement::latest()->first();
        $this->assertNotNull($ann->file);
        $this->assertNull($ann->image);
        Storage::disk('public')->assertExists($ann->file);
    }

    public function test_store_dengan_gambar(): void
    {
        Storage::fake('public');

        $img = UploadedFile::fake()->image('foto.jpg');

        $response = $this->actingAs($this->admin())->post(route('pengumuman.store'), [
            'title' => 'Pengumuman Gambar',
            'body' => 'Isi',
            'published_at' => now()->format('Y-m-d H:i:s'),
            'image' => $img,
        ]);

        $response->assertRedirect();
        $ann = Announcement::latest()->first();
        $this->assertNotNull($ann->image);
        Storage::disk('public')->assertExists($ann->image);
    }
}
