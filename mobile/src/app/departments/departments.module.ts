import { IonicModule } from '@ionic/angular';
import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { DepartmentsPage } from './departments.page';

import { DepartmentsPageRoutingModule } from './departments-routing.module';

@NgModule({
  imports: [
    IonicModule,
    CommonModule,
    FormsModule,
    ReactiveFormsModule,
    DepartmentsPageRoutingModule
  ],
  declarations: [DepartmentsPage]
})
export class DepartmentsPageModule {}
