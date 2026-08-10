import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule, Routes } from '@angular/router';
import { BaseChartDirective } from 'ng2-charts';
import { TranslateModule } from '@ngx-translate/core';

import { DashboardComponent } from './dashboard.component';

const routes: Routes = [
  { path: '', component: DashboardComponent },
];

@NgModule({
  declarations: [DashboardComponent],
  imports: [CommonModule, RouterModule.forChild(routes), BaseChartDirective, TranslateModule],
})
export class DashboardModule {}
