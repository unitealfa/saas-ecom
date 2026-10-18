<?php

namespace App\Services\Central\Users;

use Illuminate\Validation\ValidationException;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class PhoneNumberNormalizer
{
    public function normalize(string $value, string $countryCode, string $field = 'phone'): string
    {
        $value = trim($value);
        $countryCode = strtoupper(trim($countryCode));
        $util = PhoneNumberUtil::getInstance();

        if (strlen($value) > 255 || ! preg_match('/^[+\p{Nd}\s().\/-]+$/uD', $value)
            || ! in_array($countryCode, $util->getSupportedRegions(), true)) {
            throw ValidationException::withMessages([$field => 'Saisissez un numéro valide pour le pays choisi.']);
        }

        try {
            $number = $util->parse($value, $countryCode);
        } catch (NumberParseException) {
            throw ValidationException::withMessages([$field => 'Saisissez un numéro valide pour le pays choisi.']);
        }

        if ($number->hasExtension() || ! $util->isValidNumberForRegion($number, $countryCode)) {
            throw ValidationException::withMessages([$field => 'Ce numéro ne correspond pas au pays choisi.']);
        }

        return $util->format($number, PhoneNumberFormat::E164);
    }

    public function callingCode(string $countryCode): ?string
    {
        $code = PhoneNumberUtil::getInstance()->getCountryCodeForRegion(strtoupper(trim($countryCode)));

        return $code === 0 ? null : '+'.$code;
    }

    /**
     * Only for historical rows without a phone country. Shared codes are never guessed.
     *
     * @return array{value: string, region: string}
     */
    public function historical(string $value, string $profileCountry): array
    {
        try {
            return ['value' => $this->normalize($value, $profileCountry), 'region' => strtoupper(trim($profileCountry))];
        } catch (ValidationException $exception) {
            if (! str_starts_with(trim($value), '+') && ! str_starts_with(trim($value), '00')) {
                throw $exception;
            }
            $util = PhoneNumberUtil::getInstance();
            try {
                $number = $util->parse($value, strtoupper(trim($profileCountry)));
            } catch (NumberParseException) {
                throw $exception;
            }
            $regions = $util->getRegionCodesForCountryCode($number->getCountryCode());
            if (count($regions) !== 1 || $regions[0] === '001') {
                throw $exception;
            }

            return ['value' => $this->normalize($value, $regions[0]), 'region' => $regions[0]];
        }
    }
}
