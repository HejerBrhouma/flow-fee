import { Component, OnInit } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { NgxFileDropEntry, FileSystemFileEntry } from 'ngx-file-drop';
import { ExpenseService } from '../../../core/services/expense.service';
import { AuthService } from '../../../core/services/auth.service';
import { Expense, ExpenseReceipt, ExpenseStatus } from '../../../core/models/expense.model';

const MAX_RECEIPT_SIZE = 10 * 1024 * 1024; // 10 MB, mirrors the backend limit
const ALLOWED_RECEIPT_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

@Component({
  selector: 'app-expense-detail',
  templateUrl: './expense-detail.component.html',
})
export class ExpenseDetailComponent implements OnInit {
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

  constructor(
    private route: ActivatedRoute,
    private expenseService: ExpenseService,
    public authService: AuthService,
    private toastr: ToastrService,
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

  dropped(files: NgxFileDropEntry[]): void {
    for (const droppedFile of files) {
      if (!droppedFile.fileEntry.isFile) continue;

      const fileEntry = droppedFile.fileEntry as FileSystemFileEntry;
      fileEntry.file((file: File) => this.uploadReceipt(file));
    }
  }

  uploadReceipt(file: File): void {
    if (!this.expense) return;

    if (!ALLOWED_RECEIPT_TYPES.includes(file.type)) {
      this.toastr.error('Format non supporté (JPEG, PNG, WEBP ou PDF uniquement).');
      return;
    }
    if (file.size > MAX_RECEIPT_SIZE) {
      this.toastr.error('Le fichier dépasse la taille maximale de 10 Mo.');
      return;
    }

    this.uploading = true;
    this.expenseService.uploadReceipt(this.expense.id, file).subscribe({
      next: (e) => {
        this.expense = e;
        this.uploading = false;
        this.toastr.success('Justificatif ajouté.');
      },
      error: (err) => {
        this.uploading = false;
        this.toastr.error(err.error?.message ?? 'Impossible d\'ajouter ce justificatif.');
      },
    });
  }

  deleteReceipt(receipt: ExpenseReceipt): void {
    if (!this.expense) return;
    if (!confirm('Supprimer ce justificatif ?')) return;

    this.expenseService.deleteReceipt(this.expense.id, receipt.id).subscribe({
      next: () => {
        if (this.expense) {
          this.expense.receipts = this.expense.receipts.filter(r => r.id !== receipt.id);
        }
        this.toastr.success('Justificatif supprimé.');
      },
      error: () => this.toastr.error('Impossible de supprimer ce justificatif.'),
    });
  }
}
