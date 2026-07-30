import { Component } from '@angular/core';
import { Router } from '@angular/router';
import { AuthService } from '../../core/services/auth.service';

@Component({
  selector: 'app-shell',
  templateUrl: './shell.component.html',
})
export class ShellComponent {
  sidebarOpen = false;

  navItems = [
    { label: 'Tableau de bord', icon: '📊', route: '/dashboard' },
    { label: 'Mes dépenses',    icon: '💸', route: '/expenses' },
    { label: 'Rapports',        icon: '📈', route: '/reports' },
  ];

  proNavItems = [
    { label: 'Mon équipe',      icon: '👥', route: '/company/1/team' },
    { label: 'Départements',    icon: '🏢', route: '/company/1/departments' },
  ];

  constructor(public authService: AuthService, private router: Router) {}

  logout(): void {
    this.authService.logout();
  }

  isPro(): boolean {
    return this.authService.isProfessional();
  }
}
