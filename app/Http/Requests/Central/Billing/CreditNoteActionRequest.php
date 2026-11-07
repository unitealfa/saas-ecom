<?php

namespace App\Http\Requests\Central\Billing;

use App\Models\Central\Billing\SaasCreditNote;
use App\Models\Central\Users\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CreditNoteActionRequest extends FormRequest
{
    private const array ACTIONS = ['prepare' => 'reserveNumber', 'issue' => 'issue', 'cancel' => 'cancel'];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $actor = $this->user('central');
        $credit = $this->route('credit');
        $action = $this->route()?->getActionMethod();

        return $actor instanceof User && $credit instanceof SaasCreditNote && isset(self::ACTIONS[$action])
            && Gate::forUser($actor)->allows(self::ACTIONS[$action], $credit);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['expected_state' => ['required', 'string', 'regex:'.SaasCreditNote::STATE_PATTERN],
            'reason' => $this->route()?->getActionMethod() === 'cancel' ? ['required', 'string', 'max:2000'] : ['missing'],
            ...array_fill_keys(['lines', 'total_amount', 'user_id', 'status', 'document_type', 'original_invoice_id', 'number', 'document_media_id'], ['missing'])];
    }
}
