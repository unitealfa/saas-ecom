<?php

use App\Enums\MediaVisibilityEnum;
use App\Models\Central\Audit\Activity;
use App\Models\Central\Media\Media;
use App\Models\Central\Permissions\Permission;
use App\Models\Central\Roles\Role;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\User;
use App\Services\Central\Media\CentralMediaService;
use App\Services\Central\Users\ProfilePhotoService;
use Database\Factories\Central\MediaFactory;
use Database\Seeders\Central\AuthorizationSeeder;
use Database\Seeders\Central\MediaPermissionSeeder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Storage::set('local', Storage::fake('central-media-management-'.getmypid()));
    $this->seed(AuthorizationSeeder::class);
});

function centralMediaAdmin(): User
{
    $actor = User::factory()->create();
    $actor->assignRole(Role::findByName('root', 'central'));

    return $actor;
}

function centralMediaStaff(string $permission): User
{
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('media-'.Str::random(12), [$permission => 7]));

    return $actor;
}

function centralMediaPdf(string $name = 'document.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, MediaFactory::PDF_CONTENT);
}

function centralMediaDownloadUrl(Media $media): string
{
    return URL::temporarySignedRoute('central.media.download', now()->addMinutes(5), ['media' => $media], absolute: false);
}

test('media seeder adds exactly four stable permissions without changing assignments', function (): void {
    $before = DB::table('model_has_permissions')->count();
    $this->seed(MediaPermissionSeeder::class);
    $ids = Permission::where('name', 'like', 'saas.media.%')->pluck('uuid', 'name')->all();
    $this->seed(MediaPermissionSeeder::class);
    expect($ids)->toHaveCount(4)
        ->and(Permission::where('name', 'like', 'saas.media.%')->pluck('uuid', 'name')->all())->toBe($ids)
        ->and(DB::table('model_has_permissions')->count())->toBe($before);
});

test('media permissions are independent', function (): void {
    $media = Media::factory()->create();
    $subjects = ['viewAny' => Media::class, 'upload' => Media::class, 'download' => $media, 'archive' => $media];
    foreach (['view' => 'viewAny', 'upload' => 'upload', 'download' => 'download', 'archive' => 'archive'] as $permission => $ability) {
        $actor = centralMediaStaff('saas.media.'.$permission);
        foreach ($subjects as $candidate => $subject) {
            expect(Gate::forUser($actor)->allows($candidate, $subject))->toBe($candidate === $ability);
        }
    }
});

test('all media endpoints deny staff without permission', function (): void {
    $media = Media::factory()->create();
    $this->actingAs(centralMediaStaff('saas.users.view'), 'central');
    $this->get(route('central.media.index'))->assertForbidden();
    $this->get(route('central.media.create'))->assertForbidden();
    $this->get(route('central.media.show', $media))->assertForbidden();
    $this->get(centralMediaDownloadUrl($media))->assertForbidden();
    $this->post(route('central.media.store'), ['file' => centralMediaPdf()])->assertForbidden();
    $this->delete(route('central.media.archive', $media), ['expected_state' => $media->managementState()])->assertForbidden();
});

test('media reader sees menu metadata and archives without paths or mutation buttons', function (): void {
    $media = Media::factory()->create();
    $this->actingAs(centralMediaStaff('saas.media.view'), 'central')
        ->get(route('central.media.index'))->assertOk()->assertSee('Fichiers centraux')
        ->assertSee($media->original_name)->assertDontSee('Importer un fichier')->assertDontSee('>Archiver<', false);
    $this->get(route('central.media.show', $media))->assertOk()
        ->assertDontSee($media->storage_key)->assertDontSee($media->file_hash)
        ->assertDontSee('Télécharger le fichier privé');
    $this->get(route('central.media.show', $media->id))->assertNotFound();
});

test('media upload records verified private metadata and returns writers without view to dashboard', function (string $type): void {
    $actor = centralMediaStaff('saas.media.upload');
    $file = $type === 'pdf' ? centralMediaPdf() : UploadedFile::fake()->image('image.'.$type, 32, 24);
    $contents = file_get_contents($file->getPathname());
    $this->actingAs($actor, 'central')->get(route('central.media.create'))->assertOk()->assertSee('name="file"', false);
    $this->post(route('central.media.store'), ['file' => $file])
        ->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
    $media = Media::sole();
    expect($media->visibility)->toBe(MediaVisibilityEnum::PRIVATE)
        ->and($media->model_type)->toBe('central_user')->and($media->model_id)->toBe($actor->id)
        ->and($media->created_by_id)->toBe($actor->id)
        ->and($media->is_primary)->toBeFalse()
        ->and($media->file_hash)->toBe(hash('sha256', $contents));
    Storage::disk('local')->assertExists($media->storage_key);
    expect(Activity::forSubject($media)->where('event', 'central_media_uploaded')->count())->toBe(1);
})->with(['pdf', 'jpg', 'png', 'webp']);

test('media upload refuses executable svg disguised content oversized and broken image files', function (): void {
    $this->actingAs(centralMediaAdmin(), 'central');
    foreach ([
        UploadedFile::fake()->createWithContent('script.php', '<?php echo "danger";'),
        UploadedFile::fake()->createWithContent('script.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        UploadedFile::fake()->createWithContent('fake.pdf', 'not a PDF'),
        UploadedFile::fake()->createWithContent('wrong.jpg', MediaFactory::PDF_CONTENT),
        UploadedFile::fake()->create('too-large.pdf', CentralMediaService::MAX_SIZE_KB + 1, 'application/pdf'),
        UploadedFile::fake()->image('too-wide.png', CentralMediaService::MAX_DIMENSION + 1, 1),
        UploadedFile::fake()->createWithContent('truncated.jpg', "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00"),
    ] as $file) {
        $this->post(route('central.media.store'), ['file' => $file])->assertSessionHasErrors('file');
    }
    expect(Media::count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('media upload forbids client supplied parent location visibility or hashes', function (): void {
    $this->actingAs(centralMediaAdmin(), 'central')
        ->post(route('central.media.store'), ['file' => centralMediaPdf(), 'disk' => 'public',
            'storage_key' => '../../secret', 'model_id' => 99, 'visibility' => 1, 'file_hash' => 'invented'])
        ->assertSessionHasErrors(['disk', 'storage_key', 'model_id', 'visibility', 'file_hash']);
    expect(Media::count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('media download requires both a valid signature and a current permission', function (): void {
    $actor = centralMediaAdmin();
    $media = app(CentralMediaService::class)->upload($actor, centralMediaPdf('reçu.pdf'));
    $this->actingAs($actor, 'central')->get(route('central.media.show', $media))->assertOk()->assertSee('Télécharger le fichier privé');
    $this->get(route('central.media.download', $media))->assertForbidden();
    $url = centralMediaDownloadUrl($media);
    $this->get($url.'&changed=1')->assertForbidden();
    $response = $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertDownload();
    expect($response->streamedContent())->toBe(MediaFactory::PDF_CONTENT);
    expect($response->headers->get('Cache-Control'))->toContain('private')->toContain('no-store');
    expect(Activity::forSubject($media)->where('event', 'central_media_download_authorized')->count())->toBe(1);
    $this->actingAs(centralMediaStaff('saas.users.view'), 'central')->get($url)->assertForbidden();
    $this->actingAs($actor, 'central');
    $this->travel(6)->minutes();
    $this->get($url)->assertForbidden();
});

test('media download refuses a changed hash missing object public file or unsafe storage location', function (): void {
    $actor = centralMediaAdmin();
    $this->actingAs($actor, 'central');
    $media = app(CentralMediaService::class)->upload($actor, centralMediaPdf());
    Storage::disk('local')->put($media->storage_key, 'modified');
    $this->get(centralMediaDownloadUrl($media))->assertStatus(409);
    Storage::disk('local')->delete($media->storage_key);
    $this->get(centralMediaDownloadUrl($media))->assertNotFound();
    $media->forceFill(['visibility' => MediaVisibilityEnum::PUBLIC])->save();
    $this->get(centralMediaDownloadUrl($media))->assertForbidden();
    $media->forceFill(['visibility' => MediaVisibilityEnum::PRIVATE, 'storage_key' => '../outside.pdf'])->save();
    $this->get(centralMediaDownloadUrl($media))->assertNotFound();
    $media->forceFill(['storage_key' => 'central/private/safe.pdf', 'disk' => 'public'])->save();
    $this->get(centralMediaDownloadUrl($media))->assertNotFound();
});

test('existing private demonstration PDFs keep working without becoming archivable', function (): void {
    $media = Media::factory()->create(['model_type' => 'saas_invoice', 'collection_name' => 'local-fixtures',
        'storage_key' => 'local-fixtures/'.Str::uuid().'.pdf']);
    Storage::disk('local')->put($media->storage_key, MediaFactory::PDF_CONTENT);
    $this->actingAs(centralMediaAdmin(), 'central');
    $response = $this->get(centralMediaDownloadUrl($media))->assertOk()->assertDownload();
    expect($response->streamedContent())->toBe(MediaFactory::PDF_CONTENT);
    $this->delete(route('central.media.archive', $media), ['expected_state' => $media->managementState()])->assertForbidden();
});

test('media catalogue does not bypass existing profile photo authorization or removal rules', function (): void {
    $owner = User::factory()->create();
    app(ProfilePhotoService::class)->update($owner, UploadedFile::fake()->image('portrait.png', 16, 16));
    $photo = Media::sole();
    $this->actingAs(centralMediaStaff('saas.media.download'), 'central')
        ->get(centralMediaDownloadUrl($photo))->assertForbidden();
    $this->actingAs(centralMediaAdmin(), 'central')
        ->delete(route('central.media.archive', $photo), ['expected_state' => $photo->managementState()])->assertForbidden();
    $response = $this->get(centralMediaDownloadUrl($photo))->assertOk();
    expect($response->streamedContent())->toBe(Storage::disk('local')->get($photo->storage_key));
    expect($photo->refresh()->trashed())->toBeFalse();
});

test('media archive is logical preserves bytes and refuses obsolete forms', function (): void {
    $actor = centralMediaAdmin();
    $media = app(CentralMediaService::class)->upload($actor, centralMediaPdf());
    $original = $media->managementState();
    $media->forceFill(['file_hash' => str_repeat('a', 64)])->save();
    $this->actingAs($actor, 'central')->delete(route('central.media.archive', $media), ['expected_state' => $original])->assertSessionHasErrors('conflict');
    $this->delete(route('central.media.archive', $media), ['expected_state' => $media->managementState()])->assertSessionHasNoErrors();
    expect($media->refresh()->trashed())->toBeTrue();
    Storage::disk('local')->assertExists($media->storage_key);
    $this->get(route('central.media.show', $media))->assertOk()->assertSee('Archivé')->assertDontSee('Télécharger le fichier privé');
    $this->get(route('central.media.index', ['archived' => true]))->assertOk()->assertSee($media->original_name);
    $this->get(centralMediaDownloadUrl($media))->assertNotFound();
    expect(Activity::forSubject($media)->where('event', 'central_media_archived')->count())->toBe(1);
});

test('media archive protects business attachments even for root and preserves audit on rollback', function (): void {
    $actor = centralMediaAdmin();
    $business = Media::factory()->create(['model_type' => 'saas_invoice', 'collection_name' => 'invoice']);
    $this->actingAs($actor, 'central')->delete(route('central.media.archive', $business), ['expected_state' => $business->managementState()])->assertForbidden();
    $media = app(CentralMediaService::class)->upload($actor, centralMediaPdf());
    $before = Activity::count();
    expect(fn () => DB::transaction(function () use ($actor, $media): void {
        app(CentralMediaService::class)->archive($actor, $media, $media->managementState());
        throw new RuntimeException('Rollback attendu');
    }))->toThrow(RuntimeException::class, 'Rollback attendu');
    expect($media->refresh()->trashed())->toBeFalse()->and(Activity::count())->toBe($before);
    Storage::disk('local')->assertExists($media->storage_key);
});

test('a document FK prevents archival even if its media still has the administrative collection', function (): void {
    $actor = centralMediaAdmin();
    $media = app(CentralMediaService::class)->upload($actor, centralMediaPdf());
    $subscription = Subscription::factory()->create(['user_id' => $actor->id]);
    $periodEnd = now()->addMonth();
    $installment = DB::table('subscription_installments')->insertGetId([
        'uuid' => (string) Str::uuid(), 'user_id' => $actor->id,
        'subscription_id' => $subscription->id, 'operation_key' => 'test:installment:'.Str::uuid(),
        'installment_number' => 'TEST-1', 'period_starts_at' => now(), 'period_ends_at' => $periodEnd,
        'installment_amount' => 0, 'due_at' => $periodEnd, 'installment_status' => 1,
    ]);
    $rule = DB::table('saas_billing_settings')->insertGetId([
        'uuid' => (string) Str::uuid(), 'operation_key' => 'saas:rule:test-'.Str::uuid(),
        'record_type' => 2, 'code' => 'media-archive-test', 'version' => 1,
        'trigger_event' => 'test', 'numbering_scope' => 'saas_issuer',
        'parameters' => '{}', 'policy_status' => 1, 'correlation_id' => (string) Str::uuid(),
    ]);
    $document = DB::table('saas_invoices')->insertGetId([
        'uuid' => (string) Str::uuid(), 'user_id' => $actor->id, 'subscription_id' => $subscription->id,
        'installment_id' => $installment, 'billing_rule_id' => $rule, 'document_type' => 1,
        'document_media_id' => $media->id, 'operation_key' => 'saas:invoice:test-'.Str::uuid(),
        'billing_rule_snapshot' => '{}', 'net_amount' => 0, 'tax_amount' => 0, 'total_amount' => 0,
        'taxes' => '[]', 'currency' => 'DZD', 'status' => 1, 'period_starts_at' => now(),
        'period_ends_at' => $periodEnd, 'due_at' => $periodEnd,
        'saas_identity_snapshot' => '{}', 'customer_identity_snapshot' => '{}',
    ]);
    $this->actingAs($actor, 'central')
        ->delete(route('central.media.archive', $media), ['expected_state' => $media->managementState()])
        ->assertSessionHasErrors('file');
    expect($media->refresh()->trashed())->toBeFalse()
        ->and(DB::table('saas_invoices')->where('id', $document)->value('document_media_id'))->toBe($media->id)
        ->and(Activity::forSubject($media)->where('event', 'central_media_archived')->count())->toBe(0);
    Storage::disk('local')->assertExists($media->storage_key);
});

test('failed media persistence removes the unregistered object and rolls back audit', function (): void {
    $actor = centralMediaAdmin();
    Event::listen('eloquent.creating: '.Media::class, function (): never {
        throw new RuntimeException('Échec de persistance simulé');
    });
    expect(fn () => app(CentralMediaService::class)->upload($actor, centralMediaPdf()))->toThrow(RuntimeException::class, 'Échec de persistance simulé');
    expect(Media::count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([])
        ->and(Activity::where('event', 'central_media_uploaded')->count())->toBe(0);
});

test('media upload storage failure returns a validation error without fake success', function (): void {
    $actor = centralMediaAdmin();
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('putFileAs')->once()->andReturnFalse();
    $disk->shouldReceive('exists')->once()->andReturnFalse();
    Storage::shouldReceive('disk')->with('local')->andReturn($disk);
    $this->actingAs($actor, 'central')->post(route('central.media.store'), ['file' => centralMediaPdf()])->assertSessionHasErrors('file');
    expect(Media::count())->toBe(0);
});
