import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'com.flowfee.app',
  appName: 'Flow Fee',
  webDir: 'www',
  // Default (https://localhost) makes the WebView block XHR to the plain-HTTP dev backend
  // as mixed content. There's no hosted HTTPS backend yet (see environment*.ts), so serve
  // the app itself over http:// to match.
  server: {
    androidScheme: 'http',
  },
};

export default config;
