<?php

use App\Http\Requests\Tenant\Roles\SaveRoleRequest;

test('the profile form keeps only checked permissions and their durations', function (): void {
    $request = SaveRoleRequest::create('/', 'POST', [
        '_selection_form' => '1',
        'selected_permissions' => ['f8ab2e75-9834-4314-9aae-f07afde9ee40'],
        'permissions' => [
            'f8ab2e75-9834-4314-9aae-f07afde9ee40' => '9999',
            'b0e248ea-0134-41f9-bacd-7e0e8ab8c5fa' => '30',
        ],
    ]);

    (new ReflectionMethod($request, 'prepareForValidation'))->invoke($request);

    expect($request->input('permissions'))->toBe(['f8ab2e75-9834-4314-9aae-f07afde9ee40' => '9999']);
});
