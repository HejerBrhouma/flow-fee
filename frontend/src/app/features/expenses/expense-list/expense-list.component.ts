import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ExpenseService } from '../../../core/services/expense.service';
import { Expense, ExpenseFilters, ExpenseStatus } from '../../../core/models/expense.model';

@Component({
  selector: 'app-expense-list',
  templateUrl: './expense-list.component.html',
})
export class ExpenseListComponent implements OnInit {
  expenses: Expense[] = [];
  total = 0;
  page = 1;
  pages = 1;
  loading = true;

  filterForm: FormGroup;

  readonly statusLabels: Record<ExpenseStatus, string> = {
    draft: 'Brouillon',
    submitted: 'En attente',
    approved: 'Approuvée',
    rejected: 'Rejetée',
  };

  readonly statusColors: Record<ExpenseStatus, string> = {
    draft: 'bg-gray-100 text-gray-700',
    submitted: 'bg-amber-100 text-amber-700',
    approved: 'bg-green-100 text-green-700',
    rejected: 'bg-red-100 text-red-700',
  };

  constructor(
    private expenseService: ExpenseService,
    private fb: FormBuilder,
    private toastr: ToastrService,
  ) {
    this.filterForm = this.fb.group({
      status: [''],
      dateFrom: [''],
      dateTo: [''],
    });
  }

  ngOnInit(): void {
    this.load();
  }

  load(): void {
    this.loading = true;
    const filters: ExpenseFilters = {
      ...this.filterForm.value,
      page: this.page,
    };

    this.expenseService.getAll(filters).subscribe({
      next: (res) => {
        this.expenses = res.items;
        this.total = res.total;
        this.pages = res.pages;
        this.loading = false;
      },
      error: () => { this.loading = false; },
    });
  }

  applyFilters(): void {
    this.page = 1;
    this.load();
  }

  changePage(p: number): void {
    this.page = p;
    this.load();
  }

  delete(expense: Expense): void {
    if (!confirm(`Supprimer "${expense.title}" ?`)) return;

    this.expenseService.delete(expense.id).subscribe({
      next: () => {
        this.expenses = this.expenses.filter(e => e.id !== expense.id);
        this.toastr.success('Dépense supprimée.');
      },
      error: () => this.toastr.error('Impossible de supprimer cette dépense.'),
    });
  }
}
