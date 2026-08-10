import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { TranslateModule } from '@ngx-translate/core';
import { ShellComponent } from './shell/shell.component';
import { NotificationBellComponent } from './notification-bell/notification-bell.component';
import { IconComponent } from './icon/icon.component';
import { AccountMenuComponent } from './account-menu/account-menu.component';
import { LanguageSwitcherComponent } from './language-switcher/language-switcher.component';

@NgModule({
  declarations: [ShellComponent, NotificationBellComponent, IconComponent, AccountMenuComponent, LanguageSwitcherComponent],
  imports: [CommonModule, RouterModule, TranslateModule],
  exports: [ShellComponent],
})
export class LayoutModule {}
