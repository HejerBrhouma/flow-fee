import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { ExpenseService } from '../../../core/services/expense.service';
import { AuthService } from '../../../core/services/auth.service';
import { Expense } from '../../../core/models/expense.model';

@Component({
  selector: 'app-expense-form',
  templateUrl: './expense-form.component.html',
})
export class ExpenseFormComponent implements OnInit {
  form: FormGroup;
  loading = false;
  isEdit = false;
  expenseId?: number;

  constructor(
    private fb: FormBuilder,
    private expenseService: ExpenseService,
    private route: ActivatedRoute,
    private router: Router,
    private toastr: ToastrService,
    private authService: AuthService,
  ) {
    this.form = this.fb.group({
      title: ['', Validators.required],
      amount: ['', [Validators.required, Validators.min(0.01)]],
      currency: [this.authService.currentUser?.preferredCurrency ?? 'EUR', Validators.required],
      expenseDate: ['', Validators.required],
      description: [''],
      categoryId: [null],
      departmentId: [null],
    });
  }

  ngOnInit(): void {
    this.expenseId = this.route.snapshot.params['id'];
    if (this.expenseId) {
      this.isEdit = true;
      this.expenseService.getById(this.expenseId).subscribe((expense: Expense) => {
        this.form.patchValue({
          title: expense.title,
          amount: expense.amount,
          currency: expense.currency,
          expenseDate: expense.expenseDate.split('T')[0],
          description: expense.description,
          categoryId: expense.category?.id,
          departmentId: expense.department?.id,
        });
      });
    }
  }

  onSubmit(): void {
    if (this.form.invalid) return;
    this.loading = true;

    const action = this.isEdit
      ? this.expenseService.update(this.expenseId!, this.form.value)
      : this.expenseService.create(this.form.value);

    action.subscribe({
      next: (expense) => {
        this.toastr.success(this.isEdit ? 'Dépense mise à jour.' : 'Dépense créée.');
        this.router.navigate(['/expenses', expense.id]);
      },
      error: () => {
        this.loading = false;
        this.toastr.error('Une erreur est survenue.');
      },
    });
  }
}
