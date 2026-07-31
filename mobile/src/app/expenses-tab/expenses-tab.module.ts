import { IonicModule } from '@ionic/angular';
import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { ExpensesTabPage } from './expenses-tab.page';

import { ExpensesTabPageRoutingModule } from './expenses-tab-routing.module';

@NgModule({
  imports: [
    IonicModule,
    CommonModule,
    FormsModule,
    ReactiveFormsModule,
    RouterModule,
    ExpensesTabPageRoutingModule
  ],
  declarations: [ExpensesTabPage]
})
export class ExpensesTabPageModule {}
