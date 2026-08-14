import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { IonicModule } from '@ionic/angular';
import { SkeletonBlockComponent } from './skeleton-block/skeleton-block.component';
import { ErrorStateComponent } from './error-state/error-state.component';
import { OfflineBannerComponent } from './offline-banner/offline-banner.component';

@NgModule({
  declarations: [SkeletonBlockComponent, ErrorStateComponent, OfflineBannerComponent],
  imports: [CommonModule, RouterModule, IonicModule],
  exports: [SkeletonBlockComponent, ErrorStateComponent, OfflineBannerComponent],
})
export class SharedModule {}
