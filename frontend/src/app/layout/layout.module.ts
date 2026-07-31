import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { ShellComponent } from './shell/shell.component';
import { NotificationBellComponent } from './notification-bell/notification-bell.component';
import { IconComponent } from './icon/icon.component';
import { AccountMenuComponent } from './account-menu/account-menu.component';

@NgModule({
  declarations: [ShellComponent, NotificationBellComponent, IconComponent, AccountMenuComponent],
  imports: [CommonModule, RouterModule],
  exports: [ShellComponent],
})
export class LayoutModule {}
