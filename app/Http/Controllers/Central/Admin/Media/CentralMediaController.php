<?php

namespace App\Http\Controllers\Central\Admin\Media;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Media\ArchiveCentralMediaRequest;
use App\Http\Requests\Central\Media\StoreCentralMediaRequest;
use App\Models\Central\Media\Media;
use App\Services\Central\Media\CentralMediaService;
use App\Services\Central\Media\PrivateMediaAccessService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CentralMediaController extends Controller
{
    private const int DOWNLOAD_MINUTES = 5;

    public function __construct(
        private readonly CentralMediaService $media,
        private readonly PrivateMediaAccessService $privateFiles,
    ) {}

    public function index(Request $request): View
    {
        $actor = $this->centralActor();
        Gate::forUser($actor)->authorize('viewAny', Media::class);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:255'], 'archived' => ['nullable', 'boolean']]);

        return view('central.pages.admin.media.index', ['files' => $this->media->search($actor, $filters)]);
    }

    public function create(): View
    {
        Gate::forUser($this->centralActor())->authorize('upload', Media::class);

        return view('central.pages.admin.media.create');
    }

    public function store(StoreCentralMediaRequest $request): RedirectResponse
    {
        $this->media->upload($this->centralActor(), $request->file('file'));

        return $this->redirectWithStatus('Fichier privé importé.');
    }

    public function show(Media $media): View
    {
        $actor = $this->centralActor();
        Gate::forUser($actor)->authorize('view', $media);

        return view('central.pages.admin.media.show', [
            'media' => $media->load('creator'),
            'downloadUrl' => Gate::forUser($actor)->allows('download', $media)
                ? URL::temporarySignedRoute('central.media.download', now()->addMinutes(self::DOWNLOAD_MINUTES), ['media' => $media], absolute: false)
                : null,
        ]);
    }

    public function download(Media $media): StreamedResponse
    {
        return $this->privateFiles->download($this->centralActor(), $media);
    }

    public function archive(ArchiveCentralMediaRequest $request, Media $media): RedirectResponse
    {
        $this->media->archive($this->centralActor(), $media, $request->validated('expected_state'));

        return $this->redirectWithStatus('Fichier archivé. Les octets et l’historique sont conservés.');
    }

    private function redirectWithStatus(string $message): RedirectResponse
    {
        $redirect = Gate::forUser($this->centralActor())->allows('viewAny', Media::class)
            ? to_route('central.media.index')
            : to_route('dashboard');

        return $redirect->with('status', $message);
    }
}
