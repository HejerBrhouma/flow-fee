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

  constructor(
    private dashboardService: DashboardService,
    public authService: AuthService,
  ) {}

  ngOnInit(): void {
    this.load();
  }

  load(event?: CustomEvent): void {
    this.loading = !event;

    this.dashboardService.getStats().subscribe({
      next: (stats) => {
        this.stats = stats;
        this.loading = false;
        (event?.target as HTMLIonRefresherElement | undefined)?.complete();
      },
      error: () => {
        this.loading = false;
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

  readonly monthLabels = ['J', 'F', 'M', 'A', 'M', 'J', 'J', 'A', 'S', 'O', 'N', 'D'];
}
