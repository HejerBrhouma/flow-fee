import { Component, ElementRef, HostListener } from '@angular/core';
import { AppLanguage, LanguageService } from '../../core/services/language.service';

@Component({
  selector: 'app-language-switcher',
  templateUrl: './language-switcher.component.html',
})
export class LanguageSwitcherComponent {
  open = false;

  constructor(
    public languageService: LanguageService,
    private elementRef: ElementRef<HTMLElement>,
  ) {}

  @HostListener('document:click', ['$event'])
  onDocumentClick(event: MouseEvent): void {
    if (this.open && !this.elementRef.nativeElement.contains(event.target as Node)) {
      this.open = false;
    }
  }

  toggle(): void {
    this.open = !this.open;
  }

  select(lang: AppLanguage): void {
    this.languageService.use(lang);
    this.open = false;
  }
}
