import { Component, OnInit } from '@angular/core';
import { AuthService } from '../../core/services/auth.service';
import { CompanyService } from '../../core/services/company.service';
import { IconName } from '../icon/icon.component';

@Component({
  selector: 'app-shell',
  templateUrl: './shell.component.html',
})
export class ShellComponent implements OnInit {
  sidebarOpen = false;
  companyId: number | null = null;

  navItems: { labelKey: string; icon: IconName; route: string }[] = [
    { labelKey: 'nav.dashboard',     icon: 'dashboard', route: '/dashboard' },
    { labelKey: 'nav.expenses',      icon: 'expenses',  route: '/expenses' },
    { labelKey: 'nav.budget',        icon: 'budget',    route: '/budgets' },
    { labelKey: 'nav.savingsGoals',  icon: 'savings',   route: '/savings-goals' },
    { labelKey: 'nav.reports',       icon: 'reports',   route: '/reports' },
    { labelKey: 'nav.notifications', icon: 'notifications', route: '/notifications' },
  ];

  constructor(
    public authService: AuthService,
    private companyService: CompanyService,
  ) {}

  ngOnInit(): void {
    if (!this.isPro()) return;

    // The company id isn't in /auth/me, so it must be looked up via team membership —
    // hardcoding it (as this used to) breaks for any company other than id 1.
    this.companyService.getMyMembership().subscribe({
      next: (membership) => { this.companyId = membership?.company?.id ?? null; },
      error: () => { this.companyId = null; },
    });
  }

  isPro(): boolean {
    return this.authService.isProfessional();
  }
}
