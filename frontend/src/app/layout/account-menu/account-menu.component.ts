import { Component, ElementRef, HostListener } from '@angular/core';
import { AuthService } from '../../core/services/auth.service';

@Component({
  selector: 'app-account-menu',
  templateUrl: './account-menu.component.html',
})
export class AccountMenuComponent {
  open = false;

  constructor(
    public authService: AuthService,
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

  close(): void {
    this.open = false;
  }

  logout(): void {
    this.close();
    this.authService.logout();
  }
}
