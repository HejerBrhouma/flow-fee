import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { AuthService } from '../../../core/services/auth.service';

@Component({
  selector: 'app-reset-password',
  templateUrl: './reset-password.component.html',
})
export class ResetPasswordComponent implements OnInit {
  form: FormGroup;
  loading = false;
  success = false;
  token: string | null = null;

  constructor(
    private fb: FormBuilder,
    private authService: AuthService,
    private route: ActivatedRoute,
    private router: Router,
    private toastr: ToastrService,
  ) {
    this.form = this.fb.group({
      newPassword: ['', [Validators.required, Validators.minLength(8)]],
      confirmPassword: ['', Validators.required],
    });
  }

  ngOnInit(): void {
    this.token = this.route.snapshot.queryParamMap.get('token');
  }

  get passwordsMismatch(): boolean {
    const { newPassword, confirmPassword } = this.form.value;
    return !!confirmPassword && newPassword !== confirmPassword;
  }

  onSubmit(): void {
    if (this.form.invalid || this.passwordsMismatch || !this.token) return;
    this.loading = true;

    this.authService.resetPassword(this.token, this.form.value.newPassword).subscribe({
      next: () => {
        this.loading = false;
        this.success = true;
        setTimeout(() => this.router.navigateByUrl('/auth/login'), 3000);
      },
      error: (err) => {
        this.loading = false;
        this.toastr.error(err.error?.message ?? 'Lien de réinitialisation invalide ou expiré.');
      },
    });
  }
}
