import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { DepartmentsPage } from './departments.page';

const routes: Routes = [
  {
    path: '',
    component: DepartmentsPage,
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class DepartmentsPageRoutingModule {}
