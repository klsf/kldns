import './bootstrap';
import { createApp } from 'vue';
import ElementPlus from 'element-plus';
import 'element-plus/dist/index.css';

const root = document.getElementById('app');

if (root) {
    const app = createApp({});
    app.use(ElementPlus);
    app.mount(root);
}
