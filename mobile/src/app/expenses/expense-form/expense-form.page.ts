import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { ToastController } from '@ionic/angular';
import { debounceTime } from 'rxjs/operators';
import { Camera, CameraResultType, CameraSource } from '@capacitor/camera';
import { ExpenseService } from '../../core/services/expense.service';
import { NetworkService } from '../../core/services/network.service';
import { OfflineQueueService } from '../../core/services/offline-queue.service';
import { CategoryService } from '../../core/services/category.service';
import { Expense, ExpenseCreatePayload } from '../../core/models/expense.model';
import { Category } from '../../core/models/category.model';
import { suggestCategory } from '../../core/utils/suggest-category';
import { scanReceipt } from '../../core/utils/receipt-ocr';

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
  categories: Category[] = [];
  suggestedCategoryId: number | null = null;
  scanningReceipt = false;

  // Once the user explicitly touches the category select, the title-based suggestion
  // stops overriding their choice.
  private categoryManuallySet = false;

  constructor(
    private fb: FormBuilder,
    private expenseService: ExpenseService,
    private networkService: NetworkService,
    private offlineQueue: OfflineQueueService,
    private categoryService: CategoryService,
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
      categoryId: [null],
    });

    this.form.get('title')!.valueChanges.pipe(debounceTime(400)).subscribe(title => this.applySuggestion(title));
  }

  ngOnInit(): void {
    this.categoryService.getAll().subscribe(categories => this.categories = categories);

    const id = this.route.snapshot.params['id'];
    if (id) {
      this.expenseId = +id;
      this.isEdit = true;
      this.categoryManuallySet = true; // editing an existing expense: never override its category
      this.expenseService.getById(this.expenseId).subscribe((expense: Expense) => {
        this.form.patchValue({
          title: expense.title,
          amount: expense.amount,
          currency: expense.currency,
          expenseDate: expense.expenseDate,
          description: expense.description,
          categoryId: expense.category?.id ?? null,
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

  async scanReceipt(): Promise<void> {
    let photo;
    try {
      photo = await Camera.getPhoto({
        quality: 80,
        resultType: CameraResultType.Uri,
        source: CameraSource.Prompt,
        promptLabelHeader: 'Scanner un reçu',
        promptLabelPhoto: 'Choisir dans la galerie',
        promptLabelPicture: 'Prendre une photo',
      });
    } catch {
      return; // user cancelled
    }
    if (!photo.webPath) return;

    this.scanningReceipt = true;
    try {
      const response = await fetch(photo.webPath);
      const blob = await response.blob();
      const parsed = await scanReceipt(blob);
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

      const toast = await this.toastController.create({
        message: detected.length
          ? `Détecté depuis le reçu : ${detected.join(', ')}. Vérifiez avant d'enregistrer.`
          : "Aucune information n'a pu être extraite de ce reçu — remplissez le formulaire manuellement.",
        duration: 3500,
        color: detected.length ? 'success' : 'warning',
        position: 'top',
      });
      await toast.present();
    } catch {
      const toast = await this.toastController.create({
        message: "Impossible d'analyser ce reçu.",
        duration: 2500,
        color: 'danger',
        position: 'top',
      });
      await toast.present();
    } finally {
      this.scanningReceipt = false;
    }
  }

  onSubmit(): void {
    if (this.form.invalid) return;
    this.loading = true;

    const payload = {
      ...this.form.value,
      expenseDate: this.form.value.expenseDate.split('T')[0],
    };

    if (!this.isEdit && !this.networkService.isOnline) {
      this.submitOffline(payload);
      return;
    }

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
      error: async (err) => {
        this.loading = false;

        // status 0 means the request never reached the server (connection lost mid-submit) —
        // fall back to the offline queue instead of just failing outright.
        if (!this.isEdit && err.status === 0) {
          this.submitOffline(payload);
          return;
        }

        const toast = await this.toastController.create({
          message: 'Une erreur est survenue.',
          duration: 2500,
          color: 'danger',
        });
        await toast.present();
      },
    });
  }

  private async submitOffline(payload: ExpenseCreatePayload): Promise<void> {
    await this.offlineQueue.enqueue(payload);
    this.loading = false;

    const toast = await this.toastController.create({
      message: 'Pas de réseau — la dépense est enregistrée et sera synchronisée automatiquement.',
      duration: 3500,
      color: 'warning',
    });
    await toast.present();

    this.router.navigate(['/tabs/expenses'], { replaceUrl: true });
  }
}
