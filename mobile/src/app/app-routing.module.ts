import { NgModule } from '@angular/core';
import { PreloadAllModules, RouterModule, Routes } from '@angular/router';
import { guestGuard } from './core/guards/auth.guard';

const routes: Routes = [
  {
    path: 'auth/login',
    canActivate: [guestGuard],
    loadChildren: () => import('./auth/login/login.module').then(m => m.LoginPageModule),
  },
  {
    path: 'auth/register',
    canActivate: [guestGuard],
    loadChildren: () => import('./auth/register/register.module').then(m => m.RegisterPageModule),
  },
  {
    path: '',
    loadChildren: () => import('./tabs/tabs.module').then(m => m.TabsPageModule),
  },
];

@NgModule({
  imports: [RouterModule.forRoot(routes, { preloadingStrategy: PreloadAllModules })],
  exports: [RouterModule],
})
export class AppRoutingModule {}
