import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule } from '@angular/forms';
import { RouterModule, Routes } from '@angular/router';
import { SharedModule } from '../../shared/shared.module';

import { CategoriesComponent } from './categories.component';

const routes: Routes = [
  { path: '', component: CategoriesComponent },
];

@NgModule({
  declarations: [CategoriesComponent],
  imports: [CommonModule, ReactiveFormsModule, RouterModule.forChild(routes), SharedModule],
})
export class CategoriesModule {}
