<?php

namespace App\Http\Requests\Central\Billing;

use App\Models\Central\Billing\BillingRule;
use App\Models\Central\Users\User;
use App\Services\Central\Billing\BillingRuleService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateBillingParametersRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user('central');
        $rule = $this->route('billingRule');

        return $actor instanceof User && ($rule instanceof BillingRule
            ? Gate::forUser($actor)->allows('configure', $rule) : Gate::forUser($actor)->allows('create', BillingRule::class));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return BillingRuleService::parameterRules(initial: ! ($this->route('billingRule') instanceof BillingRule));
    }
}
