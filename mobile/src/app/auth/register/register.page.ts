import { Component } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { ToastController } from '@ionic/angular';
import { AuthService } from '../../core/services/auth.service';

@Component({
  selector: 'app-register',
  templateUrl: './register.page.html',
  styleUrls: ['./register.page.scss'],
  standalone: false,
})
export class RegisterPage {
  form: FormGroup;
  loading = false;

  constructor(
    private fb: FormBuilder,
    private authService: AuthService,
    private router: Router,
    private toastController: ToastController,
  ) {
    this.form = this.fb.group({
      firstName: ['', Validators.required],
      lastName: ['', Validators.required],
      email: ['', [Validators.required, Validators.email]],
      password: ['', [Validators.required, Validators.minLength(8)]],
      type: ['personal', Validators.required],
    });
  }

  onSubmit(): void {
    if (this.form.invalid) return;
    this.loading = true;

    this.authService.register(this.form.value).subscribe({
      next: async () => {
        const toast = await this.toastController.create({
          message: 'Compte créé avec succès !',
          duration: 2500,
          color: 'success',
          position: 'top',
        });
        await toast.present();
        this.router.navigateByUrl('/tabs/dashboard');
      },
      error: async (err) => {
        this.loading = false;
        const toast = await this.toastController.create({
          message: err.error?.message ?? 'Une erreur est survenue.',
          duration: 3000,
          color: 'danger',
          position: 'top',
        });
        await toast.present();
      },
    });
  }
}
