import './bootstrap';
import { createApp } from 'vue';
import BudgetBook from './components/BudgetBook.vue';

const el = document.getElementById('budget-app');
if (el) {
    createApp(BudgetBook).mount(el);
}
