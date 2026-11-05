<?php

use App\Models\Central\Roles\Role;
use App\Models\Central\Audit\Activity;
use App\Models\User;
use Database\Seeders\Central\AuthorizationSeeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

function activityActor(array $permissions): User
{
    test()->seed(AuthorizationSeeder::class);
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('activity-'.Str::random(10), array_fill_keys($permissions, 7)));

    return $actor;
}

test('central activity readers can search without exposing raw private properties', function (): void {
    $actor = activityActor(['saas.activity.view']);
    activity('billing')->causedBy($actor)->event('invoice_issued')->withProperties(['password' => 'never-show-secret'])->log('Facture émise.');
    activity('shipping')->causedBy($actor)->event('carrier_updated')->log('Transporteur modifié.');
    $this->actingAs($actor, 'central')->get(route('central.activity.index', ['event' => 'invoice_issued']))
        ->assertOk()->assertSee('invoice_issued')->assertDontSee('carrier_updated')->assertDontSee('never-show-secret');
});

test('activity export is separately authorized and exports only filtered safe fields', function (): void {
    $reader = activityActor(['saas.activity.view']);
    $exporter = activityActor(['saas.activity.view', 'saas.activity.export']);
    activity('billing')->causedBy($exporter)->event('invoice_issued')->withProperties(['password' => 'never-export-secret'])->log('Facture émise.');
    activity('shipping')->causedBy($exporter)->event('carrier_updated')->log('Transporteur modifié.');
    $this->actingAs($reader, 'central')->get(route('central.activity.export', ['event' => 'invoice_issued']))->assertForbidden();
    $response = $this->actingAs($exporter, 'central')->get(route('central.activity.export', ['event' => 'invoice_issued']))->assertOk();
    $csv = $response->streamedContent();
    expect($csv)->toContain('invoice_issued')->not->toContain('carrier_updated')->not->toContain('never-export-secret');
    expect(Activity::where('event', 'central_activity_export_requested')->count())->toBe(1);
});

test('activity filters use public actor and subject identifiers and do not reveal unrelated records', function (): void {
    $actor = activityActor(['saas.activity.view', 'saas.activity.export']);
    $subject = User::factory()->create();
    $other = User::factory()->create();
    $target = activity('users')->causedBy($actor)->performedOn($subject)->event('target_event')->withProperties(['token' => 'secret-detail'])->log('Compte modifié.');
    activity('users')->causedBy($actor)->performedOn($other)->event('other_event')->log('Autre compte.');
    $this->actingAs($actor, 'central')->get(route('central.activity.index', ['actor_uuid' => $actor->uuid, 'subject_uuid' => $subject->uuid]))
        ->assertOk()->assertSee('target_event')->assertSee($subject->uuid)->assertDontSee('other_event');
    $this->get(route('central.activity.show', $target))->assertOk()->assertSee($subject->uuid)->assertDontSee('secret-detail');
    expect(Activity::where('event', 'central_activity_viewed')->count())->toBe(2);
    $csv = $this->get(route('central.activity.export', ['actor_uuid' => $actor->uuid, 'subject_uuid' => $subject->uuid]))->assertOk()->streamedContent();
    expect($csv)->toContain('target_event')->toContain($subject->uuid)->not->toContain('other_event');
});

test('csv export neutralizes spreadsheet formulas in audit fields', function (): void {
    $actor = activityActor(['saas.activity.export']);
    activity('billing')->causedBy($actor)->event('=1+1')->log('Activité.');
    $csv = $this->actingAs($actor, 'central')->get(route('central.activity.export', ['event' => '=1+1']))->assertOk()->streamedContent();
    expect($csv)->toContain("'=1+1");
});

test('csv export includes every matching record across batches', function (): void {
    $actor = activityActor(['saas.activity.export']);
    $rows = [];
    for ($i = 0; $i < 501; $i++) {
        $rows[] = ['uuid' => (string) Str::uuid(), 'correlation_id' => (string) Str::uuid(), 'origin' => 1,
            'log_name' => 'bulk', 'event' => 'bulk_marker', 'description' => 'Activité', 'created_at' => now(), 'updated_at' => now()];
    }
    DB::table('activity_log')->insert($rows);
    $csv = $this->actingAs($actor, 'central')->get(route('central.activity.export', ['event' => 'bulk_marker']))->assertOk()->streamedContent();
    expect(substr_count($csv, 'bulk_marker'))->toBe(501)->and($csv)->not->toContain('central_activity_export_requested');
});

test('csv export uses separate spreadsheet columns and preserves delimiters within values', function (): void {
    $actor = activityActor(['saas.activity.export']);
    $subject = User::factory()->create();
    $entry = activity('journal;test')->causedBy($actor)->performedOn($subject)->event('event;test')->log('Activité.');
    $csv = $this->actingAs($actor, 'central')->get(route('central.activity.export', ['event' => 'event;test']))->assertOk()->streamedContent();
    expect(substr($csv, 0, 3))->toBe("\xEF\xBB\xBF");
    $stream = fopen('php://temp', 'w+');
    fwrite($stream, substr($csv, 3));
    rewind($stream);
    $header = fgetcsv($stream, 0, ';', '"', '');
    $row = fgetcsv($stream, 0, ';', '"', '');
    fclose($stream);
    expect($header)->toBe(['uuid', 'date_utc', 'journal', 'evenement', 'origine', 'acteur_uuid', 'sujet_uuid', 'correlation_uuid'])
        ->and($row)->toHaveCount(8)->and($row[0])->toBe($entry->uuid)
        ->and($row[2])->toBe('journal;test')->and($row[3])->toBe('event;test')
        ->and($row[5])->toBe($actor->uuid)->and($row[6])->toBe($subject->uuid);
});
