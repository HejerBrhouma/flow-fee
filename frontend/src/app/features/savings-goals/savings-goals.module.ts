import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { RouterModule, Routes } from '@angular/router';
import { SharedModule } from '../../shared/shared.module';

import { SavingsGoalsComponent } from './savings-goals.component';

const routes: Routes = [
  { path: '', component: SavingsGoalsComponent },
];

@NgModule({
  declarations: [SavingsGoalsComponent],
  imports: [CommonModule, FormsModule, ReactiveFormsModule, RouterModule.forChild(routes), SharedModule],
})
export class SavingsGoalsModule {}
