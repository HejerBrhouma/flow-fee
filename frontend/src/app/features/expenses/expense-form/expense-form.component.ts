import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { debounceTime } from 'rxjs/operators';
import { ExpenseService } from '../../../core/services/expense.service';
import { AuthService } from '../../../core/services/auth.service';
import { CategoryService } from '../../../core/services/category.service';
import { Expense } from '../../../core/models/expense.model';
import { Category } from '../../../core/models/category.model';
import { suggestCategory } from '../../../core/utils/suggest-category';
import { scanReceipt } from '../../../core/utils/receipt-ocr';

@Component({
  selector: 'app-expense-form',
  templateUrl: './expense-form.component.html',
})
export class ExpenseFormComponent implements OnInit {
  form: FormGroup;
  loading = false;
  isEdit = false;
  expenseId?: number;
  categories: Category[] = [];
  suggestedCategoryId: number | null = null;
  scanningReceipt = false;

  // Tracks whether the user explicitly touched the category select — once they have, the
  // title-based suggestion stops overriding their choice.
  private categoryManuallySet = false;

  constructor(
    private fb: FormBuilder,
    private expenseService: ExpenseService,
    private categoryService: CategoryService,
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
      categoryId: [null, Validators.required],
      departmentId: [null],
    });

    this.form.get('title')!.valueChanges.pipe(debounceTime(400)).subscribe(title => this.applySuggestion(title));
  }

  ngOnInit(): void {
    this.categoryService.getAll().subscribe(categories => this.categories = categories);

    this.expenseId = this.route.snapshot.params['id'];
    if (this.expenseId) {
      this.isEdit = true;
      this.categoryManuallySet = true; // editing an existing expense: never override its category
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

  onCategoryChange(): void {
    this.categoryManuallySet = true;
    this.suggestedCategoryId = null;
  }

  private applySuggestion(title: string): void {
    if (this.categoryManuallySet || !this.categories.length) return;

    const match = suggestCategory(title ?? '', this.categories);
    if (match) {
      this.form.get('categoryId')!.setValue(match.id, { emitEvent: false });
      this.suggestedCategoryId = match.id;
    }
  }

  async onReceiptSelected(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';
    if (!file) return;

    this.scanningReceipt = true;
    try {
      const parsed = await scanReceipt(file);
      const detected: string[] = [];

      if (parsed.merchant && !this.form.value.title) {
        this.form.get('title')!.setValue(parsed.merchant);
        detected.push('titre');
      }
      if (parsed.amount !== null) {
        this.form.get('amount')!.setValue(parsed.amount);
        detected.push('montant');
      }
      if (parsed.date) {
        this.form.get('expenseDate')!.setValue(parsed.date);
        detected.push('date');
      }

      if (detected.length) {
        this.toastr.success(`Détecté depuis le reçu : ${detected.join(', ')}. Vérifiez avant d'enregistrer.`);
      } else {
        this.toastr.warning("Aucune information n'a pu être extraite de ce reçu — remplissez le formulaire manuellement.");
      }
    } catch {
      this.toastr.error("Impossible d'analyser ce reçu.");
    } finally {
      this.scanningReceipt = false;
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
