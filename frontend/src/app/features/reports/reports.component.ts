import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup } from '@angular/forms';
import { ChartData } from 'chart.js';
import { DashboardService } from '../../core/services/dashboard.service';

@Component({
  selector: 'app-reports',
  templateUrl: './reports.component.html',
})
export class ReportsComponent implements OnInit {
  filterForm: FormGroup;
  loading = false;

  trendChartData: ChartData<'line'> = { labels: [], datasets: [] };
  categoryChartData: ChartData<'bar'> = { labels: [], datasets: [] };

  readonly months = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];

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
      next: (stats) => {
        this.trendChartData = {
          labels: stats.monthlyTrend.map(m => this.months[m.month - 1]),
          datasets: [{
            label: 'Dépenses',
            data: stats.monthlyTrend.map(m => m.total),
            borderColor: '#6366f1',
            backgroundColor: 'rgba(99,102,241,0.1)',
            fill: true,
            tension: 0.4,
          }],
        };

        this.categoryChartData = {
          labels: stats.monthlyByCategory.map(c => c.name),
          datasets: [{
            label: 'Montant',
            data: stats.monthlyByCategory.map(c => c.total),
            backgroundColor: stats.monthlyByCategory.map(c => c.color ?? '#6366f1'),
            borderRadius: 6,
          }],
        };

        this.loading = false;
      },
      error: () => { this.loading = false; },
    });
  }
}
