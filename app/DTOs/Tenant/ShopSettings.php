<?php

namespace App\DTOs\Tenant;

use Closure;
use Illuminate\Support\Facades\Validator;

final readonly class ShopSettings
{
    /** @param array<string, string> $colors */
    private function __construct(
        public string $businessType,
        public string $themeCode,
        public array $colors,
        public int $cartLifetimeDays,
    ) {}

    /** @param array<string, mixed> $settings */
    public static function fromArray(array $settings = []): self
    {
        Validator::make(['settings' => $settings], [
            'settings' => ['array:business_type,theme_code,colors,cart_lifetime_days'],
        ])->validate();

        $values = array_replace([
            'business_type' => 'OTHER',
            'theme_code' => 'default',
            'colors' => ['primary' => '#2563EB', 'secondary' => '#FFFFFF'],
            'cart_lifetime_days' => 7,
        ], array_filter($settings, fn (mixed $value): bool => $value !== null && $value !== ''));

        /** @var array{business_type: string, theme_code: string, colors: array<string, string>, cart_lifetime_days: int|string} $validated */
        $validated = Validator::make($values, [
            'business_type' => ['required', 'string', 'max:255'],
            'theme_code' => ['required', 'string', 'max:255'],
            'colors' => ['required', 'array', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_array($value) && (array_is_list($value) || array_any(array_keys($value), fn (mixed $key): bool => ! is_string($key)))) {
                    $fail('The colors must be an object with named colors.');
                }
            }],
            'colors.*' => ['required', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/D'],
            'cart_lifetime_days' => ['required', 'integer', 'min:1', 'max:2147483647'],
        ])->validate();

        return new self(
            $validated['business_type'], $validated['theme_code'],
            $validated['colors'], (int) $validated['cart_lifetime_days'],
        );
    }

    /** @return array{business_type: string, theme_code: string, colors: array<string, string>, cart_lifetime_days: int} */
    public function toArray(): array
    {
        return [
            'business_type' => $this->businessType,
            'theme_code' => $this->themeCode,
            'colors' => $this->colors,
            'cart_lifetime_days' => $this->cartLifetimeDays,
        ];
    }
}
