import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { IonicModule } from '@ionic/angular';
import { SkeletonBlockComponent } from './skeleton-block/skeleton-block.component';
import { ErrorStateComponent } from './error-state/error-state.component';

@NgModule({
  declarations: [SkeletonBlockComponent, ErrorStateComponent],
  imports: [CommonModule, RouterModule, IonicModule],
  exports: [SkeletonBlockComponent, ErrorStateComponent],
})
export class SharedModule {}
