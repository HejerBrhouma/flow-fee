import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { LayoutModule } from '../layout/layout.module';
import { SkeletonBlockComponent } from './skeleton-block/skeleton-block.component';
import { EmptyStateComponent } from './empty-state/empty-state.component';
import { ErrorStateComponent } from './error-state/error-state.component';

@NgModule({
  declarations: [SkeletonBlockComponent, EmptyStateComponent, ErrorStateComponent],
  imports: [CommonModule, RouterModule, LayoutModule],
  exports: [SkeletonBlockComponent, EmptyStateComponent, ErrorStateComponent],
})
export class SharedModule {}
