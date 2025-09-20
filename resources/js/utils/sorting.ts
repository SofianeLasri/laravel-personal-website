/**
 * Sorting utilities for table components
 */

export type SortDirection = 'asc' | 'desc';

/**
 * Generic comparison function for sorting various data types
 * Handles strings, numbers, booleans, dates, and null/undefined values
 *
 * @param a - First value to compare
 * @param b - Second value to compare
 * @param direction - Sort direction ('asc' or 'desc')
 * @returns Comparison result for Array.sort()
 */
export const compareValues = (a: unknown, b: unknown, direction: SortDirection): number => {
    const multiplier = direction === 'asc' ? 1 : -1;

    // Handle null/undefined values
    if (a === null || a === undefined) return multiplier;
    if (b === null || b === undefined) return -multiplier;

    // String comparison with French locale
    if (typeof a === 'string' && typeof b === 'string') {
        return multiplier * a.localeCompare(b, 'fr', { sensitivity: 'base' });
    }

    // Number comparison
    if (typeof a === 'number' && typeof b === 'number') {
        return multiplier * (a - b);
    }

    // Boolean comparison
    if (typeof a === 'boolean' && typeof b === 'boolean') {
        return multiplier * (a === b ? 0 : a ? -1 : 1);
    }

    // Date comparison
    if (a instanceof Date && b instanceof Date) {
        return multiplier * (a.getTime() - b.getTime());
    }

    // String date comparison
    if (typeof a === 'string' && !isNaN(Date.parse(a)) && typeof b === 'string' && !isNaN(Date.parse(b))) {
        return multiplier * (new Date(a).getTime() - new Date(b).getTime());
    }

    // Fallback to string comparison for primitive values only
    const aStr = a === null || a === undefined ? '' : typeof a === 'string' || typeof a === 'number' || typeof a === 'boolean' ? String(a) : '';
    const bStr = b === null || b === undefined ? '' : typeof b === 'string' || typeof b === 'number' || typeof b === 'boolean' ? String(b) : '';
    return multiplier * aStr.localeCompare(bStr);
};
