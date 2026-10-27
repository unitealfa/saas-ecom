<?php

namespace App\Http\Controllers\Central\Admin\Deployments;

use App\Enums\Central\Deployments\ErrorCodeEnum;
use App\Enums\Central\Deployments\OperationEnum;
use App\Enums\Central\Deployments\StatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Deployments\DeploymentActionRequest;
use App\Models\Central\TenantSchemaDeployment;
use App\Models\Central\User;
use App\Services\Central\Tenants\TenantDeploymentService;
use App\Services\Central\Tenants\TenantSchemaVersions;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TenantDeploymentController extends Controller
{
    public function __construct(private TenantDeploymentService $deployments, private TenantSchemaVersions $versions) {}

    public function index(Request $request): View
    {
        $actor = $this->centralActor();
        Gate::forUser($actor)->authorize('viewAny', TenantSchemaDeployment::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(StatusEnum::class)],
            'operation' => ['nullable', Rule::enum(OperationEnum::class)],
        ]);

        return view('central.pages.admin.deployments.index', [
            'deployments' => $this->deployments->search($actor, $filters),
        ]);
    }

    public function show(TenantSchemaDeployment $deployment): View
    {
        $actor = $this->centralActor();
        Gate::forUser($actor)->authorize('view', $deployment);

        return view('central.pages.admin.deployments.show', [
            'deployment' => $deployment->load('tenant'),
            'latestVersion' => $this->versions->latest(),
            'isLatest' => $this->isLatest($deployment),
            'attempts' => $this->attempts($deployment),
            'error' => $this->errorFor($actor, $deployment),
        ]);
    }

    public function provision(DeploymentActionRequest $request, TenantSchemaDeployment $deployment): RedirectResponse
    {
        $this->deployments->provision($this->centralActor(), $deployment, $request->validated('expected_state'));

        return $this->result($deployment, 'Provisionnement remis en file. Le worker central traitera la demande.');
    }

    public function migrate(DeploymentActionRequest $request, TenantSchemaDeployment $deployment): RedirectResponse
    {
        $created = $this->deployments->migrate(
            $this->centralActor(),
            $deployment->tenant,
            $request->validated('target_version'),
            $request->validated('expected_state'),
        );

        return $this->result($created, 'Migration demandée. La boutique reste indisponible pendant le traitement.');
    }

    public function retry(DeploymentActionRequest $request, TenantSchemaDeployment $deployment): RedirectResponse
    {
        $created = $this->deployments->retry($this->centralActor(), $deployment, $request->validated('expected_state'));

        return $this->result($created, 'Demande de reprise prise en compte. Consultez l’état de la tentative ; l’échec précédent est conservé.');
    }

    /** Vrai si aucune opération plus récente n'existe pour la boutique. */
    private function isLatest(TenantSchemaDeployment $deployment): bool
    {
        return ! TenantSchemaDeployment::where('tenant_id', $deployment->tenant_id)
            ->where('id', '>', $deployment->id)
            ->exists();
    }

    /**
     * Tentatives de la même opération (même corrélation), de la première à la dernière.
     *
     * @return Collection<int, TenantSchemaDeployment>
     */
    private function attempts(TenantSchemaDeployment $deployment): Collection
    {
        return TenantSchemaDeployment::where('tenant_id', $deployment->tenant_id)
            ->where('correlation_id', $deployment->correlation_id)
            ->orderBy('attempt_number')
            ->get(['uuid', 'attempt_number', 'status', 'created_at']);
    }

    /** L'explication de l'échec n'est montrée qu'à qui a la permission d'en voir les détails. */
    private function errorFor(User $actor, TenantSchemaDeployment $deployment): ?string
    {
        if ($deployment->status !== StatusEnum::FAILED || ! Gate::forUser($actor)->allows('viewErrors', $deployment)) {
            return null;
        }

        return ErrorCodeEnum::explain($deployment->error_code);
    }

    /** Fiche du déploiement si l'acteur peut la voir, sinon tableau de bord. */
    private function result(TenantSchemaDeployment $deployment, string $message): RedirectResponse
    {
        $canView = Gate::forUser($this->centralActor())->allows('view', $deployment);

        return redirect()
            ->route($canView ? 'central.deployments.show' : 'dashboard', $canView ? $deployment : [])
            ->with('status', $message);
    }
}
