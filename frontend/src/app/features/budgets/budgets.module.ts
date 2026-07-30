import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule } from '@angular/forms';
import { RouterModule, Routes } from '@angular/router';

import { BudgetsComponent } from './budgets.component';

const routes: Routes = [
  { path: '', component: BudgetsComponent },
];

@NgModule({
  declarations: [BudgetsComponent],
  imports: [CommonModule, ReactiveFormsModule, RouterModule.forChild(routes)],
})
export class BudgetsModule {}
