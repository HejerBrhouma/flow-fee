import { IonicModule } from '@ionic/angular';
import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { BudgetTabPage } from './budget-tab.page';
import { SharedModule } from '../shared/shared.module';

import { BudgetTabPageRoutingModule } from './budget-tab-routing.module';

@NgModule({
  imports: [
    IonicModule,
    CommonModule,
    FormsModule,
    ReactiveFormsModule,
    BudgetTabPageRoutingModule,
    SharedModule
  ],
  declarations: [BudgetTabPage]
})
export class BudgetTabPageModule {}
