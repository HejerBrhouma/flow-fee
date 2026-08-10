import { NgModule } from '@angular/core';
import { Routes, RouterModule } from '@angular/router';

import { ProfilePage } from './profile.page';

const routes: Routes = [
  {
    path: '',
    component: ProfilePage
  },
  {
    path: 'company-setup',
    loadChildren: () => import('../company-setup/company-setup.module').then(m => m.CompanySetupPageModule),
  },
  {
    path: 'company/:id/team',
    loadChildren: () => import('../team/team.module').then(m => m.TeamPageModule),
  },
  {
    path: 'company/:id/departments',
    loadChildren: () => import('../departments/departments.module').then(m => m.DepartmentsPageModule),
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class ProfilePageRoutingModule {}
