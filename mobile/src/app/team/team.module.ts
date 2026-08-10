import { IonicModule } from '@ionic/angular';
import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { TeamPage } from './team.page';

import { TeamPageRoutingModule } from './team-routing.module';

@NgModule({
  imports: [
    IonicModule,
    CommonModule,
    FormsModule,
    ReactiveFormsModule,
    TeamPageRoutingModule
  ],
  declarations: [TeamPage]
})
export class TeamPageModule {}
