import { Injectable } from '@angular/core';
import { Preferences } from '@capacitor/preferences';

export type AppTheme = 'light' | 'dark';

const THEME_KEY = 'flow_fee_theme';

// Ionic 8's class-based dark palette toggles on this exact class name (see
// @ionic/angular/css/palettes/dark.class.css) — global.scss imports that variant instead of
// dark.system.css so the app can offer a manual override on top of the OS default.
const DARK_CLASS = 'ion-palette-dark';

@Injectable({ providedIn: 'root' })
export class ThemeService {
  get current(): AppTheme {
    return document.documentElement.classList.contains(DARK_CLASS) ? 'dark' : 'light';
  }

  async init(): Promise<void> {
    const { value: stored } = await Preferences.get({ key: THEME_KEY });
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    this.apply((stored as AppTheme | null) ?? (prefersDark ? 'dark' : 'light'));
  }

  toggle(): void {
    this.apply(this.current === 'dark' ? 'light' : 'dark');
  }

  set(theme: AppTheme): void {
    this.apply(theme);
  }

  private apply(theme: AppTheme): void {
    document.documentElement.classList.toggle(DARK_CLASS, theme === 'dark');
    Preferences.set({ key: THEME_KEY, value: theme });
  }
}
