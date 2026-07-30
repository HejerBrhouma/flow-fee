import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { ShellComponent } from './shell/shell.component';
import { NotificationBellComponent } from './notification-bell/notification-bell.component';

@NgModule({
  declarations: [ShellComponent, NotificationBellComponent],
  imports: [CommonModule, RouterModule],
  exports: [ShellComponent],
})
export class LayoutModule {}
