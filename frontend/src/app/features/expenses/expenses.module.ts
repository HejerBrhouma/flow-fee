import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormsModule } from '@angular/forms';
import { RouterModule, Routes } from '@angular/router';
import { NgxFileDropModule } from 'ngx-file-drop';
import { SharedModule } from '../../shared/shared.module';

import { ExpenseListComponent } from './expense-list/expense-list.component';
import { ExpenseFormComponent } from './expense-form/expense-form.component';
import { ExpenseDetailComponent } from './expense-detail/expense-detail.component';

const routes: Routes = [
  { path: '', component: ExpenseListComponent },
  { path: 'new', component: ExpenseFormComponent },
  { path: ':id', component: ExpenseDetailComponent },
  { path: ':id/edit', component: ExpenseFormComponent },
];

@NgModule({
  declarations: [ExpenseListComponent, ExpenseFormComponent, ExpenseDetailComponent],
  imports: [CommonModule, ReactiveFormsModule, FormsModule, RouterModule.forChild(routes), NgxFileDropModule, SharedModule],
})
export class ExpensesModule {}
