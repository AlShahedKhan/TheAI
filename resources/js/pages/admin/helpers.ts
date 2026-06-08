export function number(value: number) {
    return new Intl.NumberFormat().format(value);
}

export function taka(value: number) {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'BDT',
        maximumFractionDigits: 0,
    }).format(value);
}

export function usd(value: number) {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 4,
    }).format(value);
}
