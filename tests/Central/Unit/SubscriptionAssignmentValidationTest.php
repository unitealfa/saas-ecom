<?php

use App\Http\Requests\Central\Subscriptions\AssignSubscriptionRequest;
use App\Http\Requests\Central\Subscriptions\ChangeSubscriptionRequest;
use App\Http\Requests\Central\Subscriptions\CloseSubscriptionRequest;
use App\Http\Requests\Central\Subscriptions\SearchSubscriptionsRequest;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Tenant\Users\User as TenantUser;
use App\Services\Central\Subscriptions\SubscriptionAssignmentService;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

pest()->extend(TestCase::class);

test('assignment requires public identifiers and a supported period without accepting server fields', function (): void {
    $input = ['owner_uuid' => (string) Str::uuid(), 'plan_uuid' => (string) Str::uuid(), 'operation_key' => (string) Str::uuid(), 'period' => 1, 'expected_amount' => '500.00'];
    expect(Validator::make($input, SubscriptionAssignmentService::rules())->passes())->toBeTrue();
    expect(Validator::make([...$input, 'period' => 9], SubscriptionAssignmentService::rules())->fails())->toBeTrue();
    expect(Validator::make([...$input, 'agreed_amount' => '0'], SubscriptionAssignmentService::rules())->fails())->toBeTrue();
    expect(Validator::make([...$input, 'owner_uuid' => '1'], SubscriptionAssignmentService::rules())->fails())->toBeTrue();
});

test('subscription requests refuse absent and tenant actors even with a bound subscription', function (string $requestClass, bool $tenantActor): void {
    $request = new $requestClass;
    $request->setUserResolver(fn () => $tenantActor ? new TenantUser : null);
    $route = new Route('POST', 'subscriptions/{subscription}', []);
    $route->bind(Request::create('/subscriptions/example', 'POST'));
    $route->setParameter('subscription', new Subscription);
    $request->setRouteResolver(fn () => $route);

    expect($request->authorize())->toBeFalse();
})->with([
    'assignment guest' => [AssignSubscriptionRequest::class, false],
    'assignment tenant' => [AssignSubscriptionRequest::class, true],
    'change guest' => [ChangeSubscriptionRequest::class, false],
    'change tenant' => [ChangeSubscriptionRequest::class, true],
    'closure guest' => [CloseSubscriptionRequest::class, false],
    'closure tenant' => [CloseSubscriptionRequest::class, true],
    'consultation guest' => [SearchSubscriptionsRequest::class, false],
    'consultation tenant' => [SearchSubscriptionsRequest::class, true],
]);
