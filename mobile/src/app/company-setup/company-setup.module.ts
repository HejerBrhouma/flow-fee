import { IonicModule } from '@ionic/angular';
import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { CompanySetupPage } from './company-setup.page';

import { CompanySetupPageRoutingModule } from './company-setup-routing.module';

@NgModule({
  imports: [
    IonicModule,
    CommonModule,
    FormsModule,
    ReactiveFormsModule,
    CompanySetupPageRoutingModule
  ],
  declarations: [CompanySetupPage]
})
export class CompanySetupPageModule {}
