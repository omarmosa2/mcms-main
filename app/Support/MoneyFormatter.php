<?php

namespace App\Support;

use App\Models\ClinicSetting;

class MoneyFormatter
{
    public const DefaultCurrency = 'SYP';

    private const Currencies = [
        'SYP' => [
            'label' => 'الليرة السورية SYP',
            'symbol' => 'ل.س',
            'position' => 'after',
        ],
        'USD' => [
            'label' => 'الدولار الأمريكي USD',
            'symbol' => '$',
            'position' => 'before',
        ],
    ];

    /**
     * @return list<string>
     */
    public static function currencyCodes(): array
    {
        return array_keys(self::Currencies);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function currencyOptions(): array
    {
        return collect(self::Currencies)
            ->map(fn (array $currency, string $code): array => [
                'value' => $code,
                'label' => $currency['label'],
            ])
            ->values()
            ->all();
    }

    public static function normalizeCurrency(mixed $currency): string
    {
        $code = is_string($currency) ? strtoupper($currency) : self::DefaultCurrency;

        return array_key_exists($code, self::Currencies) ? $code : self::DefaultCurrency;
    }

    public static function currencyForClinic(int $clinicId): string
    {
        return self::normalizeCurrency(
            ClinicSetting::get($clinicId, 'clinic', 'currency', self::DefaultCurrency),
        );
    }

    public static function format(mixed $amount, ?string $currency = null): string
    {
        $code = self::normalizeCurrency($currency);
        $value = is_numeric($amount) ? (float) $amount : 0.0;
        $fractionDigits = floor($value) === $value ? 0 : 2;
        $formatted = number_format($value, $fractionDigits);
        $config = self::Currencies[$code];

        if ($config['position'] === 'before') {
            return $config['symbol'].$formatted;
        }

        return $formatted.' '.$config['symbol'];
    }

    public static function formatForClinic(mixed $amount, int $clinicId): string
    {
        return self::format($amount, self::currencyForClinic($clinicId));
    }
}
