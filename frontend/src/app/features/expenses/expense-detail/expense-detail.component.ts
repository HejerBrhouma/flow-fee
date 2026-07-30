import { Component, OnInit } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { ExpenseService } from '../../../core/services/expense.service';
import { AuthService } from '../../../core/services/auth.service';
import { Expense, ExpenseStatus } from '../../../core/models/expense.model';

@Component({
  selector: 'app-expense-detail',
  templateUrl: './expense-detail.component.html',
})
export class ExpenseDetailComponent implements OnInit {
  expense: Expense | null = null;
  loading = true;
  reviewComment = '';

  readonly statusLabels: Record<ExpenseStatus, string> = {
    draft: 'Brouillon',
    submitted: 'En attente de validation',
    approved: 'Approuvée',
    rejected: 'Rejetée',
  };

  constructor(
    private route: ActivatedRoute,
    private expenseService: ExpenseService,
    public authService: AuthService,
    private toastr: ToastrService,
  ) {}

  ngOnInit(): void {
    const id = +this.route.snapshot.params['id'];
    this.expenseService.getById(id).subscribe({
      next: (expense) => { this.expense = expense; this.loading = false; },
      error: () => { this.loading = false; },
    });
  }

  submit(): void {
    if (!this.expense) return;
    this.expenseService.submit(this.expense.id).subscribe({
      next: (e) => { this.expense = e; this.toastr.success('Dépense soumise pour validation.'); },
      error: () => this.toastr.error('Impossible de soumettre la dépense.'),
    });
  }

  approve(): void {
    if (!this.expense) return;
    this.expenseService.review(this.expense.id, { action: 'approve', comment: this.reviewComment }).subscribe({
      next: (e) => { this.expense = e; this.toastr.success('Dépense approuvée.'); },
      error: () => this.toastr.error('Impossible d\'approuver la dépense.'),
    });
  }

  reject(): void {
    if (!this.expense) return;
    this.expenseService.review(this.expense.id, { action: 'reject', comment: this.reviewComment }).subscribe({
      next: (e) => { this.expense = e; this.toastr.warning('Dépense rejetée.'); },
      error: () => this.toastr.error('Impossible de rejeter la dépense.'),
    });
  }
}
