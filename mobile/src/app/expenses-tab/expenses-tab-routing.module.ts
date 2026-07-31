import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { ExpensesTabPage } from './expenses-tab.page';

// expense-detail/expense-form are nested here (instead of top-level app routes) so they push
// onto this tab's own navigation stack and the bottom tab bar stays visible while browsing them.
const routes: Routes = [
  {
    path: '',
    component: ExpensesTabPage,
  },
  {
    path: 'new',
    loadChildren: () => import('../expenses/expense-form/expense-form.module').then(m => m.ExpenseFormPageModule),
  },
  {
    path: ':id/edit',
    loadChildren: () => import('../expenses/expense-form/expense-form.module').then(m => m.ExpenseFormPageModule),
  },
  {
    path: ':id',
    loadChildren: () => import('../expenses/expense-detail/expense-detail.module').then(m => m.ExpenseDetailPageModule),
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class ExpensesTabPageRoutingModule {}
