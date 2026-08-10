import { Component } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { ToastController } from '@ionic/angular';
import { firstValueFrom } from 'rxjs';
import { CompanyService } from '../core/services/company.service';
import { AuthService } from '../core/services/auth.service';

@Component({
  selector: 'app-company-setup',
  templateUrl: './company-setup.page.html',
  styleUrls: ['./company-setup.page.scss'],
  standalone: false,
})
export class CompanySetupPage {
  form: FormGroup;
  loading = false;

  constructor(
    private fb: FormBuilder,
    private companyService: CompanyService,
    private authService: AuthService,
    private router: Router,
    private toastController: ToastController,
  ) {
    this.form = this.fb.group({
      name: ['', Validators.required],
      siret: [''],
      address: [''],
      city: [''],
      zipCode: [''],
      country: ['France'],
    });
  }

  onSubmit(): void {
    if (this.form.invalid) return;
    this.loading = true;

    this.companyService.create(this.form.value).subscribe({
      next: async (company) => {
        await firstValueFrom(this.authService.fetchCurrentUser());
        (await this.toastController.create({
          message: 'Entreprise créée avec succès !', duration: 2500, color: 'success', position: 'top',
        })).present();
        this.router.navigateByUrl(`/tabs/profile/company/${company.id}/team`);
      },
      error: async () => {
        this.loading = false;
        (await this.toastController.create({
          message: 'Une erreur est survenue.', duration: 3000, color: 'danger', position: 'top',
        })).present();
      },
    });
  }
}
