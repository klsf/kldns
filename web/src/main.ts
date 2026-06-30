import { createApp } from 'vue'
import { createPinia } from 'pinia'
import 'element-plus/theme-chalk/el-message.css'
import 'element-plus/theme-chalk/el-message-box.css'
import './styles/base.css'
import App from './App.vue'
import { router } from './app/router'

createApp(App).use(createPinia()).use(router).mount('#app')
