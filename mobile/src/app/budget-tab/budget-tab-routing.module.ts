import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { BudgetTabPage } from './budget-tab.page';

const routes: Routes = [
  {
    path: '',
    component: BudgetTabPage,
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class BudgetTabPageRoutingModule {}
