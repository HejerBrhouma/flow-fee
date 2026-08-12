import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';

import { IonicModule } from '@ionic/angular';

import { ExpenseDetailPageRoutingModule } from './expense-detail-routing.module';

import { ExpenseDetailPage } from './expense-detail.page';
import { SharedModule } from '../../shared/shared.module';

@NgModule({
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    IonicModule,
    ExpenseDetailPageRoutingModule,
    SharedModule
  ],
  declarations: [ExpenseDetailPage]
})
export class ExpenseDetailPageModule {}
