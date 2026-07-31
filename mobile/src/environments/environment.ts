// This file can be replaced during build by using the `fileReplacements` array.
// `ng build` replaces `environment.ts` with `environment.prod.ts`.
// The list of file replacements can be found in `angular.json`.

// The API host follows whatever host the app itself was loaded from, so the same build
// works for the browser preview (localhost:8100), a phone on the LAN (e.g. 192.168.x.x:8100)
// and the iOS simulator, without editing this file every time the dev machine's IP changes.
// This does NOT cover the Android emulator: its webview reports its own `localhost`, which
// never reaches the host machine. Hardcode `apiHost = '10.0.2.2'` below when testing there.
const apiHost = typeof window !== 'undefined' ? window.location.hostname : 'localhost';

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
