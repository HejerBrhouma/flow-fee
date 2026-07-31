import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { ToastController } from '@ionic/angular';
import { ExpenseService } from '../../core/services/expense.service';
import { Expense } from '../../core/models/expense.model';

@Component({
  selector: 'app-expense-form',
  templateUrl: './expense-form.page.html',
  styleUrls: ['./expense-form.page.scss'],
  standalone: false,
})
export class ExpenseFormPage implements OnInit {
  form: FormGroup;
  loading = false;
  isEdit = false;
  expenseId?: number;

  constructor(
    private fb: FormBuilder,
    private expenseService: ExpenseService,
    private route: ActivatedRoute,
    private router: Router,
    private toastController: ToastController,
  ) {
    this.form = this.fb.group({
      title: ['', Validators.required],
      amount: ['', [Validators.required, Validators.min(0.01)]],
      currency: ['EUR', Validators.required],
      expenseDate: [new Date().toISOString(), Validators.required],
      description: [''],
    });
  }

  ngOnInit(): void {
    const id = this.route.snapshot.params['id'];
    if (id) {
      this.expenseId = +id;
      this.isEdit = true;
      this.expenseService.getById(this.expenseId).subscribe((expense: Expense) => {
        this.form.patchValue({
          title: expense.title,
          amount: expense.amount,
          currency: expense.currency,
          expenseDate: expense.expenseDate,
          description: expense.description,
        });
      });
    }
  }

  onSubmit(): void {
    if (this.form.invalid) return;
    this.loading = true;

    const payload = {
      ...this.form.value,
      expenseDate: this.form.value.expenseDate.split('T')[0],
    };

    const action = this.isEdit
      ? this.expenseService.update(this.expenseId!, payload)
      : this.expenseService.create(payload);

    action.subscribe({
      next: async (expense) => {
        const toast = await this.toastController.create({
          message: this.isEdit ? 'Dépense mise à jour.' : 'Dépense créée.',
          duration: 2000,
          color: 'success',
        });
        await toast.present();
        this.router.navigate(['/tabs/expenses', expense.id], { replaceUrl: true });
      },
      error: async () => {
        this.loading = false;
        const toast = await this.toastController.create({
          message: 'Une erreur est survenue.',
          duration: 2500,
          color: 'danger',
        });
        await toast.present();
      },
    });
  }
}
