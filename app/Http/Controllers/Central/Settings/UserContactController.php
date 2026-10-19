<?php

namespace App\Http\Controllers\Central\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Users\StoreUserContactRequest;
use App\Models\Central\Country;
use App\Models\Central\User;
use App\Models\Central\UserContact;
use App\Services\Central\Users\UserContactService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserContactController extends Controller
{
    public function __construct(protected UserContactService $contacts) {}

    protected function target(Request $request): User
    {
        $actor = $request->user('central');
        abort_unless($actor instanceof User, 403);

        return $actor;
    }

    public function index(Request $request, ?User $user = null): View
    {
        $target = $this->target($request);
        $this->contacts->authorize($request->user('central'), $target);
        $available = $target->getConnection()->getSchemaBuilder()->hasTable('user_contacts');

        return view('central.pages.settings.contacts', [
            'target' => $target, 'available' => $available,
            'contacts' => $available ? $target->contacts()->with('country')->orderBy('kind')->orderByDesc('is_primary')->get() : collect(),
            'countries' => Country::where('is_active', true)->orderBy('name_fr')->get(),
            'admin' => $request->route('user') !== null,
        ]);
    }

    public function store(StoreUserContactRequest $request, ?User $user = null): RedirectResponse
    {
        $target = $this->target($request);
        $contact = $this->contacts->add($request->user('central'), $target, $request->validated('kind'),
            $request->validated('value'), $request->validated('country_uuid'));
        $sent = $contact->kind !== 'email' || $this->contacts->sendVerification($request->user('central'), $target, $contact);

        return back()->with($sent ? 'status' : 'warning', $sent
            ? 'Coordonnée ajoutée. Pour une adresse e-mail, consultez le lien de vérification.'
            : 'Coordonnée ajoutée, mais le message n’a pas pu être envoyé. Réessayez le renvoi.');
    }

    public function primary(Request $request, ?User $user = null, ?UserContact $contact = null): RedirectResponse
    {
        $this->contacts->makePrimary($request->user('central'), $this->target($request), $contact);

        return back()->with('status', 'Coordonnée principale modifiée.');
    }

    public function destroy(Request $request, ?User $user = null, ?UserContact $contact = null): RedirectResponse
    {
        $this->contacts->remove($request->user('central'), $this->target($request), $contact);

        return back()->with('status', 'Coordonnée secondaire supprimée.');
    }

    public function resend(Request $request, ?User $user = null, ?UserContact $contact = null): RedirectResponse
    {
        $sent = $this->contacts->sendVerification($request->user('central'), $this->target($request), $contact);

        return back()->with($sent ? 'status' : 'warning', $sent ? 'Lien de vérification envoyé.' : 'Envoi impossible. Réessayez plus tard.');
    }

    public function verify(Request $request, UserContact $contact, string $hash): \Symfony\Component\HttpFoundation\Response
    {
        $this->contacts->verify($request->user('central'), $contact, $hash);
        if ($contact->fresh()->is_primary) {
            return app(\Laravel\Fortify\Contracts\VerifyEmailResponse::class)->toResponse($request);
        }
        if ($request->user('central')->fresh()->status !== \App\Enums\Central\Users\StatusEnum::ACTIVE) {
            return redirect()->to(app(\App\Services\Central\Auth\RegistrationJourney::class)->destination($request->user('central')->fresh()))
                ->with('status', 'Adresse e-mail secondaire vérifiée.');
        }

        return redirect()->route('central.contacts.index')->with('status', 'Adresse e-mail vérifiée.');
    }
}
