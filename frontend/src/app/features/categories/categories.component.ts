import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { CategoryService } from '../../core/services/category.service';
import { Category } from '../../core/models/category.model';

const COLOR_PRESETS = ['#6366f1', '#0ea5e9', '#84cc16', '#f59e0b', '#ef4444', '#ec4899', '#a855f7', '#94a3b8'];

@Component({
  selector: 'app-categories',
  templateUrl: './categories.component.html',
})
export class CategoriesComponent implements OnInit {
  categories: Category[] = [];
  loading = true;
  error = false;
  saving = false;
  showForm = false;
  editingId: number | null = null;

  readonly colorPresets = COLOR_PRESETS;
  form: FormGroup;

  constructor(
    private categoryService: CategoryService,
    private fb: FormBuilder,
    private toastr: ToastrService,
  ) {
    this.form = this.fb.group({
      name: ['', Validators.required],
      icon: ['🏷️'],
      color: [COLOR_PRESETS[0]],
    });
  }

  ngOnInit(): void {
    this.load();
  }

  load(): void {
    this.loading = true;
    this.error = false;
    this.categoryService.getAll().subscribe({
      next: (categories) => { this.categories = categories; this.loading = false; },
      error: () => { this.loading = false; this.error = true; },
    });
  }

  startCreate(): void {
    this.editingId = null;
    this.form.reset({ name: '', icon: '🏷️', color: COLOR_PRESETS[0] });
    this.showForm = true;
  }

  startEdit(category: Category): void {
    this.editingId = category.id;
    this.form.reset({
      name: category.name,
      icon: category.icon ?? '🏷️',
      color: category.color ?? COLOR_PRESETS[0],
    });
    this.showForm = true;
  }

  cancel(): void {
    this.showForm = false;
    this.editingId = null;
  }

  save(): void {
    if (this.form.invalid) return;
    this.saving = true;

    const payload = this.form.value;
    const request = this.editingId
      ? this.categoryService.update(this.editingId, payload)
      : this.categoryService.create(payload);

    request.subscribe({
      next: () => {
        this.saving = false;
        this.showForm = false;
        this.editingId = null;
        this.toastr.success(this.editingId ? 'Catégorie modifiée.' : 'Catégorie créée.');
        this.load();
      },
      error: (err) => {
        this.saving = false;
        this.toastr.error(err.error?.message ?? 'Une erreur est survenue.');
      },
    });
  }

  remove(category: Category): void {
    if (!confirm(`Supprimer la catégorie "${category.name}" ? Les dépenses qui l'utilisent deviendront sans catégorie.`)) return;

    this.categoryService.delete(category.id).subscribe({
      next: () => {
        this.categories = this.categories.filter(c => c.id !== category.id);
        this.toastr.success('Catégorie supprimée.');
      },
      error: (err) => this.toastr.error(err.error?.message ?? 'Impossible de supprimer cette catégorie.'),
    });
  }
}
