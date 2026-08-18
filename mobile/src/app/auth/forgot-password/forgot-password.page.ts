import { Component } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { AuthService } from '../../core/services/auth.service';

@Component({
  selector: 'app-forgot-password',
  templateUrl: './forgot-password.page.html',
  styleUrls: ['./forgot-password.page.scss'],
  standalone: false,
})
export class ForgotPasswordPage {
  form: FormGroup;
  loading = false;
  sent = false;

  constructor(private fb: FormBuilder, private authService: AuthService) {
    this.form = this.fb.group({
      email: ['', [Validators.required, Validators.email]],
    });
  }

  onSubmit(): void {
    if (this.form.invalid) return;
    this.loading = true;

    this.authService.forgotPassword(this.form.value.email).subscribe({
      // Backend always returns the same generic response whether or not the account
      // exists, so there's nothing to branch on here — just show the confirmation.
      next: () => { this.loading = false; this.sent = true; },
      error: () => { this.loading = false; this.sent = true; },
    });
  }
}
