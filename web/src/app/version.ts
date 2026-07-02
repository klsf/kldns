import packageInfo from '../../package.json'

export const APP_VERSION = import.meta.env.VITE_APP_VERSION || packageInfo.version
