import { Component } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { AuthService } from '../../../core/services/auth.service';

@Component({
  selector: 'app-login',
  templateUrl: './login.component.html',
})
export class LoginComponent {
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
    private toastr: ToastrService,
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
        this.router.navigate(['/dashboard']);
      },
      error: (err) => {
        this.loading = false;
        this.toastr.error(err.error?.message ?? 'Email ou mot de passe incorrect.');
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
      next: () => this.router.navigate(['/dashboard']),
      error: (err) => {
        this.verifyingTwoFactor = false;
        this.toastr.error(err.error?.message ?? 'Code invalide.');
      },
    });
  }

  loginWithGoogle(): void {
    this.authService.loginWithGoogle().subscribe({
      next: () => this.router.navigate(['/dashboard']),
      error: () => this.toastr.error('Authentification Google échouée.'),
    });
  }

  loginWithFacebook(): void {
    this.authService.loginWithFacebook().subscribe({
      next: () => this.router.navigate(['/dashboard']),
      error: () => this.toastr.error('Authentification Facebook échouée.'),
    });
  }
}
