import { createApp } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import ArcoVue from '@arco-design/web-vue'
import App from './App.vue'

const routes = [
  { path: '/', component: () => import('./pages/Import.vue') },
  { path: '/configure', component: () => import('./pages/Configure.vue') },
  { path: '/analysis', component: () => import('./pages/Analysis.vue') },
  { path: '/confirm', component: () => import('./pages/Confirm.vue') },
  { path: '/report', component: () => import('./pages/Report.vue') },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

createApp(App)
  .use(router)
  .use(ArcoVue)
  .mount('#app')
