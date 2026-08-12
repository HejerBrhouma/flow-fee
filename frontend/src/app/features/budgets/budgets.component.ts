import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { forkJoin, of } from 'rxjs';
import { catchError } from 'rxjs/operators';
import { BudgetService } from '../../core/services/budget.service';
import { BudgetConsumption } from '../../core/models/company.model';

@Component({
  selector: 'app-budgets',
  templateUrl: './budgets.component.html',
})
export class BudgetsComponent implements OnInit {
  budgets: BudgetConsumption[] = [];
  loading = true;
  error = false;
  showForm = false;

  readonly months = [
    'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
    'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre',
  ];

  form: FormGroup;

  constructor(
    private budgetService: BudgetService,
    private fb: FormBuilder,
    private toastr: ToastrService,
  ) {
    const now = new Date();
    this.form = this.fb.group({
      period: ['monthly', Validators.required],
      year: [now.getFullYear(), Validators.required],
      month: [now.getMonth() + 1],
      amount: [null, [Validators.required, Validators.min(1)]],
    });
  }

  ngOnInit(): void {
    this.load();
  }

  load(): void {
    this.loading = true;
    this.error = false;
    this.budgetService.getMyBudgets().subscribe({
      next: (budgets) => {
        if (budgets.length === 0) {
          this.budgets = [];
          this.loading = false;
          return;
        }

        forkJoin(
          budgets.map(b => this.budgetService.getConsumption(b.id).pipe(catchError(() => of(null))))
        ).subscribe(results => {
          this.budgets = results.filter((r): r is BudgetConsumption => r !== null);
          this.loading = false;
        });
      },
      error: () => { this.loading = false; this.error = true; },
    });
  }

  create(): void {
    if (this.form.invalid) return;

    const { period, year, month, amount } = this.form.value;
    this.budgetService.createMyBudget({
      period,
      year,
      month: period === 'monthly' ? month : undefined,
      amount,
    }).subscribe({
      next: () => {
        this.showForm = false;
        this.form.reset({ period: 'monthly', year: new Date().getFullYear(), month: new Date().getMonth() + 1 });
        this.toastr.success('Budget créé.');
        this.load();
      },
      error: (err) => this.toastr.error(err.error?.errors?.year ?? 'Impossible de créer ce budget (existe-t-il déjà pour cette période ?).'),
    });
  }

  delete(item: BudgetConsumption): void {
    if (!confirm('Supprimer ce budget ?')) return;

    this.budgetService.delete(item.budget.id).subscribe({
      next: () => {
        this.budgets = this.budgets.filter(b => b.budget.id !== item.budget.id);
        this.toastr.success('Budget supprimé.');
      },
      error: () => this.toastr.error('Impossible de supprimer ce budget.'),
    });
  }

  barColor(percentage: number): string {
    if (percentage >= 100) return 'bg-red-500';
    if (percentage >= 90) return 'bg-amber-500';
    return 'bg-indigo-500';
  }

  periodLabel(item: BudgetConsumption): string {
    const b = item.budget;
    return b.period === 'monthly' ? `${this.months[(b.month ?? 1) - 1]} ${b.year}` : `Année ${b.year}`;
  }
}
