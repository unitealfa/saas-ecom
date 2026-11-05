<?php

namespace App\Services\Central\Billing;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class InvoiceCalculator
{
    /** @param list<array<string, mixed>> $lines
     * @param  list<array{code: string, rate: string}>  $rates
     * @return array{lines: list<array<string, mixed>>, taxes: list<array<string, string>>, net_amount: string, tax_amount: string, total_amount: string}
     */
    public function calculate(array $lines, array $rates): array
    {
        Validator::make(['lines' => $lines, 'rates' => $rates], [
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'string', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/D', 'gt:0'],
            'lines.*.net_unit_price' => ['required', 'string', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/D'],
            'lines.*.net_discount' => ['required', 'string', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/D'],
            'rates' => ['array', 'max:10'], 'rates.*.code' => ['required', 'string', 'max:64', 'distinct'],
            'rates.*.rate' => ['required', 'string', 'regex:/^\d{1,3}(?:\.\d{1,4})?$/D', 'lte:100'],
        ])->validate();
        $result = ['lines' => [], 'taxes' => [], 'net_amount' => '0.00', 'tax_amount' => '0.00', 'total_amount' => '0.00'];
        $taxes = [];
        foreach ($lines as $line) {
            $net = BigDecimal::of($line['quantity'])->multipliedBy($line['net_unit_price'])
                ->minus($line['net_discount'])->toScale(2, RoundingMode::HalfUp);
            if ($net->isNegative()) {
                throw ValidationException::withMessages(['lines' => 'La remise dépasse le montant de la ligne.']);
            }
            $lineTaxes = [];
            $tax = BigDecimal::of('0.00');
            foreach ($rates as $rate) {
                $amount = $net->multipliedBy($rate['rate'])->dividedBy(100, 2, RoundingMode::HalfUp);
                $lineTaxes[] = ['code' => $rate['code'], 'rate' => $rate['rate'], 'amount' => (string) $amount];
                $taxes[$rate['code']] = ['code' => $rate['code'], 'rate' => $rate['rate'],
                    'amount' => (string) BigDecimal::of($taxes[$rate['code']]['amount'] ?? '0.00')->plus($amount)];
                $tax = $tax->plus($amount);
            }
            $computed = [...$line, 'quantity' => (string) BigDecimal::of($line['quantity'])->toScale(2),
                'net_unit_price' => (string) BigDecimal::of($line['net_unit_price'])->toScale(2),
                'net_discount' => (string) BigDecimal::of($line['net_discount'])->toScale(2),
                'net_amount' => (string) $net, 'taxes' => $lineTaxes, 'tax_amount' => (string) $tax,
                'total_amount' => (string) $net->plus($tax)];
            foreach (['net_amount', 'tax_amount', 'total_amount'] as $field) {
                $sum = BigDecimal::of($result[$field])->plus($computed[$field]);
                if ($sum->isGreaterThan('999999999999.99')) {
                    throw ValidationException::withMessages(['lines' => 'Le montant dépasse la capacité du document.']);
                }
                $result[$field] = (string) $sum;
            }
            $result['lines'][] = $computed;
        }
        $result['taxes'] = array_values($taxes);

        return $result;
    }

    /** Une échéance représente le TTC. Le centime résiduel est affecté à la dernière taxe.
     * @param  list<array{code: string, rate: string}>  $rates
     * @return array<string, mixed>
     */
    public function fromTotal(string $total, string $description, array $rates): array
    {
        $this->calculate([['description' => $description, 'quantity' => '1.00', 'net_unit_price' => $total, 'net_discount' => '0.00']], $rates);
        $rate = BigDecimal::of(100);
        foreach ($rates as $item) {
            $rate = $rate->plus($item['rate']);
        }
        $net = BigDecimal::of($total)->multipliedBy(100)->dividedBy($rate, 2, RoundingMode::HalfUp);
        $result = $this->calculate([['description' => $description, 'quantity' => '1.00', 'net_unit_price' => (string) $net, 'net_discount' => '0.00']], $rates);
        $difference = BigDecimal::of($total)->minus($result['total_amount']);
        if (! $difference->isZero() && $rates !== []) {
            $last = array_key_last($result['taxes']);
            if ($last === null) {
                throw ValidationException::withMessages(['rates' => 'La ventilation des taxes est absente.']);
            }
            $result['taxes'][$last]['amount'] = (string) BigDecimal::of($result['taxes'][$last]['amount'])->plus($difference);
            if (BigDecimal::of($result['taxes'][$last]['amount'])->isNegative()) {
                throw ValidationException::withMessages(['rates' => 'Ces taux ne permettent pas une ventilation positive du total TTC.']);
            }
            $result['lines'][0]['taxes'] = $result['taxes'];
            $result['tax_amount'] = (string) BigDecimal::of($total)->minus($net);
            $result['lines'][0]['tax_amount'] = $result['tax_amount'];
            $result['lines'][0]['total_amount'] = $total;
            $result['total_amount'] = $total;
        }

        return $result;
    }
}
