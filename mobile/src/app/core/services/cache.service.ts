import { Injectable } from '@angular/core';
import { Preferences } from '@capacitor/preferences';

const PREFIX = 'flow_fee_cache_';

/**
 * Read-through local cache for GET responses (dashboard stats, expense list, budgets...),
 * used stale-while-revalidate style: a page shows whatever was last cached instantly (no
 * skeleton flash on every navigation), then fetches fresh data in the background and
 * re-caches it. This is also what makes those screens usable at all while offline — there's
 * simply no other source of data to show once the network call fails.
 */
@Injectable({ providedIn: 'root' })
export class CacheService {
  async get<T>(key: string): Promise<T | null> {
    const { value } = await Preferences.get({ key: PREFIX + key });
    if (!value) return null;

    try {
      return JSON.parse(value) as T;
    } catch {
      await Preferences.remove({ key: PREFIX + key });
      return null;
    }
  }

  async set<T>(key: string, value: T): Promise<void> {
    await Preferences.set({ key: PREFIX + key, value: JSON.stringify(value) });
  }
}
