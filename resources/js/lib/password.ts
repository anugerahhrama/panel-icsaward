const LOWERCASE = 'abcdefghijkmnpqrstuvwxyz';
const UPPERCASE = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
const DIGITS = '23456789';
const SYMBOLS = '!@#$%&*?-_+=';

function randomIndex(max: number): number {
    const values = new Uint32Array(1);
    const limit = Math.floor(0x100000000 / max) * max;

    do {
        crypto.getRandomValues(values);
    } while (values[0] >= limit);

    return values[0] % max;
}

function pick(characters: string): string {
    return characters[randomIndex(characters.length)];
}

/**
 * A random password that satisfies the production `Password::defaults()` rule (mixed case,
 * numbers and symbols), without look-alike characters so it can be read out to a judge.
 */
export function generatePassword(length = 16): string {
    const all = LOWERCASE + UPPERCASE + DIGITS + SYMBOLS;
    const characters = [
        pick(LOWERCASE),
        pick(UPPERCASE),
        pick(DIGITS),
        pick(SYMBOLS),
        ...Array.from({ length: length - 4 }, () => pick(all)),
    ];

    for (let index = characters.length - 1; index > 0; index--) {
        const swap = randomIndex(index + 1);
        [characters[index], characters[swap]] = [
            characters[swap],
            characters[index],
        ];
    }

    return characters.join('');
}
