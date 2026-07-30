import { Component } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { CompanyService } from '../../../core/services/company.service';

@Component({
  selector: 'app-company-setup',
  templateUrl: './company-setup.component.html',
})
export class CompanySetupComponent {
  form: FormGroup;
  loading = false;

  constructor(
    private fb: FormBuilder,
    private companyService: CompanyService,
    private router: Router,
    private toastr: ToastrService,
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
      next: (company) => {
        this.toastr.success('Entreprise créée avec succès !');
        this.router.navigate(['/company', company.id, 'team']);
      },
      error: () => {
        this.loading = false;
        this.toastr.error('Une erreur est survenue.');
      },
    });
  }
}
