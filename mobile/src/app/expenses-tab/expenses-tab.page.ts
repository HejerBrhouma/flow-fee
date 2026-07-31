import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup } from '@angular/forms';
import { ToastController } from '@ionic/angular';
import { ExpenseService } from '../core/services/expense.service';
import { Expense, ExpenseFilters, ExpenseStatus } from '../core/models/expense.model';

@Component({
  selector: 'app-expenses-tab',
  templateUrl: 'expenses-tab.page.html',
  styleUrls: ['expenses-tab.page.scss'],
  standalone: false,
})
export class ExpensesTabPage implements OnInit {
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
    draft: 'medium',
    submitted: 'warning',
    approved: 'success',
    rejected: 'danger',
  };

  constructor(
    private expenseService: ExpenseService,
    private fb: FormBuilder,
    private toastController: ToastController,
  ) {
    this.filterForm = this.fb.group({ status: [''] });
  }

  ngOnInit(): void {
    this.load();
  }

  ionViewWillEnter(): void {
    this.applyFilters();
  }

  load(event?: CustomEvent): void {
    this.loading = !event;
    const filters: ExpenseFilters = { ...this.filterForm.value, page: this.page };
    if (!filters.status) delete filters.status;

    this.expenseService.getAll(filters).subscribe({
      next: (res) => {
        this.expenses = res.items;
        this.total = res.total;
        this.pages = res.pages;
        this.loading = false;
        (event?.target as HTMLIonRefresherElement | undefined)?.complete();
      },
      error: () => {
        this.loading = false;
        (event?.target as HTMLIonRefresherElement | undefined)?.complete();
      },
    });
  }

  applyFilters(): void {
    this.page = 1;
    this.load();
  }

  /**
   * Personal expenses have no manager to approve them, so their owner stays fully
   * autonomous regardless of status. Only company expenses lock once submitted for review.
   */
  canEdit(expense: Expense): boolean {
    return !expense.department || expense.status === 'draft';
  }

  loadMore(event: CustomEvent): void {
    if (this.page >= this.pages) {
      (event.target as HTMLIonInfiniteScrollElement).complete();
      return;
    }

    this.page++;
    const filters: ExpenseFilters = { ...this.filterForm.value, page: this.page };
    if (!filters.status) delete filters.status;

    this.expenseService.getAll(filters).subscribe({
      next: (res) => {
        this.expenses = [...this.expenses, ...res.items];
        this.pages = res.pages;
        (event.target as HTMLIonInfiniteScrollElement).complete();
      },
      error: () => (event.target as HTMLIonInfiniteScrollElement).complete(),
    });
  }

  async delete(expense: Expense, event: Event): Promise<void> {
    event.stopPropagation();

    this.expenseService.delete(expense.id).subscribe({
      next: async () => {
        this.expenses = this.expenses.filter(e => e.id !== expense.id);
        const toast = await this.toastController.create({
          message: 'Dépense supprimée.',
          duration: 2000,
          color: 'success',
        });
        await toast.present();
      },
      error: async () => {
        const toast = await this.toastController.create({
          message: 'Impossible de supprimer cette dépense.',
          duration: 2500,
          color: 'danger',
        });
        await toast.present();
      },
    });
  }
}
