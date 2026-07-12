<template>
  <div class="tome">
    <div class="cover">
      <div class="corner tl"></div>
      <div class="corner tr"></div>
      <div class="corner bl"></div>
      <div class="corner br"></div>
      <div class="ribbon"></div>

      <div class="page">
        <div class="month-nav">
          <button class="nav-btn" :disabled="fetching" @click="goPrev" aria-label="Prethodni mesec">‹</button>
          <span class="month-label">{{ currentPeriodLabel }}</span>
          <button class="nav-btn" :disabled="fetching" @click="goNext" aria-label="Sledeći mesec">›</button>
        </div>
        <div class="year-toggle-row">
          <button class="reset-link" @click="toggleYearView">{{ showYearView ? '← Nazad na mesec' : '📊 Analiza godine' }}</button>
        </div>

        <div class="chat-box">
          <div class="chat-log" ref="chatLogEl">
            <div v-for="(msg, idx) in chatLog" :key="idx" class="chat-msg" :class="msg.role">
              <span>{{ msg.text }}</span>
              <div v-if="msg.confirm" class="chat-confirm">
                <button class="add-row" @click="applyChatAction(msg)">Da</button>
                <button class="reset-link" @click="rejectChatAction(msg)">Ne</button>
              </div>
            </div>
          </div>
          <form class="chat-input" @submit.prevent="sendChatMessage">
            <input type="text" v-model="chatInput" placeholder="npr. potrošio sam 500 na kafu" :disabled="chatSending">
            <button type="submit" :disabled="chatSending || !chatInput.trim()">Pošalji</button>
          </form>
        </div>

        <div class="masthead">
          <div class="eyebrow">Anno <span>{{ yearNow }}</span> · lična evidencija</div>
          <h1>Knjižica troškova</h1>
          <div class="sub">primanja, izdaci i štednja, po starom običaju</div>
          <svg class="flourish" viewBox="0 0 120 10"><path d="M0 5 H45 M75 5 H120 M55 5 a5 5 0 1 0 10 0 a5 5 0 1 0 -10 0" stroke="#B8892B" stroke-width="1" fill="none"/></svg>
        </div>

        <div v-if="loading" class="foot-note">Učitavanje knjižice…</div>

        <div v-else-if="showYearView" class="year-view">
          <div class="section-title">Analiza {{ currentYearLabel }}</div>

          <div v-if="yearLoading" class="foot-note">Učitavanje analize…</div>

          <template v-else-if="yearMonths.length">
            <div class="year-legend">
              <span class="legend-item"><span class="swatch" :style="{ background: YEAR_COLORS.income }"></span>Primanja</span>
              <span class="legend-item"><span class="swatch" :style="{ background: YEAR_COLORS.expense }"></span>Troškovi</span>
            </div>

            <svg class="year-chart" :viewBox="`0 0 ${yearChart.width} ${yearChart.height}`" preserveAspectRatio="xMidYMid meet">
              <line
                v-for="(gl, idx) in yearChart.gridlines" :key="idx"
                :x1="yearChart.padding" :x2="yearChart.width - yearChart.padding"
                :y1="gl" :y2="gl" class="year-gridline"
              />
              <polyline :points="yearChart.expensePoints" fill="none" :stroke="YEAR_COLORS.expense" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />
              <polyline :points="yearChart.incomePoints" fill="none" :stroke="YEAR_COLORS.income" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />
              <circle
                v-for="(m, i) in yearMonths" :key="'inc'+i"
                :cx="yearChart.x(i)" :cy="yearChart.y(m.income)" r="4"
                :fill="YEAR_COLORS.income" stroke="var(--parchment)" stroke-width="2"
              ><title>{{ periodLabel(m.period) }} — primanja: {{ fmt(m.income) }} RSD</title></circle>
              <circle
                v-for="(m, i) in yearMonths" :key="'exp'+i"
                :cx="yearChart.x(i)" :cy="yearChart.y(m.expense)" r="4"
                :fill="YEAR_COLORS.expense" stroke="var(--parchment)" stroke-width="2"
              ><title>{{ periodLabel(m.period) }} — troškovi: {{ fmt(m.expense) }} RSD</title></circle>
              <text
                v-for="(m, i) in yearMonths" :key="'lbl'+i"
                :x="yearChart.x(i)" :y="yearChart.height - 8" class="year-axis-label" text-anchor="middle"
              >{{ monthAbbrev(m.period) }}</text>
            </svg>

            <table class="year-table">
              <thead><tr><th>Mesec</th><th>Primanja</th><th>Troškovi</th><th>Neto</th></tr></thead>
              <tbody>
                <tr v-for="m in yearMonths" :key="m.period">
                  <td>{{ periodLabel(m.period) }}</td>
                  <td>{{ fmt(m.income) }} RSD</td>
                  <td>{{ fmt(m.expense) }} RSD</td>
                  <td :class="m.net >= 0 ? 'pos' : 'neg'">{{ signed(m.net) }} RSD</td>
                </tr>
              </tbody>
            </table>
          </template>

          <div v-else class="foot-note">Nema još podataka za analizu ove godine.</div>
        </div>

        <Transition v-else :name="navDirection === 'next' ? 'page-next' : 'page-prev'" mode="out-in">
          <div :key="currentPeriod" class="month-content" :class="{ 'is-fetching': fetching }">

            <div v-if="bannerVisible" class="banner">
              <span>Iz {{ bannerPreviousLabel }} ti je ostalo <strong>{{ signed(bannerPreviousNet) }} RSD</strong> (neto). Dodati u štednju?</span>
              <div class="banner-actions">
                <input type="number" v-model.number="bannerAmount" step="1">
                <button class="add-row" @click="confirmBanner">Dodaj u štednju</button>
                <button class="reset-link" @click="dismissBanner">Ne, hvala</button>
              </div>
            </div>

            <div class="section-title">Primanja</div>
            <table>
              <thead>
                <tr>
                  <th style="width:auto">Stavka</th>
                  <th class="amt-col">Iznos</th>
                  <th class="cur-col">Valuta</th>
                  <th class="freq-col">Učestalost</th>
                  <th class="chk-col">Akt.</th>
                  <th class="del-col"></th>
                </tr>
              </thead>
              <TransitionGroup tag="tbody" name="row">
                <tr v-for="item in visibleIncome" :key="keyFor(item)" :class="{ inactive: !item.active }">
                  <td class="cell-name"><input type="text" v-model="item.name" @change="saveIncome"></td>
                  <td class="amt-col" data-label="Iznos"><input type="number" v-model.number="item.amount" step="1" @change="saveIncome"></td>
                  <td class="cur-col" data-label="Valuta">
                    <select v-model="item.currency" @change="saveIncome">
                      <option value="RSD">RSD</option>
                      <option value="EUR">EUR</option>
                      <option value="USD">USD</option>
                    </select>
                  </td>
                  <td class="freq-col" data-label="Učestalost">
                    <select v-model.number="item.freq" @change="saveIncome">
                      <option :value="1">mesečno</option>
                      <option :value="2">na 2 meseca</option>
                      <option :value="3">na 3 meseca</option>
                      <option :value="0">jednokratno</option>
                    </select>
                  </td>
                  <td class="chk-col" data-label="Aktivno"><input type="checkbox" v-model="item.active" @change="saveIncome"></td>
                  <td class="del-col"><button class="del-btn" @click="removeRow(income, item, saveIncome)">×</button></td>
                </tr>
              </TransitionGroup>
            </table>
            <button class="add-row" @click="addRow(income, 'Novo primanje', saveIncome)">+ upiši primanje</button>
            <button v-if="oneTimeIncomeCount > 0" class="reset-link load-more-btn" @click="showOneTimeIncome = !showOneTimeIncome">
              {{ showOneTimeIncome ? 'Sakrij jednokratna primanja' : 'Prikaži jednokratna primanja' }} ({{ oneTimeIncomeCount }})
            </button>

            <div class="section-title">Troškovi</div>
            <table>
              <thead>
                <tr>
                  <th style="width:auto">Stavka</th>
                  <th class="amt-col">Iznos</th>
                  <th class="cur-col">Valuta</th>
                  <th class="freq-col">Učestalost</th>
                  <th class="end-col">Do meseca</th>
                  <th class="cat-col">Kategorija</th>
                  <th class="chk-col">Akt.</th>
                  <th class="del-col"></th>
                </tr>
              </thead>
              <TransitionGroup tag="tbody" name="row">
                <tr v-for="item in visibleExpenses" :key="keyFor(item)" :class="{ inactive: !isExpenseActive(item) }">
                  <td class="cell-name"><input type="text" v-model="item.name" @change="saveExpenses"></td>
                  <td class="amt-col" data-label="Iznos"><input type="number" v-model.number="item.amount" step="1" @change="saveExpenses"></td>
                  <td class="cur-col" data-label="Valuta">
                    <select v-model="item.currency" @change="saveExpenses">
                      <option value="RSD">RSD</option>
                      <option value="EUR">EUR</option>
                      <option value="USD">USD</option>
                    </select>
                  </td>
                  <td class="freq-col" data-label="Učestalost">
                    <select v-model.number="item.freq" @change="saveExpenses">
                      <option :value="1">mesečno</option>
                      <option :value="2">na 2 meseca</option>
                      <option :value="3">na 3 meseca</option>
                      <option :value="0">jednokratno</option>
                    </select>
                  </td>
                  <td class="end-col" data-label="Do meseca"><input type="month" v-model="item.endPeriod" @change="saveExpenses"></td>
                  <td class="cat-col" data-label="Kategorija">
                    <select v-model="item.category" @change="saveExpenses">
                      <option v-for="cat in EXPENSE_CATEGORIES" :key="cat" :value="cat">{{ cat }}</option>
                    </select>
                  </td>
                  <td class="chk-col" data-label="Aktivno"><input type="checkbox" v-model="item.active" @change="saveExpenses"></td>
                  <td class="del-col"><button class="del-btn" @click="removeRow(expenses, item, saveExpenses)">×</button></td>
                </tr>
              </TransitionGroup>
            </table>
            <button class="add-row" @click="addRow(expenses, 'Nova stavka', saveExpenses)">+ upiši trošak</button>
            <button v-if="oneTimeExpensesCount > 0" class="reset-link load-more-btn" @click="showOneTimeExpenses = !showOneTimeExpenses">
              {{ showOneTimeExpenses ? 'Sakrij jednokratne troškove' : 'Prikaži jednokratne troškove' }} ({{ oneTimeExpensesCount }})
            </button>

            <div class="chart-section" v-if="categoryBreakdown.length">
              <div class="cat-bar-row" v-for="slice in categoryBreakdown" :key="slice.category">
                <div class="cat-bar-label">{{ slice.category }}</div>
                <div class="cat-bar-track">
                  <div class="cat-bar-fill" :style="{ width: slice.pct + '%', background: slice.color }"></div>
                </div>
                <div class="cat-bar-value">{{ fmt(slice.amount) }} RSD <span class="cat-bar-pct">({{ slice.pct.toFixed(0) }}%)</span></div>
              </div>
            </div>

            <div class="section-title">Štednja i imovina</div>
            <table>
              <thead>
                <tr>
                  <th style="width:auto">Stavka</th>
                  <th class="amt-col">Iznos</th>
                  <th class="cur-col">Valuta</th>
                  <th class="cat-col">Kategorija</th>
                  <th class="del-col"></th>
                </tr>
              </thead>
              <TransitionGroup tag="tbody" name="row">
                <tr v-for="item in savings" :key="keyFor(item)">
                  <td class="cell-name"><input type="text" v-model="item.name" @change="saveSavings"></td>
                  <td class="amt-col" data-label="Iznos"><input type="number" v-model.number="item.amount" step="1" @change="saveSavings"></td>
                  <td class="cur-col" data-label="Valuta">
                    <select v-model="item.currency" @change="saveSavings">
                      <option value="RSD">RSD</option>
                      <option value="EUR">EUR</option>
                      <option value="USD">USD</option>
                    </select>
                  </td>
                  <td class="cat-col" data-label="Kategorija">
                    <select v-model="item.category" @change="saveSavings">
                      <option v-for="cat in SAVINGS_CATEGORIES" :key="cat" :value="cat">{{ cat }}</option>
                    </select>
                  </td>
                  <td class="del-col"><button class="del-btn" @click="removeRow(savings, item, saveSavings)">×</button></td>
                </tr>
              </TransitionGroup>
            </table>
            <button class="add-row" @click="addSavingsRow">+ upiši stavku štednje</button>

            <div class="rates">
              <div>
                <label>1 USD =</label>
                <input type="number" v-model.number="rates.usd" step="0.01" @change="saveRates">
              </div>
              <div>
                <label>1 EUR =</label>
                <input type="number" v-model.number="rates.eur" step="0.01" @change="saveRates">
              </div>
              <div class="note">srednji kurs NBS, po volji izmeni</div>
            </div>

            <div class="section-title">Konverter valuta</div>
            <div class="converter">
              <div>
                <label>Iznos</label>
                <input type="number" v-model.number="conv.amount" step="1">
              </div>
              <div>
                <label>Iz</label>
                <select v-model="conv.from">
                  <option value="RSD">RSD</option>
                  <option value="EUR">EUR</option>
                  <option value="USD">USD</option>
                </select>
              </div>
              <div class="eq">=</div>
              <div>
                <label>U</label>
                <select v-model="conv.to">
                  <option value="EUR">EUR</option>
                  <option value="RSD">RSD</option>
                  <option value="USD">USD</option>
                </select>
              </div>
              <div class="result">{{ convResult }}</div>
            </div>

            <div class="totals">
              <div class="totals-text">
                <div class="row"><span class="lbl">primanja ovog meseca</span><span class="val" :class="{ flash: flash.income }">{{ fmt(incThis) }} RSD</span></div>
                <div class="row"><span class="lbl">troškovi ovog meseca</span><span class="val" :class="{ flash: flash.expense }">{{ fmt(expThis) }} RSD</span></div>
                <div class="row main"><span class="lbl">neto ovog meseca</span><span class="val" :class="[netThis >= 0 ? 'pos' : 'neg', { flash: flash.net }]">{{ signed(netThis) }} RSD</span></div>
                <div class="row"><span class="lbl">prosečan neto mesečno</span><span class="val" :class="netAvg >= 0 ? 'pos' : 'neg'">{{ signed(netAvg) }} RSD</span></div>
              </div>
              <div class="seal-wrap">
                <div class="lbl">neto</div>
                <div class="val" :class="{ flash: flash.net }">{{ signed(netThis) }}</div>
                <div class="cur">RSD ovog meseca</div>
              </div>
            </div>

            <div class="analyze-row">
              <button class="add-row" :disabled="analyzing" @click="analyzeMonth">{{ analyzing ? 'Analiziram…' : '🔍 Analiziraj mesec' }}</button>
            </div>
            <div v-if="analysisText || analysisError" class="banner">
              <span v-if="analysisText">{{ analysisText }}</span>
              <span v-else>{{ analysisError }}</span>
              <div class="banner-actions">
                <button class="reset-link" @click="closeAnalysis">Zatvori</button>
              </div>
            </div>

            <div class="savings-line">
              Ukupna ušteđevina: <strong :class="{ flash: flash.savings }">{{ fmt(savTotal) }} RSD</strong>
              &nbsp;·&nbsp; <span>≈ {{ fmt2(savTotal / rates.eur) }} €</span>
              &nbsp;·&nbsp; <span>≈ {{ fmt2(savTotal / rates.usd) }} $</span>
            </div>

            <div class="foot-note">
              Isključi "Akt." za stavke koje ovog meseca ne dospevaju.
              &nbsp;·&nbsp; <button class="reset-link" @click="resetAll">vrati na početne vrednosti</button>
            </div>
          </div>
        </Transition>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, computed, watch, onMounted, nextTick } from 'vue';
import axios from 'axios';

const yearNow = new Date().getFullYear();
const loading = ref(true);
const fetching = ref(false);

const MONTH_NAMES = ['Januar', 'Februar', 'Mart', 'April', 'Maj', 'Jun', 'Jul', 'Avgust', 'Septembar', 'Oktobar', 'Novembar', 'Decembar'];

function currentYearMonth() {
  const d = new Date();
  return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
}
function shiftPeriod(period, delta) {
  const [y, m] = period.split('-').map(Number);
  const d = new Date(y, m - 1 + delta, 1);
  return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
}
function periodLabel(period) {
  const [y, m] = period.split('-').map(Number);
  return MONTH_NAMES[m - 1] + ' ' + y + '.';
}

const currentPeriod = ref(currentYearMonth());
const navDirection = ref('next');
const currentPeriodLabel = computed(() => periodLabel(currentPeriod.value));

function goPrev() { navDirection.value = 'prev'; currentPeriod.value = shiftPeriod(currentPeriod.value, -1); }
function goNext() { navDirection.value = 'next'; currentPeriod.value = shiftPeriod(currentPeriod.value, 1); }

const defaultExpenses = [];
const defaultIncome = [
  { name: 'Plata', amount: 0, currency: 'RSD', freq: 1, active: true },
];
const defaultSavings = [];
const defaultRates = { usd: 102.76, eur: 117.36 };

const EXPENSE_CATEGORIES = ['Stanovanje', 'Hrana', 'Prevoz', 'Zdravlje', 'Zabava', 'Računi', 'Otplate', 'Ostalo'];
const SAVINGS_CATEGORIES = ['Gotovina', 'Štednja', 'Investicije', 'Nekretnine', 'Ostalo'];
const CATEGORY_COLORS = ['#96690A', '#9C3232', '#1F7A4D', '#B85C1F', '#2D5F9E', '#0D8F82', '#7A3F99', '#914A1E'];

const expenses = reactive(clone(defaultExpenses));
const income = reactive(clone(defaultIncome));
const savings = reactive(clone(defaultSavings));
const rates = reactive(clone(defaultRates));
const conv = reactive({ amount: 1000, from: 'RSD', to: 'EUR' });

function clone(x) { return JSON.parse(JSON.stringify(x)); }
function replaceArray(target, source) { target.splice(0, target.length, ...source); }

const rowIdMap = new WeakMap();
let rowIdCounter = 0;
function keyFor(item) {
  if (!rowIdMap.has(item)) rowIdMap.set(item, ++rowIdCounter);
  return rowIdMap.get(item);
}

const bannerVisible = ref(false);
const bannerPreviousNet = ref(0);
const bannerAmount = ref(0);
const bannerPreviousLabel = ref('');
const dismissedPeriods = new Set();

async function loadState(period) {
  fetching.value = true;
  bannerVisible.value = false;

  replaceArray(expenses, clone(defaultExpenses));
  replaceArray(income, clone(defaultIncome));
  Object.assign(rates, clone(defaultRates));

  try {
    const { data } = await axios.get('/api/budget', { params: { period } });
    const d = data.data || {};
    if (d['expense-items']) replaceArray(expenses, JSON.parse(d['expense-items']).map(it => ({ category: 'Ostalo', ...it })));
    if (d['income-items']) replaceArray(income, JSON.parse(d['income-items']));
    if (d['expense-rates']) Object.assign(rates, JSON.parse(d['expense-rates']));
    if (d['savings-items']) replaceArray(savings, JSON.parse(d['savings-items']).map(it => ({ category: 'Ostalo', ...it })));

    if (data.is_new_period && data.previous_net !== null && data.template_period && !dismissedPeriods.has(period)) {
      bannerPreviousNet.value = data.previous_net;
      bannerAmount.value = Math.round(data.previous_net);
      bannerPreviousLabel.value = periodLabel(data.template_period);
      bannerVisible.value = data.previous_net > 0;
    }
  } catch (e) {
    // nema sacuvanih podataka jos — ostaju podrazumevane vrednosti
  } finally {
    fetching.value = false;
    loading.value = false;
  }
}

function confirmBanner() {
  savings.push({ name: 'Preneto iz ' + bannerPreviousLabel.value, amount: Math.round(bannerAmount.value) || 0, currency: 'RSD', category: 'Štednja' });
  saveSavings();
  bannerVisible.value = false;
  dismissedPeriods.add(currentPeriod.value);
}
function dismissBanner() {
  bannerVisible.value = false;
  dismissedPeriods.add(currentPeriod.value);
}

function persist(key, value, period = null) {
  const payload = { key, value: JSON.stringify(value) };
  if (period) payload.period = period;
  axios.post('/api/budget', payload);
}
function saveExpenses() { persist('expense-items', expenses, currentPeriod.value); }
function saveIncome() { persist('income-items', income, currentPeriod.value); }
function saveSavings() { persist('savings-items', savings); }
function saveRates() { persist('expense-rates', rates, currentPeriod.value); }

function addRow(arr, name, save) {
  arr.push({ name, amount: 0, currency: 'RSD', freq: 1, active: true, endPeriod: null, category: 'Ostalo' });
  save();
}
function addSavingsRow() {
  savings.push({ name: 'Nova stavka', amount: 0, currency: 'RSD', category: 'Ostalo' });
  saveSavings();
}
function removeRow(arr, item, save) {
  const idx = arr.indexOf(item);
  if (idx !== -1) arr.splice(idx, 1);
  save();
}

function resetAll() {
  replaceArray(expenses, clone(defaultExpenses));
  replaceArray(income, clone(defaultIncome));
  replaceArray(savings, clone(defaultSavings));
  Object.assign(rates, clone(defaultRates));
  saveExpenses(); saveIncome(); saveSavings(); saveRates();
}

function toRSD(amount, currency) {
  if (currency === 'USD') return amount * rates.usd;
  if (currency === 'EUR') return amount * rates.eur;
  return amount;
}
function fmt(n) { return Math.round(n).toLocaleString('sr-RS'); }
function fmt2(n) { return n.toLocaleString('sr-RS', { maximumFractionDigits: 2 }); }
function signed(n) { return (n >= 0 ? '+' : '') + fmt(n); }

function isExpenseActive(item) {
  return item.active && (!item.endPeriod || currentPeriod.value <= item.endPeriod);
}

const showOneTimeExpenses = ref(false);
const showOneTimeIncome = ref(false);

const oneTimeExpensesCount = computed(() => expenses.filter(it => it.freq === 0).length);
const oneTimeIncomeCount = computed(() => income.filter(it => it.freq === 0).length);

const visibleExpenses = computed(() => {
  const recurring = expenses.filter(it => it.freq !== 0);
  return showOneTimeExpenses.value ? [...recurring, ...expenses.filter(it => it.freq === 0)] : recurring;
});
const visibleIncome = computed(() => {
  const recurring = income.filter(it => it.freq !== 0);
  return showOneTimeIncome.value ? [...recurring, ...income.filter(it => it.freq === 0)] : recurring;
});

const expThis = computed(() => expenses.reduce((sum, it) => isExpenseActive(it) ? sum + toRSD(it.amount, it.currency) : sum, 0));
const incThis = computed(() => income.reduce((sum, it) => it.active ? sum + toRSD(it.amount, it.currency) : sum, 0));
const expAvg = computed(() => expenses.reduce((sum, it) => {
  if (!isExpenseActive(it)) return sum;
  const r = toRSD(it.amount, it.currency);
  return sum + (it.freq > 0 ? r / it.freq : r);
}, 0));
const incAvg = computed(() => income.reduce((sum, it) => {
  if (!it.active) return sum;
  const r = toRSD(it.amount, it.currency);
  return sum + (it.freq > 0 ? r / it.freq : r);
}, 0));
const savTotal = computed(() => savings.reduce((sum, it) => sum + toRSD(it.amount, it.currency), 0));
const netThis = computed(() => incThis.value - expThis.value);
const netAvg = computed(() => incAvg.value - expAvg.value);

const categoryBreakdown = computed(() => {
  const totals = {};
  expenses.forEach(it => {
    if (!isExpenseActive(it)) return;
    const cat = it.category || 'Ostalo';
    totals[cat] = (totals[cat] || 0) + toRSD(it.amount, it.currency);
  });

  const total = Object.values(totals).reduce((sum, v) => sum + v, 0);
  if (total <= 0) return [];

  return Object.entries(totals)
    .map(([category, amount]) => ({
      category,
      amount,
      pct: (amount / total) * 100,
      color: CATEGORY_COLORS[EXPENSE_CATEGORIES.indexOf(category)] ?? CATEGORY_COLORS[CATEGORY_COLORS.length - 1],
    }))
    .sort((a, b) => b.amount - a.amount);
});

const convResult = computed(() => {
  const amount = conv.amount || 0;
  const rsd = conv.from === 'RSD' ? amount : (conv.from === 'USD' ? amount * rates.usd : amount * rates.eur);
  const result = conv.to === 'RSD' ? rsd : (conv.to === 'USD' ? rsd / rates.usd : rsd / rates.eur);
  return fmt2(result) + ' ' + conv.to;
});

const flash = reactive({ income: false, expense: false, net: false, savings: false });
function triggerFlash(key) {
  flash[key] = true;
  setTimeout(() => { flash[key] = false; }, 500);
}
watch(incThis, () => triggerFlash('income'));
watch(expThis, () => triggerFlash('expense'));
watch(netThis, () => triggerFlash('net'));
watch(savTotal, () => triggerFlash('savings'));

watch(currentPeriod, (period) => loadState(period));
onMounted(() => loadState(currentPeriod.value));

const chatLog = reactive([]);
const chatInput = ref('');
const chatSending = ref(false);
const chatLogEl = ref(null);

const CHAT_ACTION_LABELS = { add_expense: 'trošak', add_income: 'primanje', add_saving: 'stavku štednje' };
const FREQ_LABELS = { 0: 'jednokratno', 1: 'mesečno', 2: 'na 2 meseca', 3: 'na 3 meseca' };

function scrollChatToBottom() {
  nextTick(() => {
    if (chatLogEl.value) chatLogEl.value.scrollTop = chatLogEl.value.scrollHeight;
  });
}

async function sendChatMessage() {
  const text = chatInput.value.trim();
  if (!text) return;

  chatLog.push({ role: 'user', text });
  chatInput.value = '';
  chatSending.value = true;
  chatLog.push({ role: 'assistant', text: 'Razmišljam…' });
  scrollChatToBottom();

  try {
    const { data } = await axios.post('/api/budget/chat', { message: text });
    chatLog.pop();

    const validActions = ['add_expense', 'add_income', 'add_saving'];
    if (validActions.includes(data.action) && data.name && data.amount > 0) {
      const currency = data.currency || 'RSD';
      const freq = data.freq ?? 1;
      const freqText = data.action === 'add_saving' ? '' : `, ${FREQ_LABELS[freq] ?? 'mesečno'}`;
      chatLog.push({
        role: 'assistant',
        text: `Da dodam ${CHAT_ACTION_LABELS[data.action]} "${data.name}": ${data.amount} ${currency}${freqText}?`,
        confirm: { action: data.action, name: data.name, amount: data.amount, currency, freq },
      });
    } else {
      chatLog.push({ role: 'assistant', text: 'Nisam siguran šta si mislio/la — probaj konkretnije (npr. "potrošio sam 500 dinara na kafu").' });
    }
  } catch (e) {
    chatLog.pop();
    chatLog.push({ role: 'assistant', text: 'Nešto nije u redu, probaj ponovo.' });
  } finally {
    chatSending.value = false;
    scrollChatToBottom();
  }
}

function applyChatAction(msg) {
  const { action, name, amount, currency, freq } = msg.confirm;
  if (action === 'add_expense') {
    expenses.push({ name, amount, currency, freq, active: true, endPeriod: null, category: 'Ostalo' });
    saveExpenses();
  } else if (action === 'add_income') {
    income.push({ name, amount, currency, freq, active: true });
    saveIncome();
  } else if (action === 'add_saving') {
    savings.push({ name, amount, currency, category: 'Ostalo' });
    saveSavings();
  }
  msg.confirm = null;
  chatLog.push({ role: 'assistant', text: '✓ Dodato.' });
  scrollChatToBottom();
}

function rejectChatAction(msg) {
  msg.confirm = null;
  chatLog.push({ role: 'assistant', text: 'U redu, ništa nisam dodao.' });
  scrollChatToBottom();
}

const analyzing = ref(false);
const analysisText = ref('');
const analysisError = ref('');

async function analyzeMonth() {
  analyzing.value = true;
  analysisText.value = '';
  analysisError.value = '';

  try {
    const activeExpenses = expenses
      .filter(it => it.active)
      .map(it => ({ name: it.name, amount: it.amount, currency: it.currency }));

    const { data } = await axios.post('/api/budget/analyze', {
      period: currentPeriod.value,
      income_total: Math.round(incThis.value),
      expense_total: Math.round(expThis.value),
      net: Math.round(netThis.value),
      expenses: activeExpenses,
    });
    analysisText.value = data.tip;
  } catch (e) {
    analysisError.value = 'Analiza trenutno nije dostupna.';
  } finally {
    analyzing.value = false;
  }
}

function closeAnalysis() {
  analysisText.value = '';
  analysisError.value = '';
}

const showYearView = ref(false);
const yearMonths = ref([]);
const yearLoading = ref(false);
const currentYearLabel = computed(() => currentPeriod.value.split('-')[0]);
const YEAR_COLORS = { income: CATEGORY_COLORS[2], expense: CATEGORY_COLORS[1] };

async function toggleYearView() {
  showYearView.value = !showYearView.value;
  if (showYearView.value && yearMonths.value.length === 0 && !yearLoading.value) {
    yearLoading.value = true;
    try {
      const { data } = await axios.get('/api/budget/yearly');
      yearMonths.value = data.months;
    } finally {
      yearLoading.value = false;
    }
  }
}

function monthAbbrev(period) {
  const [, m] = period.split('-').map(Number);
  return MONTH_NAMES[m - 1].slice(0, 3);
}

const yearChart = computed(() => {
  const width = 640, height = 220, padding = 32;
  const months = yearMonths.value;

  if (!months.length) {
    return { width, height, padding, x: () => 0, y: () => 0, incomePoints: '', expensePoints: '', gridlines: [] };
  }

  const maxVal = Math.max(1, ...months.flatMap(m => [m.income, m.expense]));
  const stepX = months.length > 1 ? (width - padding * 2) / (months.length - 1) : 0;
  const x = (i) => padding + i * stepX;
  const y = (v) => height - padding - (v / maxVal) * (height - padding * 2);

  return {
    width, height, padding, x, y,
    incomePoints: months.map((m, i) => `${x(i)},${y(m.income)}`).join(' '),
    expensePoints: months.map((m, i) => `${x(i)},${y(m.expense)}`).join(' '),
    gridlines: [0, 0.25, 0.5, 0.75, 1].map(t => height - padding - t * (height - padding * 2)),
  };
});
</script>

<style>
:root{
  --leather:#2E1B14;
  --leather-hi:#4A2A1E;
  --parchment:#EFE1BE;
  --parchment-dark:#E2D0A2;
  --ink:#3B2A18;
  --ink-light:#7A6440;
  --gilt:#B8892B;
  --gilt-bright:#D8AE4C;
  --seal:#7A1F1F;
  --seal-hi:#9C3232;
  --pos:#2E5B3E;
}
.tome{ width:100%; max-width:780px; position:relative; margin:0 auto; }

.cover{
  background:linear-gradient(135deg, var(--leather-hi), var(--leather) 40%, #1F1109 100%);
  border-radius:6px;
  padding:20px;
  box-shadow:
    0 30px 60px -20px rgba(0,0,0,0.7),
    inset 0 0 0 2px rgba(184,137,43,0.35),
    inset 0 0 40px rgba(0,0,0,0.55);
  position:relative;
}
.cover::before{
  content:"";
  position:absolute;
  inset:8px;
  border:1px solid rgba(184,137,43,0.45);
  border-radius:3px;
  pointer-events:none;
}
.corner{ position:absolute; width:26px; height:26px; border:1.5px solid var(--gilt); opacity:0.75; }
.corner.tl{ top:14px; left:14px; border-right:none; border-bottom:none; }
.corner.tr{ top:14px; right:14px; border-left:none; border-bottom:none; }
.corner.bl{ bottom:14px; left:14px; border-right:none; border-top:none; }
.corner.br{ bottom:14px; right:14px; border-left:none; border-top:none; }

.ribbon{
  position:absolute;
  top:0; right:38px;
  width:20px; height:64px;
  background:linear-gradient(180deg, var(--seal-hi), var(--seal));
  clip-path:polygon(0 0,100% 0,100% 100%,50% 78%,0 100%);
  box-shadow:0 3px 8px rgba(0,0,0,0.4);
}

.page{
  background:
    radial-gradient(circle at 12% 18%, rgba(120,90,40,0.10), transparent 40%),
    radial-gradient(circle at 85% 75%, rgba(120,90,40,0.10), transparent 45%),
    radial-gradient(circle at 60% 10%, rgba(120,90,40,0.08), transparent 35%),
    var(--parchment);
  padding:38px 34px 30px 34px;
  box-shadow:inset 0 0 60px rgba(120,90,40,0.25), 0 2px 0 rgba(0,0,0,0.15);
  position:relative;
  overflow:hidden;
  font-family:'EB Garamond',Georgia,'Cambria','Times New Roman',serif;
  color:var(--ink);
}
.page::after{
  content:"";
  position:absolute;
  inset:0;
  box-shadow:inset 0 0 0 10px var(--parchment), inset 0 0 0 11px rgba(120,90,40,0.3);
  pointer-events:none;
}

.month-nav{
  display:flex; align-items:center; justify-content:center; gap:18px;
  margin-bottom:6px;
}
.month-nav .nav-btn{
  background:none; border:1px solid var(--gilt); color:var(--ink);
  font-family:Georgia,serif; font-size:16px; line-height:1; width:28px; height:28px;
  cursor:pointer; border-radius:50%;
}
.month-nav .nav-btn:hover:not(:disabled){ background:rgba(184,137,43,0.15); }
.month-nav .nav-btn:disabled{ opacity:0.4; cursor:default; }
.month-nav .month-label{
  font-family:'IM Fell English SC',Georgia,serif; text-transform:uppercase; letter-spacing:1.5px;
  font-size:13px; color:var(--gilt); min-width:130px; text-align:center;
}

.banner{
  display:flex; flex-direction:column; gap:8px;
  background:rgba(184,137,43,0.14); border:1px solid var(--gilt);
  border-radius:4px; padding:12px 16px; margin-bottom:20px;
  font-size:12.5px;
}
.banner-actions{ display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.banner-actions input{
  width:100px; font-family:Georgia,serif; font-size:13px; color:var(--ink);
  background:transparent; border:none; border-bottom:1px solid var(--ink-light); padding:3px 2px;
}
.banner-actions input:focus{ outline:none; border-bottom:1px solid var(--gilt); }
.banner .add-row{ margin:0; padding:6px 12px; }
.banner .reset-link{ margin:0; }

.masthead{ text-align:center; border-bottom:2px solid var(--ink); padding-bottom:16px; margin-bottom:22px; position:relative; }
.masthead .eyebrow{ font-size:12px; letter-spacing:3px; text-transform:uppercase; color:var(--gilt); margin-bottom:6px; font-family:'IM Fell English SC',Georgia,serif; }
.masthead h1{ margin:0; font-size:30px; letter-spacing:2px; color:var(--ink); font-family:'Cinzel',Georgia,serif; font-weight:900; }
.masthead h1::first-letter{ font-size:48px; color:var(--seal); font-family:'Cinzel',Georgia,serif; }
.masthead .sub{ font-size:12px; font-style:italic; color:var(--ink-light); margin-top:6px; }
.flourish{ margin:8px auto 0 auto; width:120px; height:10px; opacity:0.6; }

.section-title{
  text-align:center;
  font-size:14px;
  text-transform:uppercase;
  letter-spacing:2px;
  color:var(--gilt);
  font-family:'IM Fell English SC',Georgia,serif;
  margin:28px 0 12px 0;
  position:relative;
}
.section-title::before, .section-title::after{
  content:"—";
  color:var(--ink-light);
  margin:0 10px;
}
.section-title:first-of-type{ margin-top:0; }

.page table{ width:100%; border-collapse:collapse; margin-bottom:6px; }
.page thead th{
  font-size:10px; text-transform:uppercase; letter-spacing:1px;
  color:var(--ink-light); font-variant:small-caps;
  text-align:left; padding:0 6px 8px 6px;
  border-bottom:1px solid var(--ink-light);
}
.page tbody td{ padding:7px 6px; border-bottom:1px dotted rgba(59,42,24,0.4); vertical-align:middle; }
.page tbody tr.inactive{ opacity:0.4; }

.page input[type=text], .page input[type=number]{
  font-family:Georgia,serif; font-size:13.5px; color:var(--ink);
  background:transparent; border:none; border-bottom:1px solid transparent;
  width:100%; padding:3px 2px;
}
.page input[type=text]:focus, .page input[type=number]:focus{ outline:none; border-bottom:1px solid var(--gilt); }
.page select{
  font-family:Georgia,serif; font-size:12.5px; color:var(--ink);
  background:transparent; border:none; border-bottom:1px solid transparent; padding:3px 0;
}
.page select:focus{ outline:none; border-bottom:1px solid var(--gilt); }

.amt-col{ width:90px; }
.cur-col{ width:64px; }
.freq-col{ width:120px; }
.end-col{ width:120px; }
.cat-col{ width:110px; }
.chk-col{ width:36px; text-align:center; }
.del-col{ width:26px; text-align:center; }
.end-col input[type=month]{ font-size:12px; }

.del-btn{ background:none; border:none; color:var(--seal); font-size:16px; cursor:pointer; font-family:Georgia,serif; }
.del-btn:hover{ text-decoration:underline; }

.add-row{
  margin:12px 0 8px 0;
  background:none;
  border:1px solid var(--gilt);
  color:var(--ink);
  font-family:Georgia,serif;
  font-variant:small-caps;
  font-size:12.5px;
  padding:8px 14px;
  cursor:pointer;
  letter-spacing:0.5px;
}
.add-row:hover{ background:rgba(184,137,43,0.12); }

.chart-section{ margin:18px 0 26px 0; }
.cat-bar-row{
  display:flex; align-items:center; gap:10px;
  margin-bottom:2px; padding:3px 0;
}
.cat-bar-label{
  width:88px; flex-shrink:0; font-size:11.5px; color:var(--ink);
  font-variant:small-caps; text-align:right; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.cat-bar-track{ flex:1; height:16px; background:rgba(59,42,24,0.08); border-radius:0 4px 4px 0; overflow:hidden; }
.cat-bar-fill{ height:100%; border-radius:0 4px 4px 0; }
.cat-bar-value{
  width:150px; flex-shrink:0; font-size:11.5px; color:var(--ink-light);
  text-align:left; white-space:nowrap;
}
.cat-bar-pct{ color:var(--ink-light); opacity:0.8; }

.year-toggle-row{ text-align:center; margin-bottom:6px; }

.year-view{ padding-top:4px; }
.year-legend{ display:flex; justify-content:center; gap:22px; margin:10px 0 16px 0; font-size:12px; color:var(--ink); }
.legend-item{ display:inline-flex; align-items:center; gap:6px; }
.legend-item .swatch{ width:10px; height:10px; border-radius:50%; display:inline-block; }

.year-chart{ width:100%; height:auto; display:block; margin-bottom:18px; }
.year-gridline{ stroke:rgba(59,42,24,0.15); stroke-width:1; }
.year-axis-label{ font-size:9px; fill:var(--ink-light); font-family:Georgia,serif; }

.year-table{ width:100%; border-collapse:collapse; font-size:12px; }
.year-table th{
  font-size:10px; text-transform:uppercase; letter-spacing:1px; color:var(--ink-light);
  font-variant:small-caps; text-align:left; padding:0 6px 8px 6px; border-bottom:1px solid var(--ink-light);
}
.year-table td{ padding:6px; border-bottom:1px dotted rgba(59,42,24,0.3); color:var(--ink); }
.year-table td.pos{ color:var(--pos); }
.year-table td.neg{ color:var(--seal); }

.rates{
  display:flex; gap:26px; flex-wrap:wrap;
  border-top:1px solid var(--ink-light); border-bottom:1px solid var(--ink-light);
  padding:16px 2px; margin:26px 0 20px 0; font-size:12.5px;
}
.rates label{ color:var(--ink-light); display:block; margin-bottom:3px; font-size:10px; text-transform:uppercase; letter-spacing:0.6px; font-variant:small-caps; }
.rates input{ width:80px; border-bottom:1px solid var(--ink-light); }
.rates .note{ font-style:italic; color:var(--ink-light); font-size:11.5px; align-self:flex-end; }

.converter{
  display:flex; align-items:flex-end; gap:14px; flex-wrap:wrap;
  padding-bottom:22px; margin-bottom:24px;
  border-bottom:1px solid var(--ink-light); font-size:12.5px;
}
.converter label{ display:block; color:var(--ink-light); font-size:10px; text-transform:uppercase; margin-bottom:3px; letter-spacing:0.6px; font-variant:small-caps; }
.converter input[type=number]{ width:100px; border-bottom:1px solid var(--ink-light); }
.converter select{ border-bottom:1px solid var(--ink-light); }
.converter .eq{ font-size:15px; padding-bottom:4px; color:var(--ink-light); }
.converter .result{ font-weight:bold; padding-bottom:4px; font-size:14px; }

.totals{ display:flex; align-items:center; justify-content:space-between; gap:20px; flex-wrap:wrap; }
.totals-text{ max-width:340px; }
.totals-text .row{ display:flex; justify-content:space-between; font-size:13px; padding:5px 0; border-bottom:1px dotted rgba(59,42,24,0.4); }
.totals-text .row .lbl{ color:var(--ink-light); font-variant:small-caps; }
.totals-text .row.main .lbl, .totals-text .row.main .val{ color:var(--ink); font-weight:bold; }
.totals-text .row .val.pos{ color:var(--pos); }
.totals-text .row .val.neg{ color:var(--seal); }

.seal-wrap{
  width:132px; height:132px; border-radius:50%; flex-shrink:0; position:relative;
  background:radial-gradient(circle at 35% 30%, var(--seal-hi), var(--seal) 55%, #4E1414 100%);
  box-shadow:0 10px 18px -6px rgba(0,0,0,0.55), inset 0 2px 4px rgba(255,255,255,0.25), inset 0 -6px 10px rgba(0,0,0,0.4);
  display:flex; flex-direction:column; align-items:center; justify-content:center;
  color:#F1D9A8; text-align:center; transform:rotate(-4deg);
}
.seal-wrap::before{ content:""; position:absolute; inset:9px; border:1px solid rgba(241,217,168,0.5); border-radius:50%; }
.seal-wrap .lbl{ font-size:9.5px; letter-spacing:1.5px; text-transform:uppercase; margin-bottom:5px; font-family:'IM Fell English SC',Georgia,serif; }
.seal-wrap .val{ font-size:15px; font-weight:bold; line-height:1.2; font-family:Georgia,serif; border-radius:3px; }
.seal-wrap .cur{ font-size:8.5px; margin-top:4px; font-variant:small-caps; }

.savings-line{
  font-size:12.5px; color:var(--ink-light); text-align:center;
  margin-top:18px; padding-top:16px; border-top:1px dashed var(--ink-light);
  font-variant:small-caps;
}
.savings-line strong{ color:var(--ink); border-radius:3px; }
.savings-line span{ color:var(--ink); }

.foot-note{ margin-top:24px; font-size:11px; font-style:italic; color:var(--ink-light); text-align:center; }
.reset-link{ background:none; border:none; color:var(--ink-light); font-family:Georgia,serif; font-size:10.5px; font-style:italic; text-decoration:underline; cursor:pointer; padding:0; }
.load-more-btn{ display:block; margin:8px 0 4px 0; }

.chat-box{
  border:1px solid var(--ink-light); border-radius:6px; padding:10px 12px;
  margin-bottom:22px; background:rgba(255,255,255,0.12);
}
.chat-log{ max-height:180px; overflow-y:auto; margin-bottom:8px; font-size:12.5px; }
.chat-log:empty{ display:none; }
.chat-msg{ margin-bottom:8px; }
.chat-msg.user{ text-align:right; }
.chat-msg.user span{ color:var(--ink); font-weight:600; }
.chat-msg.assistant{ text-align:left; }
.chat-msg.assistant span{ color:var(--ink-light); font-style:italic; }
.chat-confirm{ display:flex; gap:8px; margin-top:4px; }
.chat-confirm button{ padding:4px 10px; font-size:11.5px; margin:0; }
.chat-input{ display:flex; gap:8px; }
.chat-input input{
  flex:1; font-family:Georgia,serif; font-size:13px; color:var(--ink);
  background:transparent; border:none; border-bottom:1px solid var(--ink-light); padding:5px 2px;
}
.chat-input input:focus{ outline:none; border-bottom:1px solid var(--gilt); }
.chat-input button{
  background:none; border:1px solid var(--gilt); color:var(--ink);
  font-family:Georgia,serif; font-variant:small-caps; font-size:12px;
  padding:6px 14px; cursor:pointer;
}
.chat-input button:hover:not(:disabled){ background:rgba(184,137,43,0.12); }
.chat-input button:disabled{ opacity:0.5; cursor:default; }

.analyze-row{ text-align:center; margin:16px 0; }

.flash{ animation:goldFlash 0.5s ease; }
@keyframes goldFlash{
  0%{ background:rgba(184,137,43,0.45); }
  100%{ background:transparent; }
}

.month-content{ transition:opacity 0.15s ease; }
.month-content.is-fetching{ opacity:0.5; pointer-events:none; }

.page-next-enter-active, .page-next-leave-active,
.page-prev-enter-active, .page-prev-leave-active{
  transition:transform 0.32s ease, opacity 0.32s ease;
}
.page-next-enter-from{ transform:translateX(28px); opacity:0; }
.page-next-leave-to{ transform:translateX(-28px); opacity:0; }
.page-prev-enter-from{ transform:translateX(-28px); opacity:0; }
.page-prev-leave-to{ transform:translateX(28px); opacity:0; }

.fade-enter-active, .fade-leave-active{ transition:opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to{ opacity:0; }

.row-enter-active, .row-leave-active{ transition:opacity 0.25s ease, transform 0.25s ease; }
.row-enter-from, .row-leave-to{ opacity:0; transform:translateY(-6px); }
.row-leave-active{ position:relative; }

@media (max-width:640px){
  .page{ padding:26px 16px 22px 16px; }
  .masthead h1{ font-size:24px; }
  .ribbon{ right:18px; }
  .chat-log{ max-height:140px; }
  .chat-input{ flex-wrap:wrap; }
  .chat-input input{ min-width:0; flex-basis:100%; }

  .cat-bar-row{ flex-wrap:wrap; }
  .cat-bar-label{ width:auto; text-align:left; flex:1 1 auto; }
  .cat-bar-track{ flex-basis:100%; order:3; }
  .cat-bar-value{ width:auto; text-align:right; }

  .page table, .page thead, .page tbody, .page tr, .page td{ display:block; }
  .page thead{ display:none; }
  .amt-col, .cur-col, .freq-col, .end-col, .cat-col, .chk-col, .del-col{ width:auto; }

  .page tbody tr{
    position:relative;
    border:1px solid rgba(122,100,64,0.35);
    border-radius:6px;
    padding:12px 40px 4px 12px;
    margin-bottom:10px;
    background:rgba(255,255,255,0.18);
  }
  .page tbody tr.inactive{ background:rgba(255,255,255,0.08); }

  .page tbody td{ padding:7px 0; }
  .page td[data-label]{
    display:flex; align-items:center; justify-content:space-between; gap:10px;
  }
  .page td[data-label]::before{
    content:attr(data-label);
    font-size:10px; text-transform:uppercase; letter-spacing:0.6px;
    color:var(--ink-light); font-variant:small-caps; flex-shrink:0;
  }
  .page td[data-label] input, .page td[data-label] select{ text-align:right; }
  .page td[data-label]:last-of-type{ border-bottom:none; }

  .page td.cell-name{ font-size:15px; }
  .page td.cell-name input{ font-size:15px; font-weight:600; }

  .page td.del-col{
    position:absolute; top:10px; right:8px;
    width:auto; padding:0; border:none; text-align:right;
  }
  .page td.del-col .del-btn{ font-size:18px; }

  .totals{ flex-direction:column; align-items:stretch; gap:14px; }
  .totals-text{ max-width:none; }
  .seal-wrap{ margin:0 auto; }
}
</style>
