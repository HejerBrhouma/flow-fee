import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule, Routes } from '@angular/router';
import { ReactiveFormsModule } from '@angular/forms';
import { BaseChartDirective } from 'ng2-charts';

import { ReportsComponent } from './reports.component';

const routes: Routes = [
  { path: '', component: ReportsComponent },
];

@NgModule({
  declarations: [ReportsComponent],
  imports: [CommonModule, ReactiveFormsModule, RouterModule.forChild(routes), BaseChartDirective],
})
export class ReportsModule {}
