import { Component } from '@angular/core';
import { LanguageService } from './core/services/language.service';
import { ThemeService } from './core/services/theme.service';

@Component({
  selector: 'app-root',
  template: '<router-outlet></router-outlet>',
})
export class AppComponent {
  constructor(
    private languageService: LanguageService,
    private themeService: ThemeService,
  ) {
    this.languageService.init();
    this.themeService.init();
  }
}
