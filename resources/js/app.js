import { createApp } from 'vue';
import App from './App.vue';
import { setRouter } from './navigation';
import router from './router';

setRouter(router);

createApp(App).use(router).mount('#app');
