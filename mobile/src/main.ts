import { platformBrowserDynamic } from '@angular/platform-browser-dynamic';
import { defineCustomElements } from '@ionic/pwa-elements/loader';

import { AppModule } from './app/app.module';

platformBrowserDynamic().bootstrapModule(AppModule)
  .catch(err => console.log(err));

// Provides web fallbacks (camera action sheet, etc.) for Capacitor plugins when
// running in a plain browser (dev preview / PWA) instead of a native shell.
defineCustomElements(window);
