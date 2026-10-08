<?php

namespace Tests\Feature;

use App\Models\Checkpoint;
use App\Models\Guard;
use App\Models\IncidentReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolved_incident_reports_are_read_only_until_reopened(): void
    {
        $supervisor = User::factory()->create(['role' => 'admin']);
        $incident = $this->createIncidentReport([
            'status' => 'resolved',
            'admin_notes' => 'Original admin note.',
            'action_taken' => 'Original response completed.',
            'resolved_at' => now(),
        ]);

        $this
            ->actingAs($supervisor)
            ->patch(route('incidents.update', $incident), [
                'status' => 'under_review',
                'admin_notes' => 'Changed note.',
                'action_taken' => 'Changed action.',
            ])
            ->assertRedirect(route('incidents.index'))
            ->assertSessionHas('status', 'Resolved incident reports are read-only. Reopen the report before making changes.');

        $incident->refresh();

        $this->assertSame('resolved', $incident->status);
        $this->assertSame('Original admin note.', $incident->admin_notes);
        $this->assertSame('Original response completed.', $incident->action_taken);
        $this->assertNotNull($incident->resolved_at);
    }

    public function test_supervisor_can_reopen_resolved_incident_report(): void
    {
        $supervisor = User::factory()->create(['role' => 'admin']);
        $incident = $this->createIncidentReport([
            'status' => 'resolved',
            'admin_notes' => 'Original admin note.',
            'action_taken' => 'Original response completed.',
            'resolved_at' => now(),
        ]);

        $this
            ->actingAs($supervisor)
            ->patch(route('incidents.update', $incident), [
                'review_action' => 'reopen',
            ])
            ->assertRedirect(route('incidents.index'))
            ->assertSessionHas('status', 'Incident report reopened for review.');

        $incident->refresh();

        $this->assertSame('under_review', $incident->status);
        $this->assertSame('Original admin note.', $incident->admin_notes);
        $this->assertSame('Original response completed.', $incident->action_taken);
        $this->assertNull($incident->resolved_at);
    }

    public function test_resolved_incident_page_shows_read_only_reopen_control(): void
    {
        $supervisor = User::factory()->create(['role' => 'admin']);
        $this->createIncidentReport([
            'status' => 'resolved',
            'admin_notes' => 'Reviewed and verified.',
            'action_taken' => 'Area secured.',
            'resolved_at' => now(),
        ]);

        $this
            ->actingAs($supervisor)
            ->get(route('incidents.index'))
            ->assertOk()
            ->assertSee('Resolved report is read-only')
            ->assertSee('Reopen Report')
            ->assertSee('Reviewed and verified.')
            ->assertSee('Area secured.');
    }

    private function createIncidentReport(array $overrides = []): IncidentReport
    {
        $guard = Guard::create([
            'employee_no' => 'BCP-REVIEW-01',
            'name' => 'Review Guard',
            'email' => 'review.guard@example.com',
            'rfid_uid' => 'RFID-REVIEW-01',
            'status' => 'active',
        ]);

        $checkpoint = Checkpoint::create([
            'code' => 'CP-REVIEW-01',
            'name' => 'Review Checkpoint',
            'location' => 'Review Area',
            'device_uid' => 'ESP32-REVIEW-01',
            'status' => 'active',
        ]);

        return IncidentReport::create([
            'guard_id' => $guard->id,
            'checkpoint_id' => $checkpoint->id,
            'title' => 'Review Incident',
            'incident_type' => 'Unauthorized Entry / Trespassing',
            'category' => 'Unauthorized Entry / Trespassing',
            'priority' => 'high',
            'severity' => 'high',
            'location' => 'Review Area',
            'incident_at' => now(),
            'reported_at' => now(),
            'description' => 'Incident for review workflow testing.',
            'status' => 'submitted',
            ...$overrides,
        ]);
    }
}
