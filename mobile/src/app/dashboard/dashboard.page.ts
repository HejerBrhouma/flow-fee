import { Component, OnInit } from '@angular/core';
import { DashboardService } from '../core/services/dashboard.service';
import { AuthService } from '../core/services/auth.service';
import { DashboardStats } from '../core/models/company.model';

@Component({
  selector: 'app-dashboard',
  templateUrl: 'dashboard.page.html',
  styleUrls: ['dashboard.page.scss'],
  standalone: false,
})
export class DashboardPage implements OnInit {
  stats: DashboardStats | null = null;
  loading = true;
  error = false;

  constructor(
    private dashboardService: DashboardService,
    public authService: AuthService,
  ) {}

  ngOnInit(): void {
    this.load();
  }

  load(event?: CustomEvent): void {
    this.loading = !event;
    this.error = false;

    this.dashboardService.getStats().subscribe({
      next: (stats) => {
        this.stats = stats;
        this.loading = false;
        (event?.target as HTMLIonRefresherElement | undefined)?.complete();
      },
      error: () => {
        this.loading = false;
        // A failed pull-to-refresh keeps the existing data on screen instead of
        // replacing it with an error state.
        if (!event) this.error = true;
        (event?.target as HTMLIonRefresherElement | undefined)?.complete();
      },
    });
  }

  maxCategoryTotal(): number {
    if (!this.stats?.monthlyByCategory.length) return 1;
    return Math.max(...this.stats.monthlyByCategory.map(c => c.total));
  }

  maxTrendTotal(): number {
    if (!this.stats?.monthlyTrend.length) return 1;
    return Math.max(...this.stats.monthlyTrend.map(m => m.total), 1);
  }

  /**
   * The API only returns months that actually had expenses, so a year with a single
   * month of data would otherwise render as one bar filling the whole chart width.
   * Padding out all 12 months (0 where there's no data) keeps the axis meaningful.
   */
  get fullYearTrend(): { month: number; total: number }[] {
    const totals = new Map(this.stats?.monthlyTrend.map(m => [m.month, m.total]) ?? []);
    return Array.from({ length: 12 }, (_, i) => ({ month: i + 1, total: totals.get(i + 1) ?? 0 }));
  }

  readonly monthLabels = ['J', 'F', 'M', 'A', 'M', 'J', 'J', 'A', 'S', 'O', 'N', 'D'];
}
