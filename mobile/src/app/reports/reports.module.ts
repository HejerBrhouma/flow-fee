import { IonicModule } from '@ionic/angular';
import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { ReportsPage } from './reports.page';
import { SharedModule } from '../shared/shared.module';

import { ReportsPageRoutingModule } from './reports-routing.module';

@NgModule({
  imports: [
    IonicModule,
    CommonModule,
    FormsModule,
    ReactiveFormsModule,
    ReportsPageRoutingModule,
    SharedModule
  ],
  declarations: [ReportsPage]
})
export class ReportsPageModule {}
