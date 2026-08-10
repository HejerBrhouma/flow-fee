import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { AlertController, ToastController } from '@ionic/angular';
import { Camera, CameraResultType, CameraSource } from '@capacitor/camera';
import { AuthService } from '../core/services/auth.service';
import { CompanyService } from '../core/services/company.service';
import { UserCompany } from '../core/models/company.model';

const MAX_AVATAR_SIZE = 5 * 1024 * 1024; // 5 MB, mirrors the backend limit

@Component({
  selector: 'app-profile',
  templateUrl: './profile.page.html',
  styleUrls: ['./profile.page.scss'],
  standalone: false,
})
export class ProfilePage implements OnInit {
  editing = false;
  saving = false;
  uploadingAvatar = false;
  changingPassword = false;
  savingPassword = false;
  membership: UserCompany | null = null;
  membershipLoading = true;

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
    private companyService: CompanyService,
    private fb: FormBuilder,
    private alertController: AlertController,
    private toastController: ToastController,
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

  ngOnInit(): void {
    this.companyService.getMyMembership().subscribe({
      next: (membership) => { this.membership = membership; this.membershipLoading = false; },
      error: () => { this.membershipLoading = false; },
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
      next: async () => {
        this.saving = false;
        this.editing = false;
        await this.toast('Profil mis à jour.', 'success');
      },
      error: async () => {
        this.saving = false;
        await this.toast('Impossible de mettre à jour le profil.', 'danger');
      },
    });
  }

  async changeAvatar(): Promise<void> {
    let photo;
    try {
      photo = await Camera.getPhoto({
        quality: 80,
        resultType: CameraResultType.Uri,
        source: CameraSource.Prompt,
        promptLabelHeader: 'Photo de profil',
        promptLabelPhoto: 'Choisir dans la galerie',
        promptLabelPicture: 'Prendre une photo',
      });
    } catch {
      return; // user cancelled
    }

    if (!photo.webPath) return;

    const response = await fetch(photo.webPath);
    const blob = await response.blob();

    if (blob.size > MAX_AVATAR_SIZE) {
      await this.toast('Le fichier dépasse la taille maximale de 5 Mo.', 'danger');
      return;
    }

    const file = new File([blob], `avatar-${Date.now()}.${photo.format}`, { type: blob.type });

    this.uploadingAvatar = true;
    this.authService.uploadAvatar(file).subscribe({
      next: async () => {
        this.uploadingAvatar = false;
        await this.toast('Photo de profil mise à jour.', 'success');
      },
      error: async () => {
        this.uploadingAvatar = false;
        await this.toast('Impossible de mettre à jour la photo de profil.', 'danger');
      },
    });
  }

  removeAvatar(): void {
    this.authService.deleteAvatar().subscribe({
      next: async () => this.toast('Photo de profil supprimée.', 'success'),
      error: async () => this.toast('Impossible de supprimer la photo de profil.', 'danger'),
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
      next: async () => {
        this.savingPassword = false;
        this.changingPassword = false;
        await this.toast('Mot de passe mis à jour.', 'success');
      },
      error: async (err) => {
        this.savingPassword = false;
        await this.toast(err.error?.message ?? 'Impossible de changer le mot de passe.', 'danger');
      },
    });
  }

  async confirmLogout(): Promise<void> {
    const alert = await this.alertController.create({
      header: 'Se déconnecter ?',
      buttons: [
        { text: 'Annuler', role: 'cancel' },
        { text: 'Se déconnecter', role: 'destructive', handler: () => this.authService.logout() },
      ],
    });
    await alert.present();
  }

  private async toast(message: string, color: 'success' | 'danger'): Promise<void> {
    (await this.toastController.create({ message, duration: 2500, color, position: 'top' })).present();
  }
}
