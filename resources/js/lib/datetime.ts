/**
 * Format an ISO timestamp in Western Indonesian Time so server and client render the same text.
 */
export function formatDateTimeWib(value: string): string {
    return `${new Intl.DateTimeFormat('en-GB', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        timeZone: 'Asia/Jakarta',
    }).format(new Date(value))} WIB`;
}

/**
 * Today's date in Western Indonesian Time as `YYYY-MM-DD`, e.g. for the `min` of a date input.
 */
export function todayWib(): string {
    return new Intl.DateTimeFormat('en-CA', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        timeZone: 'Asia/Jakarta',
    }).format(new Date());
}
