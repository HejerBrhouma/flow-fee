import { Component } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { ToastController } from '@ionic/angular';
import { AuthService } from '../../core/services/auth.service';

@Component({
  selector: 'app-login',
  templateUrl: './login.page.html',
  styleUrls: ['./login.page.scss'],
  standalone: false,
})
export class LoginPage {
  form: FormGroup;
  loading = false;

  awaitingTwoFactor = false;
  verifyingTwoFactor = false;
  twoFactorCode = '';
  private challengeToken = '';

  constructor(
    private fb: FormBuilder,
    private authService: AuthService,
    private router: Router,
    private toastController: ToastController,
  ) {
    this.form = this.fb.group({
      email: ['', [Validators.required, Validators.email]],
      password: ['', Validators.required],
    });
  }

  onSubmit(): void {
    if (this.form.invalid) return;
    this.loading = true;

    this.authService.login(this.form.value).subscribe({
      next: (result) => {
        this.loading = false;
        if (result.requiresTwoFactor) {
          this.challengeToken = result.challengeToken;
          this.awaitingTwoFactor = true;
          return;
        }
        this.router.navigateByUrl('/tabs/dashboard');
      },
      error: async (err) => {
        this.loading = false;
        const toast = await this.toastController.create({
          message: err.error?.message ?? 'Email ou mot de passe incorrect.',
          duration: 3000,
          color: 'danger',
          position: 'top',
        });
        await toast.present();
      },
    });
  }

  cancelTwoFactor(): void {
    this.awaitingTwoFactor = false;
    this.twoFactorCode = '';
  }

  submitTwoFactor(): void {
    if (!this.twoFactorCode) return;
    this.verifyingTwoFactor = true;

    this.authService.verifyTwoFactor(this.challengeToken, this.twoFactorCode).subscribe({
      next: () => this.router.navigateByUrl('/tabs/dashboard'),
      error: async (err) => {
        this.verifyingTwoFactor = false;
        const toast = await this.toastController.create({
          message: err.error?.message ?? 'Code invalide.',
          duration: 3000,
          color: 'danger',
          position: 'top',
        });
        await toast.present();
      },
    });
  }

  loginWithGoogle(): void {
    this.authService.loginWithGoogle();
  }

  loginWithFacebook(): void {
    this.authService.loginWithFacebook();
  }
}
