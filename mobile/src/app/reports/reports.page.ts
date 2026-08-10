import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup } from '@angular/forms';
import { DashboardService } from '../core/services/dashboard.service';
import { DashboardStats } from '../core/models/company.model';

@Component({
  selector: 'app-reports',
  templateUrl: './reports.page.html',
  styleUrls: ['./reports.page.scss'],
  standalone: false,
})
export class ReportsPage implements OnInit {
  filterForm: FormGroup;
  loading = true;
  stats: DashboardStats | null = null;

  readonly years = [2024, 2025, 2026];
  readonly monthLabels = ['J', 'F', 'M', 'A', 'M', 'J', 'J', 'A', 'S', 'O', 'N', 'D'];
  readonly monthNames = [
    'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
    'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre',
  ];

  constructor(
    private fb: FormBuilder,
    private dashboardService: DashboardService,
  ) {
    const now = new Date();
    this.filterForm = this.fb.group({
      year: [now.getFullYear()],
      month: [now.getMonth() + 1],
    });
  }

  ngOnInit(): void {
    this.load();
  }

  load(): void {
    this.loading = true;
    const { year, month } = this.filterForm.value;

    this.dashboardService.getStats(year, month).subscribe({
      next: (stats) => { this.stats = stats; this.loading = false; },
      error: () => { this.loading = false; },
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
}
