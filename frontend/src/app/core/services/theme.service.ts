import { Injectable } from '@angular/core';

export type AppTheme = 'light' | 'dark';

const THEME_KEY = 'flow_fee_theme';

@Injectable({ providedIn: 'root' })
export class ThemeService {
  get current(): AppTheme {
    return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
  }

  init(): void {
    const stored = localStorage.getItem(THEME_KEY) as AppTheme | null;
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    this.apply(stored ?? (prefersDark ? 'dark' : 'light'));
  }

  toggle(): void {
    this.apply(this.current === 'dark' ? 'light' : 'dark');
  }

  private apply(theme: AppTheme): void {
    document.documentElement.classList.toggle('dark', theme === 'dark');
    localStorage.setItem(THEME_KEY, theme);
  }
}
