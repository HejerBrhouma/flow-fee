import { Component } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { AuthService } from '../../core/services/auth.service';

const MAX_AVATAR_SIZE = 5 * 1024 * 1024; // 5 MB, mirrors the backend limit
const ALLOWED_AVATAR_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

@Component({
  selector: 'app-profile',
  templateUrl: './profile.component.html',
})
export class ProfileComponent {
  editing = false;
  saving = false;
  uploadingAvatar = false;
  changingPassword = false;
  savingPassword = false;

  form: FormGroup;
  passwordForm: FormGroup;

  readonly currencies = [
    { value: 'EUR', label: 'EUR €' },
    { value: 'USD', label: 'USD $' },
    { value: 'GBP', label: 'GBP £' },
    { value: 'TND', label: 'TND د.ت' },
  ];

  constructor(
    public authService: AuthService,
    private fb: FormBuilder,
    private toastr: ToastrService,
  ) {
    this.form = this.fb.group({
      firstName: ['', Validators.required],
      lastName: ['', Validators.required],
      phone: [''],
      preferredCurrency: ['EUR'],
    });

    this.passwordForm = this.fb.group({
      currentPassword: ['', Validators.required],
      newPassword: ['', [Validators.required, Validators.minLength(8)]],
    });
  }

  startEditing(): void {
    const user = this.authService.currentUser;
    this.form.reset({
      firstName: user?.firstName ?? '',
      lastName: user?.lastName ?? '',
      phone: user?.phone ?? '',
      preferredCurrency: user?.preferredCurrency ?? 'EUR',
    });
    this.editing = true;
  }

  cancelEditing(): void {
    this.editing = false;
  }

  save(): void {
    if (this.form.invalid) return;
    this.saving = true;

    this.authService.updateProfile(this.form.value).subscribe({
      next: () => {
        this.saving = false;
        this.editing = false;
        this.toastr.success('Profil mis à jour.');
      },
      error: () => {
        this.saving = false;
        this.toastr.error('Impossible de mettre à jour le profil.');
      },
    });
  }

  onAvatarSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';
    if (!file) return;

    if (!ALLOWED_AVATAR_TYPES.includes(file.type)) {
      this.toastr.error('Format non supporté (JPEG, PNG ou WEBP uniquement).');
      return;
    }
    if (file.size > MAX_AVATAR_SIZE) {
      this.toastr.error('Le fichier dépasse la taille maximale de 5 Mo.');
      return;
    }

    this.uploadingAvatar = true;
    this.authService.uploadAvatar(file).subscribe({
      next: () => {
        this.uploadingAvatar = false;
        this.toastr.success('Photo de profil mise à jour.');
      },
      error: () => {
        this.uploadingAvatar = false;
        this.toastr.error('Impossible de mettre à jour la photo de profil.');
      },
    });
  }

  removeAvatar(): void {
    this.authService.deleteAvatar().subscribe({
      next: () => this.toastr.success('Photo de profil supprimée.'),
      error: () => this.toastr.error('Impossible de supprimer la photo de profil.'),
    });
  }

  startChangingPassword(): void {
    this.passwordForm.reset();
    this.changingPassword = true;
  }

  cancelChangingPassword(): void {
    this.changingPassword = false;
  }

  savePassword(): void {
    if (this.passwordForm.invalid) return;
    this.savingPassword = true;

    this.authService.changePassword(this.passwordForm.value).subscribe({
      next: () => {
        this.savingPassword = false;
        this.changingPassword = false;
        this.toastr.success('Mot de passe mis à jour.');
      },
      error: (err) => {
        this.savingPassword = false;
        this.toastr.error(err.error?.message ?? 'Impossible de changer le mot de passe.');
      },
    });
  }
}
