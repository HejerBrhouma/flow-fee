// Used for native Release builds (Xcode/Android Studio "Release" scheme). There's no
// separate hosted production backend yet, so this points at the same local Docker backend
// as environment.ts — update DEV_MACHINE_LAN_IP if the dev machine's IP changes (check via
// `ipconfig getifaddr en0` on macOS).
const DEV_MACHINE_LAN_IP = 'YOUR_DEV_MACHINE_LAN_IP'; // e.g. 192.168.1.42

export const environment = {
  production: true,
  apiUrl: `http://${DEV_MACHINE_LAN_IP}:8001/api`,
};
