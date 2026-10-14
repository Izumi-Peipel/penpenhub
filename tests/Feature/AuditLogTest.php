<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_user_ip_and_meta(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $this->actingAs($user);

        $log = AuditLogger::log('thing.happened', $company, ['k' => 'v'], $company->id);

        $this->assertSame($user->id, $log->user_id);
        $this->assertNotNull($log->ip);
        $this->assertSame(['k' => 'v'], $log->meta);
        $this->assertSame(Company::class, $log->subject_type);
        $this->assertNotNull($log->created_at);
    }

    public function test_log_cannot_be_updated(): void
    {
        $log = AuditLogger::log('a');

        $this->expectException(LogicException::class);
        $log->update(['action' => 'b']);
    }

    public function test_log_cannot_be_deleted(): void
    {
        $log = AuditLogger::log('a');

        $this->expectException(LogicException::class);
        $log->delete();
    }
}
