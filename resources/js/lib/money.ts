import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

type CurrencyCode = 'SYP' | 'USD';

type CurrencyConfig = {
    symbol: string;
    position: 'before' | 'after';
};

const defaultCurrency: CurrencyCode = 'SYP';

const currencies: Record<CurrencyCode, CurrencyConfig> = {
    SYP: {
        symbol: 'ل.س',
        position: 'after',
    },
    USD: {
        symbol: '$',
        position: 'before',
    },
};

function normalizeCurrency(value: unknown): CurrencyCode {
    return typeof value === 'string' && value in currencies
        ? (value as CurrencyCode)
        : defaultCurrency;
}

export function formatMoney(
    amount: number | string | null | undefined,
    currency: unknown = defaultCurrency,
): string {
    const code = normalizeCurrency(currency);
    const numericAmount = Number(amount ?? 0);
    const safeAmount = Number.isFinite(numericAmount) ? numericAmount : 0;
    const fractionDigits = Number.isInteger(safeAmount) ? 0 : 2;
    const formattedAmount = new Intl.NumberFormat('en-US-u-nu-latn', {
        minimumFractionDigits: fractionDigits,
        maximumFractionDigits: fractionDigits,
    }).format(safeAmount);
    const config = currencies[code];

    return config.position === 'before'
        ? `${config.symbol}${formattedAmount}`
        : `${formattedAmount} ${config.symbol}`;
}

export function useMoneyFormatter() {
    const page = usePage();
    const currency = computed(() =>
        normalizeCurrency((page.props.settings as { currency?: unknown } | undefined)?.currency),
    );

    return {
        currency,
        formatMoney: (amount: number | string | null | undefined): string =>
            formatMoney(amount, currency.value),
    };
}
