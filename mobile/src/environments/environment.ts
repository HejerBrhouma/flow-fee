// This file can be replaced during build by using the `fileReplacements` array.
// `ng build` replaces `environment.ts` with `environment.prod.ts`.
// The list of file replacements can be found in `angular.json`.

import { Capacitor } from '@capacitor/core';

// A compiled native app's webview always reports its own bundled origin (e.g.
// `capacitor://localhost`), never the phone's actual network address — so
// window.location.hostname can't be used to reach the dev machine there. Hardcode the
// Mac's current LAN IP for native builds instead; update it if the IP changes (check via
// `ipconfig getifaddr en0` on macOS). Browser/LAN-via-URL testing (`ionic serve`) still
// auto-detects via window.location.hostname, so this only matters for Xcode/Android Studio builds.
const DEV_MACHINE_LAN_IP = '192.168.100.61';

const apiHost = Capacitor.isNativePlatform()
  ? DEV_MACHINE_LAN_IP
  : (typeof window !== 'undefined' ? window.location.hostname : 'localhost');

export const environment = {
  production: false,
  apiUrl: `http://${apiHost}:8001/api`,
};

/*
 * For easier debugging in development mode, you can import the following file
 * to ignore zone related error stack frames such as `zone.run`, `zoneDelegate.invokeTask`.
 *
 * This import should be commented out in production mode because it will have a negative impact
 * on performance if an error is thrown.
 */
// import 'zone.js/plugins/zone-error';  // Included with Angular CLI.
