import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { TranslateModule } from '@ngx-translate/core';
import { ShellComponent } from './shell/shell.component';
import { NotificationBellComponent } from './notification-bell/notification-bell.component';
import { IconComponent } from './icon/icon.component';
import { AccountMenuComponent } from './account-menu/account-menu.component';
import { LanguageSwitcherComponent } from './language-switcher/language-switcher.component';
import { ThemeToggleComponent } from './theme-toggle/theme-toggle.component';

@NgModule({
  declarations: [ShellComponent, NotificationBellComponent, IconComponent, AccountMenuComponent, LanguageSwitcherComponent, ThemeToggleComponent],
  imports: [CommonModule, RouterModule, TranslateModule],
  exports: [ShellComponent, IconComponent],
})
export class LayoutModule {}
