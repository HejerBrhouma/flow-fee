import { Component } from '@angular/core';
import { AuthService } from '../../core/services/auth.service';
import { IconName } from '../icon/icon.component';

@Component({
  selector: 'app-shell',
  templateUrl: './shell.component.html',
})
export class ShellComponent {
  sidebarOpen = false;

  navItems: { label: string; icon: IconName; route: string }[] = [
    { label: 'Tableau de bord',      icon: 'dashboard', route: '/dashboard' },
    { label: 'Mes dépenses',         icon: 'expenses',  route: '/expenses' },
    { label: 'Mon budget',           icon: 'budget',    route: '/budgets' },
    { label: 'Objectifs d\'épargne', icon: 'savings',   route: '/savings-goals' },
    { label: 'Rapports',             icon: 'reports',   route: '/reports' },
  ];

  proNavItems: { label: string; icon: IconName; route: string }[] = [
    { label: 'Mon équipe',      icon: 'team',        route: '/company/1/team' },
    { label: 'Départements',    icon: 'departments', route: '/company/1/departments' },
  ];

  constructor(public authService: AuthService) {}

  isPro(): boolean {
    return this.authService.isProfessional();
  }
}
