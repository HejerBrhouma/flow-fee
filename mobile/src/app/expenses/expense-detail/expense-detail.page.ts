import { Component, OnInit } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { AlertController, ToastController } from '@ionic/angular';
import { Camera, CameraResultType, CameraSource } from '@capacitor/camera';
import { ExpenseService } from '../../core/services/expense.service';
import { AuthService } from '../../core/services/auth.service';
import { Expense, ExpenseReceipt, ExpenseStatus } from '../../core/models/expense.model';

const MAX_RECEIPT_SIZE = 10 * 1024 * 1024; // 10 MB, mirrors the backend limit

@Component({
  selector: 'app-expense-detail',
  templateUrl: './expense-detail.page.html',
  styleUrls: ['./expense-detail.page.scss'],
  standalone: false,
})
export class ExpenseDetailPage implements OnInit {
  expense: Expense | null = null;
  loading = true;
  error = false;
  reviewComment = '';
  uploading = false;
  private expenseId!: number;

  readonly statusLabels: Record<ExpenseStatus, string> = {
    draft: 'Brouillon',
    submitted: 'En attente de validation',
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
    private route: ActivatedRoute,
    private expenseService: ExpenseService,
    public authService: AuthService,
    private toastController: ToastController,
    private alertController: AlertController,
  ) {}

  ngOnInit(): void {
    this.expenseId = +this.route.snapshot.params['id'];
    this.load();
  }

  load(): void {
    this.loading = true;
    this.error = false;
    this.expenseService.getById(this.expenseId).subscribe({
      next: (expense) => { this.expense = expense; this.loading = false; },
      error: () => { this.loading = false; this.error = true; },
    });
  }

  /**
   * Personal expenses have no manager to approve them, so their owner stays fully
   * autonomous regardless of status. Only company expenses lock once submitted for review.
   */
  get canEdit(): boolean {
    return !this.expense?.department || this.expense?.status === 'draft';
  }

  submit(): void {
    if (!this.expense) return;
    this.expenseService.submit(this.expense.id).subscribe({
      next: async (e) => { this.expense = e; await this.notify('Dépense soumise pour validation.', 'success'); },
      error: async () => this.notify('Impossible de soumettre la dépense.', 'danger'),
    });
  }

  approve(): void {
    if (!this.expense) return;
    this.expenseService.review(this.expense.id, { action: 'approve', comment: this.reviewComment }).subscribe({
      next: async (e) => { this.expense = e; await this.notify('Dépense approuvée.', 'success'); },
      error: async () => this.notify('Impossible d\'approuver la dépense.', 'danger'),
    });
  }

  reject(): void {
    if (!this.expense) return;
    this.expenseService.review(this.expense.id, { action: 'reject', comment: this.reviewComment }).subscribe({
      next: async (e) => { this.expense = e; await this.notify('Dépense rejetée.', 'warning'); },
      error: async () => this.notify('Impossible de rejeter la dépense.', 'danger'),
    });
  }

  async captureReceipt(): Promise<void> {
    if (!this.expense) return;

    let photo;
    try {
      photo = await Camera.getPhoto({
        quality: 80,
        resultType: CameraResultType.Uri,
        source: CameraSource.Prompt,
        promptLabelHeader: 'Justificatif',
        promptLabelPhoto: 'Choisir dans la galerie',
        promptLabelPicture: 'Prendre une photo',
      });
    } catch {
      return; // user cancelled
    }

    if (!photo.webPath) return;

    const response = await fetch(photo.webPath);
    const blob = await response.blob();

    if (blob.size > MAX_RECEIPT_SIZE) {
      await this.notify('Le fichier dépasse la taille maximale de 10 Mo.', 'danger');
      return;
    }

    const file = new File([blob], `receipt-${Date.now()}.${photo.format}`, { type: blob.type });
    this.uploadReceipt(file);
  }

  private uploadReceipt(file: File): void {
    if (!this.expense) return;

    this.uploading = true;
    this.expenseService.uploadReceipt(this.expense.id, file).subscribe({
      next: async (e) => {
        this.expense = e;
        this.uploading = false;
        await this.notify('Justificatif ajouté.', 'success');
      },
      error: async (err) => {
        this.uploading = false;
        await this.notify(err.error?.message ?? 'Impossible d\'ajouter ce justificatif.', 'danger');
      },
    });
  }

  async deleteReceipt(receipt: ExpenseReceipt): Promise<void> {
    if (!this.expense) return;

    const alert = await this.alertController.create({
      header: 'Supprimer ce justificatif ?',
      buttons: [
        { text: 'Annuler', role: 'cancel' },
        {
          text: 'Supprimer',
          role: 'destructive',
          handler: () => this.confirmDeleteReceipt(receipt),
        },
      ],
    });
    await alert.present();
  }

  private confirmDeleteReceipt(receipt: ExpenseReceipt): void {
    if (!this.expense) return;

    this.expenseService.deleteReceipt(this.expense.id, receipt.id).subscribe({
      next: async () => {
        if (this.expense) {
          this.expense.receipts = this.expense.receipts.filter(r => r.id !== receipt.id);
        }
        await this.notify('Justificatif supprimé.', 'success');
      },
      error: async () => this.notify('Impossible de supprimer ce justificatif.', 'danger'),
    });
  }

  private async notify(message: string, color: 'success' | 'warning' | 'danger'): Promise<void> {
    const toast = await this.toastController.create({ message, duration: 2500, color, position: 'top' });
    await toast.present();
  }
}
