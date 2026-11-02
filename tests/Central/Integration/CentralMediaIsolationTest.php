<?php

use App\Models\Central\Media\Media;
use App\Models\Central\Roles\Role;
use App\Models\Central\Tenants\Tenant;
use App\Models\Tenant\Media\Media as TenantMedia;
use App\Models\User;
use App\Services\Central\Media\CentralMediaService;
use App\Services\Central\Media\PrivateMediaAccessService;
use Database\Factories\Central\MediaFactory;
use Database\Seeders\Central\AuthorizationSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

pest()->extend(TestCase::class);

test('central files remain central and cannot be read or changed through an initialized tenant', function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $this->seed(AuthorizationSeeder::class);
    $disk = Storage::fake('central-media-isolation-'.getmypid());
    Storage::set('local', $disk);
    $actor = User::factory()->create();
    $actor->assignRole(Role::findByName('root', 'central'));
    $media = app(CentralMediaService::class)->upload($actor,
        UploadedFile::fake()->createWithContent('central.pdf', MediaFactory::PDF_CONTENT));
    $centralDatabase = $media->getConnection()->getDatabaseName();
    $tenant = null;
    try {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);
        expect(DB::connection()->getDatabaseName())->not->toBe($centralDatabase);
        expect((new Media)->getConnection()->getDatabaseName())->toBe($centralDatabase)
            ->and(TenantMedia::where('uuid', $media->uuid)->exists())->toBeFalse();
        expect(fn () => app(PrivateMediaAccessService::class)->download($actor, $media))->toThrow(HttpException::class);
        expect(fn () => app(CentralMediaService::class)->archive($actor, $media, $media->managementState()))->toThrow(HttpException::class);
        expect(fn () => app(CentralMediaService::class)->upload($actor,
            UploadedFile::fake()->createWithContent('tenant-attempt.pdf', MediaFactory::PDF_CONTENT)))->toThrow(HttpException::class);
        expect(Media::count())->toBe(1);
    } finally {
        tenancy()->end();
        Storage::set('local', $disk);
        if ($tenant !== null) {
            TestCase::dropIsolatedMysqlDatabase($tenant->database()->getName());
        }
    }
    expect($disk->get($media->storage_key))->toBe(MediaFactory::PDF_CONTENT)
        ->and($media->refresh()->trashed())->toBeFalse()->and(tenancy()->initialized)->toBeFalse();
});
