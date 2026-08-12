import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormsModule } from '@angular/forms';
import { RouterModule, Routes } from '@angular/router';
import { SharedModule } from '../../shared/shared.module';

import { CompanySetupComponent } from './company-setup/company-setup.component';
import { TeamComponent } from './team/team.component';
import { DepartmentsComponent } from './departments/departments.component';

const routes: Routes = [
  { path: 'setup', component: CompanySetupComponent },
  { path: ':id/team', component: TeamComponent },
  { path: ':id/departments', component: DepartmentsComponent },
];

@NgModule({
  declarations: [CompanySetupComponent, TeamComponent, DepartmentsComponent],
  imports: [CommonModule, ReactiveFormsModule, FormsModule, RouterModule.forChild(routes), SharedModule],
})
export class CompanyModule {}
