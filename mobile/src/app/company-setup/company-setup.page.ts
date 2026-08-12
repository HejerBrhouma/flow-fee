import { Component } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { ToastController } from '@ionic/angular';
import { combineLatest, firstValueFrom, of } from 'rxjs';
import { catchError, debounceTime, distinctUntilChanged, startWith, switchMap } from 'rxjs/operators';
import { CompanyService } from '../core/services/company.service';
import { AuthService } from '../core/services/auth.service';
import { COUNTRIES } from '../core/constants/countries';

type AddressCheckStatus = 'idle' | 'checking' | 'valid' | 'invalid' | 'unknown';

interface TaxIdRule {
  field: 'siret' | 'taxId';
  label: string;
  placeholder: string;
  hint: string;
}

// Mirrors the backend's CompanyController::TAX_ID_RULES — kept in sync manually since this
// is a small, fixed list (the server validates authoritatively either way).
const TAX_ID_RULES: Record<string, TaxIdRule> = {
  France: { field: 'siret', label: 'SIRET', placeholder: '12345678901234', hint: '14 chiffres.' },
  Tunisie: { field: 'taxId', label: 'Matricule fiscal', placeholder: '1234567AAM000', hint: '7 chiffres, 3 lettres, 3 chiffres.' },
};

@Component({
  selector: 'app-company-setup',
  templateUrl: './company-setup.page.html',
  styleUrls: ['./company-setup.page.scss'],
  standalone: false,
})
export class CompanySetupPage {
  form: FormGroup;
  loading = false;
  addressCheck: AddressCheckStatus = 'idle';

  readonly countries = COUNTRIES;

  constructor(
    private fb: FormBuilder,
    private companyService: CompanyService,
    private authService: AuthService,
    private router: Router,
    private toastController: ToastController,
  ) {
    this.form = this.fb.group({
      name: ['', Validators.required],
      country: ['France', Validators.required],
      siret: [''],
      taxId: [''],
      address: [''],
      city: [''],
      zipCode: [''],
    });

    combineLatest([
      this.form.get('country')!.valueChanges.pipe(startWith(this.form.value.country)),
      this.form.get('city')!.valueChanges.pipe(startWith(this.form.value.city)),
      this.form.get('zipCode')!.valueChanges.pipe(startWith(this.form.value.zipCode)),
    ]).pipe(
      debounceTime(600),
      distinctUntilChanged((a, b) => JSON.stringify(a) === JSON.stringify(b)),
      switchMap(([country, city, zipCode]) => {
        if (!city?.trim() || !zipCode?.trim()) {
          this.addressCheck = 'idle';
          return [];
        }
        this.addressCheck = 'checking';
        return this.companyService.verifyAddress(country, city, zipCode).pipe(
          catchError(() => of({ valid: null })),
        );
      }),
    ).subscribe((result) => {
      this.addressCheck = result.valid === null ? 'unknown' : result.valid ? 'valid' : 'invalid';
    });
  }

  get taxIdRule(): TaxIdRule | null {
    return TAX_ID_RULES[this.form.value.country] ?? null;
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
      error: async (err) => {
        this.loading = false;
        const errors = err.error?.errors;
        const message = errors ? Object.values(errors)[0] as string : 'Une erreur est survenue.';
        (await this.toastController.create({
          message, duration: 3000, color: 'danger', position: 'top',
        })).present();
      },
    });
  }
}
