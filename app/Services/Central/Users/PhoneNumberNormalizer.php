<?php

namespace App\Services\Central\Users;

use Illuminate\Validation\ValidationException;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class PhoneNumberNormalizer
{
    private const int MAX_LENGTH = 255;

    private const string ALLOWED_CHARACTERS = '/^[+\p{Nd}\s().\/-]+$/uD';

    private const string INVALID_MESSAGE = 'Saisissez un numéro valide pour le pays choisi.';

    private const string MISMATCH_MESSAGE = 'Ce numéro ne correspond pas au pays choisi.';

    public function normalize(string $value, string $countryCode, string $field = 'phone'): string
    {
        $value = trim($value);
        $region = $this->region($countryCode);
        $util = PhoneNumberUtil::getInstance();

        if (strlen($value) > self::MAX_LENGTH
            || ! preg_match(self::ALLOWED_CHARACTERS, $value)
            || ! in_array($region, $util->getSupportedRegions(), true)) {
            throw $this->error($field, self::INVALID_MESSAGE);
        }

        try {
            $number = $util->parse($value, $region);
        } catch (NumberParseException) {
            throw $this->error($field, self::INVALID_MESSAGE);
        }

        if ($number->hasExtension() || ! $util->isValidNumberForRegion($number, $region)) {
            throw $this->error($field, self::MISMATCH_MESSAGE);
        }

        return $util->format($number, PhoneNumberFormat::E164);
    }

    public function callingCode(string $countryCode): ?string
    {
        $code = PhoneNumberUtil::getInstance()->getCountryCodeForRegion($this->region($countryCode));

        return $code === 0 ? null : '+'.$code;
    }

    /** @return array{value: string, region: string} */
    public function historical(string $value, string $profileCountry): array
    {
        $region = $this->region($profileCountry);

        try {
            return ['value' => $this->normalize($value, $region), 'region' => $region];
        } catch (ValidationException $exception) {
            $trimmed = trim($value);

            if (! str_starts_with($trimmed, '+') && ! str_starts_with($trimmed, '00')) {
                throw $exception;
            }

            $util = PhoneNumberUtil::getInstance();

            try {
                $number = $util->parse($value, $region);
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

    private function region(string $countryCode): string
    {
        return strtoupper(trim($countryCode));
    }

    private function error(string $field, string $message): ValidationException
    {
        return ValidationException::withMessages([$field => $message]);
    }
}
