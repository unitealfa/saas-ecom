<?php

$validatedCustomers = env('BILLING_VALIDATED_CUSTOMERS', '{}');
if (! is_string($validatedCustomers)) {
    throw new InvalidArgumentException('BILLING_VALIDATED_CUSTOMERS must be a JSON object.');
}
$validatedCustomers = json_decode($validatedCustomers, true, 512, JSON_THROW_ON_ERROR);
if (! is_array($validatedCustomers)) {
    throw new InvalidArgumentException('BILLING_VALIDATED_CUSTOMERS must contain customer identities indexed by UUID.');
}

return [
    'issuer' => [
        'name' => env('BILLING_ISSUER_NAME'),
        'address' => env('BILLING_ISSUER_ADDRESS'),
        'nif' => env('BILLING_ISSUER_NIF'),
        'validation_reference' => env('BILLING_ISSUER_VALIDATION_REFERENCE'),
        'validated_at' => env('BILLING_ISSUER_VALIDATED_AT'),
    ],
    'customers' => $validatedCustomers,
    'tax_policy' => [
        'validation_reference' => env('BILLING_TAX_VALIDATION_REFERENCE'),
        'validated_at' => env('BILLING_TAX_VALIDATED_AT'),
        'no_tax_reason' => env('BILLING_NO_TAX_REASON'),
        'rates' => [],
    ],
];
