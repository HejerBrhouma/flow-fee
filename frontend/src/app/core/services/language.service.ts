import { Injectable } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';

export type AppLanguage = 'fr' | 'en' | 'ar';

const LANGUAGE_KEY = 'flow_fee_language';
const RTL_LANGUAGES: AppLanguage[] = ['ar'];
const SUPPORTED_LANGUAGES: AppLanguage[] = ['fr', 'en', 'ar'];

@Injectable({ providedIn: 'root' })
export class LanguageService {
  readonly languages: { code: AppLanguage; label: string }[] = [
    { code: 'fr', label: 'Français' },
    { code: 'en', label: 'English' },
    { code: 'ar', label: 'العربية' },
  ];

  constructor(private translate: TranslateService) {
    this.translate.addLangs(SUPPORTED_LANGUAGES);
    this.translate.setDefaultLang('fr');
  }

  get current(): AppLanguage {
    return (this.translate.currentLang as AppLanguage) ?? 'fr';
  }

  init(): void {
    const stored = localStorage.getItem(LANGUAGE_KEY) as AppLanguage | null;
    const browserLang = this.translate.getBrowserLang() as AppLanguage | undefined;
    const lang = stored ?? (browserLang && SUPPORTED_LANGUAGES.includes(browserLang) ? browserLang : 'fr');
    this.use(lang);
  }

  use(lang: AppLanguage): void {
    this.translate.use(lang);
    localStorage.setItem(LANGUAGE_KEY, lang);
    document.documentElement.lang = lang;
    document.documentElement.dir = RTL_LANGUAGES.includes(lang) ? 'rtl' : 'ltr';
  }
}
