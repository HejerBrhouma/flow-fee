import { Component, OnInit } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { CompanyService } from '../../../core/services/company.service';
import { Department } from '../../../core/models/company.model';

@Component({
  selector: 'app-departments',
  templateUrl: './departments.component.html',
})
export class DepartmentsComponent implements OnInit {
  companyId!: number;
  departments: Department[] = [];
  loading = true;
  showForm = false;

  form: FormGroup;

  constructor(
    private route: ActivatedRoute,
    private companyService: CompanyService,
    private fb: FormBuilder,
    private toastr: ToastrService,
  ) {
    this.form = this.fb.group({
      name: ['', Validators.required],
      description: [''],
      monthlyBudget: [null],
      yearlyBudget: [null],
    });
  }

  ngOnInit(): void {
    this.companyId = +this.route.snapshot.params['id'];
    this.companyService.getDepartments(this.companyId).subscribe({
      next: (depts) => { this.departments = depts; this.loading = false; },
      error: () => { this.loading = false; },
    });
  }

  create(): void {
    if (this.form.invalid) return;

    this.companyService.createDepartment(this.companyId, this.form.value).subscribe({
      next: (dept) => {
        this.departments.push(dept);
        this.form.reset();
        this.showForm = false;
        this.toastr.success('Département créé.');
      },
      error: () => this.toastr.error('Impossible de créer le département.'),
    });
  }
}
