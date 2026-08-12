import { IonicModule } from '@ionic/angular';
import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { TeamPage } from './team.page';
import { SharedModule } from '../shared/shared.module';

import { TeamPageRoutingModule } from './team-routing.module';

@NgModule({
  imports: [
    IonicModule,
    CommonModule,
    FormsModule,
    ReactiveFormsModule,
    TeamPageRoutingModule,
    SharedModule
  ],
  declarations: [TeamPage]
})
export class TeamPageModule {}
