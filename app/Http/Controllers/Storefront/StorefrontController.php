<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\VerificationStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureLocalDiagnostics;
use App\Models\Central\Domains\Domain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stancl\Tenancy\Resolvers\DomainTenantResolver;

class StorefrontController extends Controller
{
    private const array REDIRECTABLE_METHODS = ['GET', 'HEAD'];

    public function __invoke(Request $request, EnsureLocalDiagnostics $diagnostics): Response|RedirectResponse
    {
        $domain = DomainTenantResolver::$currentDomain;

        abort_unless(
            $domain instanceof Domain && $domain->verification_status === VerificationStatusEnum::VERIFIED,
            404,
        );

        $primary = $this->primaryToRedirectTo($request, $domain);

        if ($primary !== null) {
            // publicUrl() vient du nom enregistré, jamais de l'en-tête Host de la requête.
            return redirect()->to($primary->publicUrl())->header('Cache-Control', 'no-store');
        }

        $message = 'Cette boutique est en préparation.';

        if ($diagnostics->isAllowed($request)) {
            $message .= "\nID de la boutique : ".tenant('id')
                ."\nID du propriétaire (compte central) : ".tenant('user_id');
        }

        return response($message, 503, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store',
            'Retry-After' => '3600',
        ]);
    }

    /** Accueil d'un domaine secondaire : renvoie le domaine principal s'il est prêt, sinon null. */
    private function primaryToRedirectTo(Request $request, Domain $domain): ?Domain
    {
        if ($domain->is_primary
            || $request->path() !== '/'
            || ! in_array($request->method(), self::REDIRECTABLE_METHODS, true)) {
            return null;
        }

        $primary = Domain::where('tenant_id', $domain->tenant_id)->where('is_primary', true)->first();

        if ($primary === null || $primary->uuid === $domain->uuid) {
            return null;
        }

        return $primary->isReadyAsPrimary() ? $primary : null;
    }
}
