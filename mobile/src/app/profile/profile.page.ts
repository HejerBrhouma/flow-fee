import { Component } from '@angular/core';
import { AlertController } from '@ionic/angular';
import { AuthService } from '../core/services/auth.service';

@Component({
  selector: 'app-profile',
  templateUrl: './profile.page.html',
  styleUrls: ['./profile.page.scss'],
  standalone: false,
})
export class ProfilePage {
  constructor(
    public authService: AuthService,
    private alertController: AlertController,
  ) {}

  async confirmLogout(): Promise<void> {
    const alert = await this.alertController.create({
      header: 'Se déconnecter ?',
      buttons: [
        { text: 'Annuler', role: 'cancel' },
        { text: 'Se déconnecter', role: 'destructive', handler: () => this.authService.logout() },
      ],
    });
    await alert.present();
  }
}
