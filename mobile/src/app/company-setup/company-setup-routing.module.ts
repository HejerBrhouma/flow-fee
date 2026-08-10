import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { CompanySetupPage } from './company-setup.page';

const routes: Routes = [
  {
    path: '',
    component: CompanySetupPage,
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class CompanySetupPageRoutingModule {}
