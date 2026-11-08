<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    use RefreshDatabase;

    protected string $backupDir;
    protected User $superAdmin;
    protected User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backupDir = storage_path('app/backup');
        if (!File::isDirectory($this->backupDir)) {
            File::makeDirectory($this->backupDir, 0755, true);
        }

        $this->superAdmin = User::factory()->create([
            'role' => 'super administrator',
        ]);

        $this->staff = User::factory()->create([
            'role' => 'staff',
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up test backup files
        if (File::isDirectory($this->backupDir)) {
            $files = File::files($this->backupDir);
            foreach ($files as $file) {
                if (str_contains($file->getFilename(), 'test-')) {
                    File::delete($file->getRealPath());
                }
            }
        }

        parent::tearDown();
    }

    public function test_artisan_database_backup_command_creates_backup_and_logs_activity(): void
    {
        $exitCode = Artisan::call('app:database-back-up', [
            '--filename' => 'test-artisan-run',
        ]);

        $this->assertEquals(0, $exitCode);

        // Verify MySQL .sql or .sql.gz file was created in storage/app/backup
        $expectedSqlGz = $this->backupDir . DIRECTORY_SEPARATOR . 'test-artisan-run.sql.gz';
        $expectedSql = $this->backupDir . DIRECTORY_SEPARATOR . 'test-artisan-run.sql';

        $exists = File::exists($expectedSqlGz) || File::exists($expectedSql);

        $this->assertTrue($exists, 'MySQL backup file must exist in storage/app/backup');

        // Verify ActivityLog was created
        $log = ActivityLog::where('actor_type', 'system')
            ->where('description', 'like', '%test-artisan-run%')
            ->first();

        $this->assertNotNull($log, 'An ActivityLog entry must be recorded when backup runs');
    }

    public function test_admin_can_view_backups_inertia_page(): void
    {
        $dummyPath = $this->backupDir . DIRECTORY_SEPARATOR . 'test-snapshot-inertia.sql.gz';
        File::put($dummyPath, 'dummy-inertia-content');

        $response = $this->actingAs($this->superAdmin)->get(route('admin.backups.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Backups/Index')
            ->has('backups')
        );

        File::delete($dummyPath);
    }

    public function test_admin_can_view_backups_list_json(): void
    {
        // Create dummy backup file
        $dummyPath = $this->backupDir . DIRECTORY_SEPARATOR . 'test-snapshot-123.sql.gz';
        File::put($dummyPath, 'dummy-compressed-content');

        $response = $this->actingAs($this->superAdmin)->getJson(route('admin.backups.index'));

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'name' => 'test-snapshot-123.sql.gz',
        ]);

        File::delete($dummyPath);
    }

    public function test_non_admin_cannot_access_backups(): void
    {
        $response = $this->actingAs($this->staff)->getJson(route('admin.backups.index'));
        $response->assertStatus(403);
    }

    public function test_admin_can_trigger_manual_backup_via_controller(): void
    {
        $response = $this->actingAs($this->superAdmin)->post(route('admin.backups.store'));

        $response->assertSessionHas('success');
    }

    public function test_admin_can_download_backup(): void
    {
        $dummyPath = $this->backupDir . DIRECTORY_SEPARATOR . 'test-download-file.sql.gz';
        File::put($dummyPath, 'test-download-content');

        // Test path param
        $response = $this->actingAs($this->superAdmin)->get(route('admin.backups.download', 'test-download-file.sql.gz'));
        $response->assertStatus(200);
        $this->assertEquals('attachment; filename=test-download-file.sql.gz', $response->headers->get('content-disposition'));

        // Test query param
        $responseQuery = $this->actingAs($this->superAdmin)->get(route('admin.backups.download') . '?filename=test-download-file.sql.gz');
        $responseQuery->assertStatus(200);
        $this->assertEquals('attachment; filename=test-download-file.sql.gz', $responseQuery->headers->get('content-disposition'));

        File::delete($dummyPath);
    }

    public function test_admin_can_delete_backup(): void
    {
        $dummyPath = $this->backupDir . DIRECTORY_SEPARATOR . 'test-delete-file.sql.gz';
        File::put($dummyPath, 'delete-me');

        $this->assertTrue(File::exists($dummyPath));

        $response = $this->actingAs($this->superAdmin)->delete(route('admin.backups.destroy', 'test-delete-file.sql.gz'));

        $response->assertSessionHas('success');
        $this->assertFalse(File::exists($dummyPath), 'File should be removed from disk');
    }
}

