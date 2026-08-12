import { Component, OnInit } from '@angular/core';
import { ChartData, ChartOptions } from 'chart.js';
import { DashboardService } from '../../core/services/dashboard.service';
import { AuthService } from '../../core/services/auth.service';
import { DashboardStats } from '../../core/models/company.model';

@Component({
  selector: 'app-dashboard',
  templateUrl: './dashboard.component.html',
})
export class DashboardComponent implements OnInit {
  stats: DashboardStats | null = null;
  loading = true;
  error = false;

  readonly months = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];

  trendChartData: ChartData<'bar'> = { labels: [], datasets: [] };
  categoryChartData: ChartData<'doughnut'> = { labels: [], datasets: [] };

  chartOptions: ChartOptions = {
    responsive: true,
    plugins: { legend: { display: false } },
  };

  constructor(
    private dashboardService: DashboardService,
    public authService: AuthService,
  ) {}

  ngOnInit(): void {
    this.load();
  }

  load(): void {
    this.loading = true;
    this.error = false;
    this.dashboardService.getStats().subscribe({
      next: (stats) => {
        this.stats = stats;
        this.buildCharts(stats);
        this.loading = false;
      },
      error: () => {
        this.loading = false;
        this.error = true;
      },
    });
  }

  private buildCharts(stats: DashboardStats): void {
    // Monthly trend bar chart
    this.trendChartData = {
      labels: stats.monthlyTrend.map(m => this.months[m.month - 1]),
      datasets: [{
        data: stats.monthlyTrend.map(m => m.total),
        backgroundColor: '#6366f1',
        borderRadius: 6,
      }],
    };

    // Category doughnut chart
    this.categoryChartData = {
      labels: stats.monthlyByCategory.map(c => c.name),
      datasets: [{
        data: stats.monthlyByCategory.map(c => c.total),
        backgroundColor: stats.monthlyByCategory.map(c => c.color ?? '#6366f1'),
      }],
    };
  }
}
