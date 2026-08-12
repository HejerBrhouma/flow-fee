import { Component, OnInit } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { forkJoin, of } from 'rxjs';
import { catchError } from 'rxjs/operators';
import { CompanyService } from '../../../core/services/company.service';
import { BudgetService } from '../../../core/services/budget.service';
import { AuthService } from '../../../core/services/auth.service';
import { BudgetConsumption, Department } from '../../../core/models/company.model';

@Component({
  selector: 'app-departments',
  templateUrl: './departments.component.html',
})
export class DepartmentsComponent implements OnInit {
  companyId!: number;
  departments: Department[] = [];
  loading = true;
  error = false;
  showForm = false;

  form: FormGroup;

  expandedDeptId: number | null = null;
  deptBudgets: Record<number, BudgetConsumption[]> = {};
  budgetsLoading = false;
  budgetForm: FormGroup;

  readonly months = [
    'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
    'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre',
  ];

  constructor(
    private route: ActivatedRoute,
    private companyService: CompanyService,
    private budgetService: BudgetService,
    private fb: FormBuilder,
    private toastr: ToastrService,
    public authService: AuthService,
  ) {
    this.form = this.fb.group({
      name: ['', Validators.required],
      description: [''],
      monthlyBudget: [null],
      yearlyBudget: [null],
    });

    const now = new Date();
    this.budgetForm = this.fb.group({
      period: ['monthly', Validators.required],
      year: [now.getFullYear(), Validators.required],
      month: [now.getMonth() + 1],
      amount: [null, [Validators.required, Validators.min(1)]],
    });
  }

  ngOnInit(): void {
    this.companyId = +this.route.snapshot.params['id'];
    this.load();
  }

  load(): void {
    this.loading = true;
    this.error = false;
    this.companyService.getDepartments(this.companyId).subscribe({
      next: (depts) => { this.departments = depts; this.loading = false; },
      error: () => { this.loading = false; this.error = true; },
    });
  }

  create(): void {
    if (this.form.invalid) return;

    this.companyService.createDepartment(this.companyId, this.form.value).subscribe({
      next: (dept) => {
        this.departments.push(dept);
        this.form.reset();
        this.showForm = false;
        this.toastr.success('Département créé.');
      },
      error: () => this.toastr.error('Impossible de créer le département.'),
    });
  }

  toggleBudgets(dept: Department): void {
    if (this.expandedDeptId === dept.id) {
      this.expandedDeptId = null;
      return;
    }

    this.expandedDeptId = dept.id;
    if (!this.deptBudgets[dept.id]) {
      this.loadDeptBudgets(dept.id);
    }
  }

  loadDeptBudgets(deptId: number): void {
    this.budgetsLoading = true;
    this.budgetService.getDepartmentBudgets(deptId).subscribe({
      next: (budgets) => {
        if (budgets.length === 0) {
          this.deptBudgets[deptId] = [];
          this.budgetsLoading = false;
          return;
        }

        forkJoin(
          budgets.map(b => this.budgetService.getConsumption(b.id).pipe(catchError(() => of(null))))
        ).subscribe(results => {
          this.deptBudgets[deptId] = results.filter((r): r is BudgetConsumption => r !== null);
          this.budgetsLoading = false;
        });
      },
      error: () => { this.budgetsLoading = false; },
    });
  }

  createDeptBudget(dept: Department): void {
    if (this.budgetForm.invalid) return;

    const { period, year, month, amount } = this.budgetForm.value;
    this.budgetService.createDepartmentBudget(dept.id, {
      period,
      year,
      month: period === 'monthly' ? month : undefined,
      amount,
    }).subscribe({
      next: () => {
        this.budgetForm.reset({ period: 'monthly', year: new Date().getFullYear(), month: new Date().getMonth() + 1 });
        this.toastr.success('Budget créé.');
        this.loadDeptBudgets(dept.id);
      },
      error: () => this.toastr.error('Impossible de créer ce budget (existe-t-il déjà pour cette période ?).'),
    });
  }

  deleteBudget(deptId: number, item: BudgetConsumption): void {
    if (!confirm('Supprimer ce budget ?')) return;

    this.budgetService.delete(item.budget.id).subscribe({
      next: () => {
        this.deptBudgets[deptId] = this.deptBudgets[deptId].filter(b => b.budget.id !== item.budget.id);
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
