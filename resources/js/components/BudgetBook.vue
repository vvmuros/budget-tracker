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
          <button class="nav-btn" :disabled="fetching" @click="goPrev" :aria-label="t('prevMonth')">
            <svg viewBox="0 0 16 16" class="icon"><path d="M10 3 L5 8 L10 13" /></svg>
          </button>
          <span class="month-label">{{ currentPeriodLabel }}</span>
          <button class="nav-btn" :disabled="fetching" @click="goNext" :aria-label="t('nextMonth')">
            <svg viewBox="0 0 16 16" class="icon"><path d="M6 3 L11 8 L6 13" /></svg>
          </button>
        </div>
        <div class="year-toggle-row">
          <button class="reset-link icon-link" @click="toggleYearView">
            <svg v-if="!showYearView" viewBox="0 0 16 16" class="icon"><path d="M2 13 V8 M6 13 V4 M10 13 V6 M14 13 V2" /></svg>
            <svg v-else viewBox="0 0 16 16" class="icon"><path d="M9 3 L4 8 L9 13" /></svg>
            {{ showYearView ? t('backToMonth') : t('yearAnalysis') }}
          </button>
          <a class="lang-link" :href="switchLangUrl(lang === 'en' ? 'sr' : 'en')">{{ lang === 'en' ? 'SR' : 'EN' }}</a>
          <button class="theme-toggle" @click="toggleTheme" :aria-label="t('toggleTheme')">
            <svg v-if="isDark" viewBox="0 0 16 16" class="icon"><circle cx="8" cy="8" r="3" /><path d="M8 1.5 V3 M8 13 V14.5 M1.5 8 H3 M13 8 H14.5 M3.3 3.3 L4.4 4.4 M11.6 11.6 L12.7 12.7 M3.3 12.7 L4.4 11.6 M11.6 4.4 L12.7 3.3" /></svg>
            <svg v-else viewBox="0 0 16 16" class="icon"><path d="M13.5 9.8 A5.5 5.5 0 1 1 6.2 2.5 A4.3 4.3 0 0 0 13.5 9.8 Z" /></svg>
          </button>
        </div>

        <div class="chat-box">
          <div class="chat-log" ref="chatLogEl">
            <div v-for="(msg, idx) in chatLog" :key="idx" class="chat-msg" :class="msg.role">
              <span>{{ msg.text }}</span>
              <div v-if="msg.confirm" class="chat-confirm">
                <button class="add-row" @click="applyChatAction(msg)">{{ t('yes') }}</button>
                <button class="reset-link" @click="rejectChatAction(msg)">{{ t('no') }}</button>
              </div>
            </div>
          </div>
          <form class="chat-input" @submit.prevent="sendChatMessage">
            <input type="text" v-model="chatInput" :placeholder="t('chatPlaceholder')" :disabled="chatSending || isListening">
            <button
              v-if="voiceSupported" type="button" class="mic-btn" :class="{ listening: isListening }"
              :disabled="chatSending" @click="toggleVoiceInput" :aria-label="t('voiceLabel')"
            >
              <svg v-if="!isListening" viewBox="0 0 16 16" class="icon"><path d="M8 2.5 a2 2 0 0 1 2 2 v3.5 a2 2 0 0 1 -4 0 v-3.5 a2 2 0 0 1 2 -2 Z" /><path d="M4.5 8 a3.5 3.5 0 0 0 7 0 M8 11.5 V13.5 M6 13.5 H10" /></svg>
              <svg v-else viewBox="0 0 16 16" class="icon"><rect x="4" y="4" width="8" height="8" rx="1" /></svg>
            </button>
            <button type="button" class="mic-btn" :disabled="chatSending || scanningReceipt" @click="triggerReceiptPicker" :aria-label="t('receiptLabel')">
              <svg viewBox="0 0 16 16" class="icon"><path d="M2 6 a1 1 0 0 1 1 -1 h1.5 l1 -1.5 h5 l1 1.5 H13 a1 1 0 0 1 1 1 v6 a1 1 0 0 1 -1 1 H3 a1 1 0 0 1 -1 -1 Z" /><circle cx="8" cy="9" r="2.3" /></svg>
            </button>
            <input ref="receiptInputEl" type="file" accept="image/*" capture="environment" class="receipt-input" @change="onReceiptSelected">
            <button type="submit" :disabled="chatSending || isListening || !chatInput.trim()">{{ t('send') }}</button>
          </form>
        </div>

        <div class="masthead">
          <div class="eyebrow">Anno <span>{{ yearNow }}</span> · {{ t('annoTag') }}</div>
          <h1>{{ t('title') }}</h1>
          <div class="sub">{{ t('subtitle') }}</div>
          <svg class="flourish" viewBox="0 0 120 10"><path d="M0 5 H45 M75 5 H120 M55 5 a5 5 0 1 0 10 0 a5 5 0 1 0 -10 0" stroke="#B8892B" stroke-width="1" fill="none"/></svg>
          <div v-if="!loading" class="greeting">{{ dataGreeting }}</div>
        </div>

        <div v-if="loading" class="foot-note">{{ t('loadingBook') }}</div>

        <div v-else-if="showYearView" class="year-view">
          <div class="section-title">{{ t('yearAnalysisHeading') }} {{ currentYearLabel }}</div>

          <div v-if="yearLoading" class="foot-note">{{ t('loadingAnalysis') }}</div>

          <template v-else-if="yearMonths.length">
            <div class="year-legend">
              <span class="legend-item"><span class="swatch" :style="{ background: YEAR_COLORS.income }"></span>{{ t('income') }}</span>
              <span class="legend-item"><span class="swatch" :style="{ background: YEAR_COLORS.expense }"></span>{{ t('expenses') }}</span>
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
              ><title>{{ periodLabel(m.period) }} — {{ t('income') }}: {{ fmt(m.income) }} RSD</title></circle>
              <circle
                v-for="(m, i) in yearMonths" :key="'exp'+i"
                :cx="yearChart.x(i)" :cy="yearChart.y(m.expense)" r="4"
                :fill="YEAR_COLORS.expense" stroke="var(--parchment)" stroke-width="2"
              ><title>{{ periodLabel(m.period) }} — {{ t('expenses') }}: {{ fmt(m.expense) }} RSD</title></circle>
              <text
                v-for="(m, i) in yearMonths" :key="'lbl'+i"
                :x="yearChart.x(i)" :y="yearChart.height - 8" class="year-axis-label" text-anchor="middle"
              >{{ monthAbbrev(m.period) }}</text>
            </svg>

            <table class="year-table">
              <thead><tr><th>{{ t('month') }}</th><th>{{ t('income') }}</th><th>{{ t('expenses') }}</th><th>{{ t('net') }}</th></tr></thead>
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

          <div v-else class="foot-note">{{ t('noYearData') }}</div>
        </div>

        <Transition v-else :name="navDirection === 'next' ? 'page-next' : 'page-prev'" mode="out-in">
          <div :key="currentPeriod" class="month-content" :class="{ 'is-fetching': fetching }">

            <div v-if="bannerVisible" class="banner">
              <span>{{ bannerPrefix(bannerPreviousLabel) }}<strong>{{ signed(bannerPreviousNet) }} RSD</strong>{{ bannerSuffix(bannerPreviousLabel) }}</span>
              <div class="banner-actions">
                <input type="number" v-model.number="bannerAmount" step="1">
                <button class="add-row" @click="confirmBanner">{{ t('addToSavings') }}</button>
                <button class="reset-link" @click="dismissBanner">{{ t('noThanks') }}</button>
              </div>
            </div>

            <div class="section-title">{{ t('income') }}</div>
            <table>
              <thead>
                <tr>
                  <th style="width:auto">{{ t('item') }}</th>
                  <th class="amt-col">{{ t('amount') }}</th>
                  <th class="cur-col">{{ t('currency') }}</th>
                  <th class="freq-col">{{ t('frequency') }}</th>
                  <th class="chk-col">{{ t('active') }}</th>
                  <th class="del-col"></th>
                </tr>
              </thead>
              <TransitionGroup tag="tbody" name="row">
                <tr v-for="item in visibleIncome" :key="keyFor(item)" :class="{ inactive: !item.active }">
                  <td class="cell-name"><input type="text" v-model="item.name" :title="item.name" @change="saveIncome"></td>
                  <td class="amt-col" :data-label="t('amount')"><input type="number" v-model.number="item.amount" step="1" @change="saveIncome"></td>
                  <td class="cur-col" :data-label="t('currency')">
                    <select v-model="item.currency" @change="saveIncome">
                      <option value="RSD">RSD</option>
                      <option value="EUR">EUR</option>
                      <option value="USD">USD</option>
                    </select>
                  </td>
                  <td class="freq-col" :data-label="t('frequency')">
                    <select v-model.number="item.freq" @change="saveIncome">
                      <option :value="1">{{ t('monthly') }}</option>
                      <option :value="2">{{ t('every2Months') }}</option>
                      <option :value="3">{{ t('every3Months') }}</option>
                      <option :value="0">{{ t('oneTime') }}</option>
                    </select>
                  </td>
                  <td class="chk-col" :data-label="t('active')"><input type="checkbox" v-model="item.active" @change="saveIncome"></td>
                  <td class="del-col"><button class="del-btn" @click="removeRow(income, item, saveIncome)" :aria-label="t('deleteRow')"><svg viewBox="0 0 16 16" class="icon"><path d="M4 4 L12 12 M12 4 L4 12" /></svg></button></td>
                </tr>
              </TransitionGroup>
            </table>
            <button class="add-row" @click="addRow(income, t('newIncomeName'), saveIncome)"><svg viewBox="0 0 16 16" class="icon"><path d="M8 3 V13 M3 8 H13" /></svg> {{ t('addIncome') }}</button>
            <button v-if="oneTimeIncomeCount > 0" class="reset-link load-more-btn" @click="showOneTimeIncome = !showOneTimeIncome">
              {{ showOneTimeIncome ? t('hideOneTimeIncome') : t('showOneTimeIncome') }} ({{ oneTimeIncomeCount }})
            </button>

            <div class="section-title">{{ t('expenses') }}</div>
            <table>
              <thead>
                <tr>
                  <th style="width:auto">{{ t('item') }}</th>
                  <th class="amt-col">{{ t('amount') }}</th>
                  <th class="cur-col">{{ t('currency') }}</th>
                  <th class="freq-col">{{ t('frequency') }}</th>
                  <th class="end-col">{{ t('untilMonth') }}</th>
                  <th class="cat-col">{{ t('category') }}</th>
                  <th class="chk-col">{{ t('active') }}</th>
                  <th class="del-col"></th>
                </tr>
              </thead>
              <TransitionGroup tag="tbody" name="row">
                <tr v-for="item in visibleExpenses" :key="keyFor(item)" :class="{ inactive: !isExpenseActive(item) }">
                  <td class="cell-name">
                    <span class="cat-dot" :style="{ background: categoryColor(item.category, EXPENSE_CATEGORIES) }"></span>
                    <input type="text" v-model="item.name" :title="item.name" @change="saveExpenses">
                  </td>
                  <td class="amt-col" :data-label="t('amount')"><input type="number" v-model.number="item.amount" step="1" @change="saveExpenses"></td>
                  <td class="cur-col" :data-label="t('currency')">
                    <select v-model="item.currency" @change="saveExpenses">
                      <option value="RSD">RSD</option>
                      <option value="EUR">EUR</option>
                      <option value="USD">USD</option>
                    </select>
                  </td>
                  <td class="freq-col" :data-label="t('frequency')">
                    <select v-model.number="item.freq" @change="saveExpenses">
                      <option :value="1">{{ t('monthly') }}</option>
                      <option :value="2">{{ t('every2Months') }}</option>
                      <option :value="3">{{ t('every3Months') }}</option>
                      <option :value="0">{{ t('oneTime') }}</option>
                    </select>
                  </td>
                  <td class="end-col" :data-label="t('untilMonth')">
                    <button type="button" class="month-picker-btn" @click="openMonthPicker($event)">{{ item.endPeriod ? formatEndPeriod(item.endPeriod) : '—' }}</button>
                    <input type="month" v-model="item.endPeriod" class="month-picker-input" @change="saveExpenses">
                  </td>
                  <td class="cat-col" :data-label="t('category')">
                    <select v-model="item.category" @change="saveExpenses">
                      <option v-for="cat in EXPENSE_CATEGORIES" :key="cat" :value="cat">{{ categoryLabel(cat) }}</option>
                    </select>
                  </td>
                  <td class="chk-col" :data-label="t('active')"><input type="checkbox" v-model="item.active" @change="saveExpenses"></td>
                  <td class="del-col"><button class="del-btn" @click="removeRow(expenses, item, saveExpenses)" :aria-label="t('deleteRow')"><svg viewBox="0 0 16 16" class="icon"><path d="M4 4 L12 12 M12 4 L4 12" /></svg></button></td>
                </tr>
              </TransitionGroup>
            </table>
            <button class="add-row" @click="addRow(expenses, t('newExpenseName'), saveExpenses)"><svg viewBox="0 0 16 16" class="icon"><path d="M8 3 V13 M3 8 H13" /></svg> {{ t('addExpense') }}</button>
            <button v-if="oneTimeExpensesCount > 0" class="reset-link load-more-btn" @click="showOneTimeExpenses = !showOneTimeExpenses">
              {{ showOneTimeExpenses ? t('hideOneTimeExpenses') : t('showOneTimeExpenses') }} ({{ oneTimeExpensesCount }})
            </button>

            <div class="chart-section" v-if="categoryBreakdown.length">
              <div class="cat-bar-row" v-for="slice in categoryBreakdown" :key="slice.category">
                <div class="cat-bar-label">{{ categoryLabel(slice.category) }}</div>
                <div class="cat-bar-track">
                  <div class="cat-bar-fill" :style="{ width: slice.pct + '%', background: slice.color }"></div>
                </div>
                <div class="cat-bar-value">{{ fmt(slice.amount) }} RSD <span class="cat-bar-pct">({{ slice.pct.toFixed(0) }}%)</span></div>
              </div>
            </div>

            <div class="section-title">{{ t('savingsAndAssets') }}</div>
            <table>
              <thead>
                <tr>
                  <th style="width:auto">{{ t('item') }}</th>
                  <th class="amt-col">{{ t('amount') }}</th>
                  <th class="cur-col">{{ t('currency') }}</th>
                  <th class="cat-col">{{ t('category') }}</th>
                  <th class="del-col"></th>
                </tr>
              </thead>
              <TransitionGroup tag="tbody" name="row">
                <tr v-for="item in savings" :key="keyFor(item)">
                  <td class="cell-name">
                    <span class="cat-dot" :style="{ background: categoryColor(item.category, SAVINGS_CATEGORIES) }"></span>
                    <input type="text" v-model="item.name" :title="item.name" @change="saveSavings">
                  </td>
                  <td class="amt-col" :data-label="t('amount')"><input type="number" v-model.number="item.amount" step="1" @change="saveSavings"></td>
                  <td class="cur-col" :data-label="t('currency')">
                    <select v-model="item.currency" @change="saveSavings">
                      <option value="RSD">RSD</option>
                      <option value="EUR">EUR</option>
                      <option value="USD">USD</option>
                    </select>
                  </td>
                  <td class="cat-col" :data-label="t('category')">
                    <select v-model="item.category" @change="saveSavings">
                      <option v-for="cat in SAVINGS_CATEGORIES" :key="cat" :value="cat">{{ categoryLabel(cat) }}</option>
                    </select>
                  </td>
                  <td class="del-col"><button class="del-btn" @click="removeRow(savings, item, saveSavings)" :aria-label="t('deleteRow')"><svg viewBox="0 0 16 16" class="icon"><path d="M4 4 L12 12 M12 4 L4 12" /></svg></button></td>
                </tr>
              </TransitionGroup>
            </table>
            <button class="add-row" @click="addSavingsRow"><svg viewBox="0 0 16 16" class="icon"><path d="M8 3 V13 M3 8 H13" /></svg> {{ t('addSaving') }}</button>

            <div class="rates">
              <div>
                <label>{{ t('usdRate') }}</label>
                <input type="number" v-model.number="rates.usd" step="0.01" @change="saveRates">
              </div>
              <div>
                <label>{{ t('eurRate') }}</label>
                <input type="number" v-model.number="rates.eur" step="0.01" @change="saveRates">
              </div>
              <div class="note">{{ t('rateNote') }}</div>
            </div>

            <div class="section-title">{{ t('currencyConverter') }}</div>
            <div class="converter">
              <div>
                <label>{{ t('amount') }}</label>
                <input type="number" v-model.number="conv.amount" step="1">
              </div>
              <div>
                <label>{{ t('fromLabel') }}</label>
                <select v-model="conv.from">
                  <option value="RSD">RSD</option>
                  <option value="EUR">EUR</option>
                  <option value="USD">USD</option>
                </select>
              </div>
              <div class="eq">=</div>
              <div>
                <label>{{ t('toLabel') }}</label>
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
                <div class="row"><span class="lbl">{{ t('incomeThisMonth') }}</span><span class="val" :class="{ flash: flash.income }">{{ fmt(incThis) }} RSD</span></div>
                <div class="row"><span class="lbl">{{ t('expensesThisMonth') }}</span><span class="val" :class="{ flash: flash.expense }">{{ fmt(expThis) }} RSD</span></div>
                <div class="row main"><span class="lbl">{{ t('netThisMonth') }}</span><span class="val" :class="[netThis >= 0 ? 'pos' : 'neg', { flash: flash.net }]">{{ signed(netThis) }} RSD</span></div>
                <div class="row"><span class="lbl">{{ t('avgNetMonthly') }}</span><span class="val" :class="netAvg >= 0 ? 'pos' : 'neg'">{{ signed(netAvg) }} RSD</span></div>
              </div>
              <div class="seal-wrap">
                <div class="lbl">{{ t('netLower') }}</div>
                <div class="val" :class="{ flash: flash.net }">{{ signed(netThis) }}</div>
                <div class="cur">{{ t('rsdThisMonth') }}</div>
              </div>
            </div>

            <div class="analyze-row">
              <button class="add-row" :disabled="analyzing" @click="analyzeMonth">
                <svg viewBox="0 0 16 16" class="icon"><circle cx="7" cy="7" r="4.5" /><path d="M10.2 10.2 L14 14" /></svg>
                {{ analyzing ? t('analyzing') : t('analyzeMonth') }}
              </button>
            </div>
            <div v-if="analysisText || analysisError" class="banner">
              <span v-if="analysisText">{{ analysisText }}</span>
              <span v-else>{{ analysisError }}</span>
              <div class="banner-actions">
                <button class="reset-link" @click="closeAnalysis">{{ t('close') }}</button>
              </div>
            </div>

            <div class="savings-line">
              {{ t('totalSavings') }} <strong :class="{ flash: flash.savings }">{{ fmt(savTotal) }} RSD</strong>
              &nbsp;·&nbsp; <span>≈ {{ fmt2(savTotal / rates.eur) }} €</span>
              &nbsp;·&nbsp; <span>≈ {{ fmt2(savTotal / rates.usd) }} $</span>
            </div>

            <div class="foot-note">
              {{ t('footNote') }}
              &nbsp;·&nbsp; <button class="reset-link" @click="resetAll">{{ t('resetDefaults') }}</button>
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

function readLangCookie() {
  const match = document.cookie.match(/(?:^|; )lang=([^;]*)/);
  return match ? decodeURIComponent(match[1]) : 'sr';
}
const lang = ref(readLangCookie());

const TRANSLATIONS = {
  sr: {
    prevMonth: 'Prethodni mesec',
    nextMonth: 'Sledeći mesec',
    yearAnalysis: 'Analiza godine',
    backToMonth: 'Nazad na mesec',
    toggleTheme: 'Promeni temu',
    chatPlaceholder: 'npr. potrošio sam 500 na kafu',
    voiceLabel: 'Govorom unesi trošak',
    receiptLabel: 'Skeniraj račun',
    send: 'Pošalji',
    yes: 'Da',
    no: 'Ne',
    annoTag: 'lična evidencija',
    title: 'Knjižica troškova',
    subtitle: 'primanja, izdaci i štednja, po starom običaju',
    loadingBook: 'Učitavanje knjižice…',
    yearAnalysisHeading: 'Analiza',
    loadingAnalysis: 'Učitavanje analize…',
    income: 'Primanja',
    expenses: 'Troškovi',
    savingsAndAssets: 'Štednja i imovina',
    month: 'Mesec',
    net: 'Neto',
    noYearData: 'Nema još podataka za analizu ove godine.',
    addToSavings: 'Dodaj u štednju',
    noThanks: 'Ne, hvala',
    item: 'Stavka',
    amount: 'Iznos',
    currency: 'Valuta',
    frequency: 'Učestalost',
    untilMonth: 'Do meseca',
    category: 'Kategorija',
    active: 'Akt.',
    monthly: 'mesečno',
    every2Months: 'na 2 meseca',
    every3Months: 'na 3 meseca',
    oneTime: 'jednokratno',
    deleteRow: 'Obriši red',
    addIncome: 'upiši primanje',
    addExpense: 'upiši trošak',
    addSaving: 'upiši stavku štednje',
    hideOneTimeIncome: 'Sakrij jednokratna primanja',
    showOneTimeIncome: 'Prikaži jednokratna primanja',
    hideOneTimeExpenses: 'Sakrij jednokratne troškove',
    showOneTimeExpenses: 'Prikaži jednokratne troškove',
    usdRate: '1 USD =',
    eurRate: '1 EUR =',
    rateNote: 'srednji kurs NBS, po volji izmeni',
    currencyConverter: 'Konverter valuta',
    fromLabel: 'Iz',
    toLabel: 'U',
    incomeThisMonth: 'primanja ovog meseca',
    expensesThisMonth: 'troškovi ovog meseca',
    netThisMonth: 'neto ovog meseca',
    avgNetMonthly: 'prosečan neto mesečno',
    netLower: 'neto',
    rsdThisMonth: 'RSD ovog meseca',
    analyzing: 'Analiziram…',
    analyzeMonth: 'Analiziraj mesec',
    close: 'Zatvori',
    totalSavings: 'Ukupna ušteđevina:',
    footNote: 'Isključi "Akt." za stavke koje ovog meseca ne dospevaju.',
    resetDefaults: 'vrati na početne vrednosti',
    newIncomeName: 'Novo primanje',
    newExpenseName: 'Nova stavka',
    newSavingName: 'Nova stavka',
    transferredFrom: 'Preneto iz ',
    thinking: 'Razmišljam…',
    unclear: 'Nisam siguran šta si mislio/la — probaj konkretnije (npr. "potrošio sam 500 dinara na kafu").',
    chatError: 'Nešto nije u redu, probaj ponovo.',
    added: '✓ Dodato.',
    rejected: 'U redu, ništa nisam dodao.',
    receiptSent: '📷 Poslata slika računa',
    readingReceipt: 'Čitam račun…',
    receiptUnclear: 'Nisam uspeo da pročitam račun sa slike — probaj jasniju fotografiju ili ukucaj ručno.',
    receiptError: 'Nešto nije u redu sa slikom, probaj ponovo.',
    imageLoadError: 'Neuspešno učitavanje slike.',
    analysisUnavailable: 'Analiza trenutno nije dostupna.',
    voiceNoMatch: 'Nisam razumeo šta si rekao/la — probaj ponovo, sporije i jasnije.',
    voiceNotAllowed: 'Nisam dobio dozvolu za mikrofon — proveri podešavanja browsera/telefona.',
    voiceAudioCapture: 'Ne mogu da pristupim mikrofonu na ovom uređaju.',
    voiceNetwork: 'Problem sa mrežom tokom prepoznavanja govora — probaj ponovo.',
    voiceLangNotSupported: 'Srpski jezik nije podržan za prepoznavanje govora na ovom uređaju.',
    voiceGenericError: (code) => `Nisam uspeo da prepoznam govor (${code}) — probaj ponovo ili ukucaj ručno.`,
    chatActionExpense: 'trošak',
    chatActionIncome: 'primanje',
    chatActionSaving: 'stavku štednje',
    confirmAddPrefix: 'Da dodam',
    categoryWord: 'kategorija',
  },
  en: {
    prevMonth: 'Previous month',
    nextMonth: 'Next month',
    yearAnalysis: 'Yearly analysis',
    backToMonth: 'Back to month',
    toggleTheme: 'Toggle theme',
    chatPlaceholder: 'e.g. spent 500 on coffee',
    voiceLabel: 'Add an expense by voice',
    receiptLabel: 'Scan a receipt',
    send: 'Send',
    yes: 'Yes',
    no: 'No',
    annoTag: 'personal ledger',
    title: 'Budget Book',
    subtitle: 'income, expenses and savings, the old-fashioned way',
    loadingBook: 'Loading the book…',
    yearAnalysisHeading: 'Analysis',
    loadingAnalysis: 'Loading analysis…',
    income: 'Income',
    expenses: 'Expenses',
    savingsAndAssets: 'Savings & Assets',
    month: 'Month',
    net: 'Net',
    noYearData: 'No data yet to analyze this year.',
    addToSavings: 'Add to savings',
    noThanks: 'No thanks',
    item: 'Item',
    amount: 'Amount',
    currency: 'Currency',
    frequency: 'Frequency',
    untilMonth: 'Until month',
    category: 'Category',
    active: 'Active',
    monthly: 'monthly',
    every2Months: 'every 2 months',
    every3Months: 'every 3 months',
    oneTime: 'one-time',
    deleteRow: 'Delete row',
    addIncome: 'add income',
    addExpense: 'add expense',
    addSaving: 'add savings item',
    hideOneTimeIncome: 'Hide one-time income',
    showOneTimeIncome: 'Show one-time income',
    hideOneTimeExpenses: 'Hide one-time expenses',
    showOneTimeExpenses: 'Show one-time expenses',
    usdRate: '1 USD =',
    eurRate: '1 EUR =',
    rateNote: 'NBS mid rate, adjust as you like',
    currencyConverter: 'Currency converter',
    fromLabel: 'From',
    toLabel: 'To',
    incomeThisMonth: 'income this month',
    expensesThisMonth: 'expenses this month',
    netThisMonth: 'net this month',
    avgNetMonthly: 'average monthly net',
    netLower: 'net',
    rsdThisMonth: 'RSD this month',
    analyzing: 'Analyzing…',
    analyzeMonth: 'Analyze month',
    close: 'Close',
    totalSavings: 'Total savings:',
    footNote: 'Turn off "Active" for items not due this month.',
    resetDefaults: 'reset to defaults',
    newIncomeName: 'New income',
    newExpenseName: 'New item',
    newSavingName: 'New item',
    transferredFrom: 'Transferred from ',
    thinking: 'Thinking…',
    unclear: 'Not sure what you meant — try being more specific (e.g. "spent 500 dinars on coffee").',
    chatError: 'Something went wrong, try again.',
    added: '✓ Added.',
    rejected: "OK, I didn't add anything.",
    receiptSent: '📷 Receipt photo sent',
    readingReceipt: 'Reading the receipt…',
    receiptUnclear: "Couldn't read the receipt from the photo — try a clearer picture or type it manually.",
    receiptError: 'Something went wrong with the image, try again.',
    imageLoadError: 'Failed to load the image.',
    analysisUnavailable: 'Analysis is currently unavailable.',
    voiceNoMatch: "Didn't catch that — try again, slower and clearer.",
    voiceNotAllowed: 'Microphone permission denied — check your browser/phone settings.',
    voiceAudioCapture: "Can't access the microphone on this device.",
    voiceNetwork: 'Network problem during speech recognition — try again.',
    voiceLangNotSupported: 'Speech recognition for this language is not supported on this device.',
    voiceGenericError: (code) => `Couldn't recognize speech (${code}) — try again or type it manually.`,
    chatActionExpense: 'expense',
    chatActionIncome: 'income',
    chatActionSaving: 'savings item',
    confirmAddPrefix: 'Add',
    categoryWord: 'category',
  },
};

function t(key) {
  return TRANSLATIONS[lang.value]?.[key] ?? key;
}

const MONTH_NAMES_BY_LANG = {
  sr: ['Januar', 'Februar', 'Mart', 'April', 'Maj', 'Jun', 'Jul', 'Avgust', 'Septembar', 'Oktobar', 'Novembar', 'Decembar'],
  en: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
};
const MONTH_NAMES = computed(() => MONTH_NAMES_BY_LANG[lang.value]);

const CATEGORY_LABELS_EN = {
  Stanovanje: 'Housing', Hrana: 'Food', Prevoz: 'Transport', Zdravlje: 'Health',
  Zabava: 'Entertainment', Računi: 'Bills', Otplate: 'Debt payments', Ostalo: 'Other',
  Gotovina: 'Cash', Štednja: 'Savings', Investicije: 'Investments', Nekretnine: 'Real estate',
};
function categoryLabel(cat) {
  return lang.value === 'en' ? (CATEGORY_LABELS_EN[cat] ?? cat) : cat;
}

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
  const name = MONTH_NAMES.value[m - 1];
  return lang.value === 'en' ? `${name} ${y}` : `${name} ${y}.`;
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

function categoryColor(category, list) {
  const idx = list.indexOf(category);
  return CATEGORY_COLORS[idx] ?? CATEGORY_COLORS[CATEGORY_COLORS.length - 1];
}

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
  greetingSeed.value = Math.floor(Math.random() * 100);

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
  savings.push({ name: t('transferredFrom') + bannerPreviousLabel.value, amount: Math.round(bannerAmount.value) || 0, currency: 'RSD', category: 'Štednja' });
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
  savings.push({ name: t('newSavingName'), amount: 0, currency: 'RSD', category: 'Ostalo' });
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

const GREETING_TEMPLATES = {
  sr: [
    (name, amount) => `O ne, ${name}? Opet ${amount} RSD?`,
    (name, amount) => `${name} — ozbiljno, opet?`,
    (name, amount) => `Najveći trošak ovog meseca: ${name} (${amount} RSD). Vau.`,
    (name, amount) => `${amount} RSD na "${name}"? Dobro, dobro.`,
    (name, amount) => `Knjiga primećuje: ${name} ti je pojeo ${amount} RSD.`,
  ],
  en: [
    (name, amount) => `Oh no, ${name} again? ${amount} RSD?`,
    (name, amount) => `${name} — seriously, again?`,
    (name, amount) => `Biggest expense this month: ${name} (${amount} RSD). Wow.`,
    (name, amount) => `${amount} RSD on "${name}"? Okay then.`,
    (name, amount) => `The ledger notices: ${name} ate ${amount} RSD of your money.`,
  ],
};
const FALLBACK_GREETINGS = {
  sr: ['Prazna stranica — hajde da je popunimo.', 'Dobrodošao nazad u knjižicu.', 'Šta ima novo ovog meseca?'],
  en: ['A blank page — let\'s fill it in.', 'Welcome back to the book.', "What's new this month?"],
};
const greetingSeed = ref(Math.floor(Math.random() * 100));

const dataGreeting = computed(() => {
  const active = expenses.filter(isExpenseActive);
  const templates = GREETING_TEMPLATES[lang.value] ?? GREETING_TEMPLATES.sr;
  const fallbacks = FALLBACK_GREETINGS[lang.value] ?? FALLBACK_GREETINGS.sr;

  if (!active.length) {
    return fallbacks[greetingSeed.value % fallbacks.length];
  }

  const biggest = active.reduce((max, it) => (
    toRSD(it.amount, it.currency) > toRSD(max.amount, max.currency) ? it : max
  ));

  return templates[greetingSeed.value % templates.length](biggest.name, fmt(toRSD(biggest.amount, biggest.currency)));
});

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
      color: categoryColor(category, EXPENSE_CATEGORIES),
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

const isDark = ref(document.documentElement.getAttribute('data-theme') === 'dark');

function toggleTheme() {
  isDark.value = !isDark.value;
  const theme = isDark.value ? 'dark' : 'light';
  document.documentElement.setAttribute('data-theme', theme);
  localStorage.setItem('theme', theme);
}

const chatLog = reactive([]);
const chatInput = ref('');
const chatSending = ref(false);
const chatLogEl = ref(null);

const CHAT_ACTION_LABELS = computed(() => ({
  add_expense: t('chatActionExpense'),
  add_income: t('chatActionIncome'),
  add_saving: t('chatActionSaving'),
}));
const FREQ_LABELS = computed(() => ({
  0: t('oneTime'), 1: t('monthly'), 2: t('every2Months'), 3: t('every3Months'),
}));

function scrollChatToBottom() {
  nextTick(() => {
    if (chatLogEl.value) chatLogEl.value.scrollTop = chatLogEl.value.scrollHeight;
  });
}

const SpeechRecognitionCtor = window.SpeechRecognition || window.webkitSpeechRecognition;
const voiceSupported = !!SpeechRecognitionCtor;
const isListening = ref(false);
let recognition = null;

function toggleVoiceInput() {
  if (!voiceSupported) return;

  if (isListening.value) {
    recognition?.stop();
    return;
  }

  recognition = new SpeechRecognitionCtor();
  recognition.lang = lang.value === 'en' ? 'en-US' : 'sr-RS';
  recognition.interimResults = false;
  recognition.maxAlternatives = 1;

  const VOICE_ERROR_MESSAGES = {
    'not-allowed': t('voiceNotAllowed'),
    'service-not-allowed': t('voiceNotAllowed'),
    'audio-capture': t('voiceAudioCapture'),
    'network': t('voiceNetwork'),
    'language-not-supported': t('voiceLangNotSupported'),
  };

  recognition.onstart = () => { isListening.value = true; };
  recognition.onend = () => { isListening.value = false; };
  recognition.onnomatch = () => {
    chatLog.push({ role: 'assistant', text: t('voiceNoMatch') });
    scrollChatToBottom();
  };
  recognition.onerror = (event) => {
    isListening.value = false;
    console.error('SpeechRecognition error:', event.error);
    if (event.error === 'no-speech' || event.error === 'aborted') return;
    chatLog.push({
      role: 'assistant',
      text: VOICE_ERROR_MESSAGES[event.error] || TRANSLATIONS[lang.value].voiceGenericError(event.error),
    });
    scrollChatToBottom();
  };
  recognition.onresult = (event) => {
    chatInput.value = event.results[0][0].transcript;
    sendChatMessage();
  };

  recognition.start();
}

async function sendChatMessage() {
  const text = chatInput.value.trim();
  if (!text) return;

  chatLog.push({ role: 'user', text });
  chatInput.value = '';
  chatSending.value = true;
  chatLog.push({ role: 'assistant', text: t('thinking') });
  scrollChatToBottom();

  try {
    const { data } = await axios.post('/api/budget/chat', {
      message: text,
      expense_categories: EXPENSE_CATEGORIES,
      savings_categories: SAVINGS_CATEGORIES,
    });
    chatLog.pop();

    const validActions = ['add_expense', 'add_income', 'add_saving'];
    if (validActions.includes(data.action) && data.name && data.amount > 0) {
      const currency = data.currency || 'RSD';
      const freq = data.freq ?? 1;
      const category = data.category || 'Ostalo';
      const freqText = data.action === 'add_saving' ? '' : `, ${FREQ_LABELS.value[freq] ?? t('monthly')}`;
      const catText = data.action === 'add_income' ? '' : `, ${t('categoryWord')} ${categoryLabel(category)}`;
      chatLog.push({
        role: 'assistant',
        text: `${t('confirmAddPrefix')} ${CHAT_ACTION_LABELS.value[data.action]} "${data.name}": ${data.amount} ${currency}${freqText}${catText}?`,
        confirm: { action: data.action, name: data.name, amount: data.amount, currency, freq, category },
      });
    } else {
      chatLog.push({ role: 'assistant', text: t('unclear') });
    }
  } catch (e) {
    chatLog.pop();
    chatLog.push({ role: 'assistant', text: t('chatError') });
  } finally {
    chatSending.value = false;
    scrollChatToBottom();
  }
}

function applyChatAction(msg) {
  const { action, name, amount, currency, freq, category } = msg.confirm;
  if (action === 'add_expense') {
    expenses.push({ name, amount, currency, freq, active: true, endPeriod: null, category: category || 'Ostalo' });
    saveExpenses();
  } else if (action === 'add_income') {
    income.push({ name, amount, currency, freq, active: true });
    saveIncome();
  } else if (action === 'add_saving') {
    savings.push({ name, amount, currency, category: category || 'Ostalo' });
    saveSavings();
  }
  msg.confirm = null;
  chatLog.push({ role: 'assistant', text: t('added') });
  scrollChatToBottom();
}

function rejectChatAction(msg) {
  msg.confirm = null;
  chatLog.push({ role: 'assistant', text: t('rejected') });
  scrollChatToBottom();
}

const receiptInputEl = ref(null);
const scanningReceipt = ref(false);

function triggerReceiptPicker() {
  receiptInputEl.value?.click();
}

function compressImageFile(file, maxDimension = 1024, quality = 0.7) {
  return new Promise((resolve, reject) => {
    const img = new Image();
    const objectUrl = URL.createObjectURL(file);

    img.onload = () => {
      URL.revokeObjectURL(objectUrl);

      let { width, height } = img;
      if (width > height && width > maxDimension) {
        height = Math.round(height * (maxDimension / width));
        width = maxDimension;
      } else if (height > maxDimension) {
        width = Math.round(width * (maxDimension / height));
        height = maxDimension;
      }

      const canvas = document.createElement('canvas');
      canvas.width = width;
      canvas.height = height;
      canvas.getContext('2d').drawImage(img, 0, 0, width, height);

      const dataUrl = canvas.toDataURL('image/jpeg', quality);
      resolve(dataUrl.split(',')[1]);
    };
    img.onerror = () => {
      URL.revokeObjectURL(objectUrl);
      reject(new Error(t('imageLoadError')));
    };
    img.src = objectUrl;
  });
}

async function onReceiptSelected(event) {
  const file = event.target.files?.[0];
  event.target.value = '';
  if (!file) return;

  scanningReceipt.value = true;
  chatLog.push({ role: 'user', text: t('receiptSent') });
  chatLog.push({ role: 'assistant', text: t('readingReceipt') });
  scrollChatToBottom();

  try {
    const base64 = await compressImageFile(file);
    const { data } = await axios.post('/api/budget/receipt', {
      image: base64,
      mime_type: 'image/jpeg',
      expense_categories: EXPENSE_CATEGORIES,
    });
    chatLog.pop();

    if (data.action === 'add_expense' && data.name && data.amount > 0) {
      const currency = data.currency || 'RSD';
      const category = data.category || 'Ostalo';
      chatLog.push({
        role: 'assistant',
        text: `${t('confirmAddPrefix')} ${t('chatActionExpense')} "${data.name}": ${data.amount} ${currency}, ${t('categoryWord')} ${categoryLabel(category)}?`,
        confirm: { action: 'add_expense', name: data.name, amount: data.amount, currency, freq: 0, category },
      });
    } else {
      chatLog.push({ role: 'assistant', text: t('receiptUnclear') });
    }
  } catch (e) {
    chatLog.pop();
    chatLog.push({ role: 'assistant', text: t('receiptError') });
  } finally {
    scanningReceipt.value = false;
    scrollChatToBottom();
  }
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
    analysisError.value = t('analysisUnavailable');
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
  return MONTH_NAMES.value[m - 1].slice(0, 3);
}

function formatEndPeriod(period) {
  const [y] = period.split('-');
  return monthAbbrev(period) + ' ' + y;
}

function openMonthPicker(event) {
  const input = event.currentTarget.nextElementSibling;
  if (!input) return;
  if (typeof input.showPicker === 'function') {
    input.showPicker();
  } else {
    input.focus();
  }
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
    gridlines: [0, 0.25, 0.5, 0.75, 1].map(pct => height - padding - pct * (height - padding * 2)),
  };
});

function bannerPrefix(label) {
  return lang.value === 'en' ? `You have ` : `Iz ${label} ti je ostalo `;
}
function bannerSuffix(label) {
  return lang.value === 'en' ? ` (net) left over from ${label}. Add it to savings?` : ` (neto). Dodati u štednju?`;
}

function switchLangUrl(target) {
  return '/lang/' + target;
}
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
  --card-tint:rgba(255,255,255,0.18);
  --card-tint-dim:rgba(255,255,255,0.08);
  --panel-tint:rgba(255,255,255,0.12);
}
@media (prefers-color-scheme: dark){
  :root:not([data-theme="light"]){
    --parchment:#241A12;
    --parchment-dark:#1C130D;
    --ink:#EDE0C8;
    --ink-light:#B8A588;
    --gilt:#C9A244;
    --gilt-bright:#E8C468;
    --seal:#C6605F;
    --seal-hi:#D97A78;
    --pos:#5FAE7C;
    --card-tint:rgba(0,0,0,0.22);
    --card-tint-dim:rgba(0,0,0,0.12);
    --panel-tint:rgba(0,0,0,0.18);
  }
}
:root[data-theme="dark"]{
  --parchment:#241A12;
  --parchment-dark:#1C130D;
  --ink:#EDE0C8;
  --ink-light:#B8A588;
  --gilt:#C9A244;
  --gilt-bright:#E8C468;
  --seal:#C6605F;
  --seal-hi:#D97A78;
  --pos:#5FAE7C;
  --card-tint:rgba(0,0,0,0.22);
  --card-tint-dim:rgba(0,0,0,0.12);
  --panel-tint:rgba(0,0,0,0.18);
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
  font-variant-numeric:tabular-nums;
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
.icon{
  width:14px; height:14px; display:inline-block; vertical-align:middle;
  fill:none; stroke:currentColor; stroke-width:1.6; stroke-linecap:round; stroke-linejoin:round;
}
.icon circle, .icon rect{ fill:none; stroke:currentColor; stroke-width:1.6; }

.month-nav .nav-btn{
  background:none; border:1px solid var(--gilt); color:var(--ink);
  display:inline-flex; align-items:center; justify-content:center;
  width:28px; height:28px; cursor:pointer; border-radius:50%;
}
.month-nav .nav-btn:hover:not(:disabled){ background:rgba(184,137,43,0.15); }
.month-nav .nav-btn:disabled{ opacity:0.4; cursor:default; }
.month-nav .nav-btn .icon{ width:12px; height:12px; }

.icon-link{ display:inline-flex; align-items:center; gap:5px; }
.icon-link .icon{ color:var(--ink-light); }
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
.masthead .greeting{ font-size:11.5px; font-style:italic; color:var(--gilt); margin-top:10px; opacity:0.9; }
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
.page td.cell-name input{ display:block; text-overflow:ellipsis; overflow:hidden; white-space:nowrap; }
.cat-dot{ display:none; width:8px; height:8px; border-radius:50%; flex-shrink:0; }
.page select{
  font-family:Georgia,serif; font-size:12.5px; color:var(--ink);
  background:transparent; border:none; border-bottom:1px solid transparent; padding:3px 0;
}
.page select:focus{ outline:none; border-bottom:1px solid var(--gilt); }
.page select option{ color:#1a1208; background:#fff; }

.amt-col{ width:90px; }
.cur-col{ width:64px; }
.freq-col{ width:120px; }
.end-col{ width:66px; position:relative; }
.cat-col{ width:110px; }
.chk-col{ width:36px; text-align:center; }
.del-col{ width:26px; text-align:center; }

.month-picker-btn{
  background:none; border:none; color:var(--ink); font-family:Georgia,serif; font-size:12.5px;
  cursor:pointer; text-decoration:underline; text-decoration-style:dotted; text-decoration-color:var(--ink-light);
  padding:3px 2px; text-align:left; white-space:nowrap;
}
.month-picker-input{
  position:absolute; width:1px; height:1px; opacity:0; pointer-events:none;
  overflow:hidden; border:none; padding:0;
}

.del-btn{ background:none; border:none; color:var(--seal); cursor:pointer; display:inline-flex; padding:4px; }
.del-btn:hover{ opacity:0.7; }
.del-btn .icon{ width:13px; height:13px; }

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
  display:inline-flex;
  align-items:center;
  gap:6px;
}
.add-row:hover:not(:disabled){ background:rgba(184,137,43,0.12); }
.add-row .icon{ color:var(--gilt); }

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

.year-toggle-row{ display:flex; align-items:center; justify-content:center; gap:16px; margin-bottom:6px; position:relative; }
.theme-toggle{
  position:absolute; right:0; top:50%; transform:translateY(-50%);
  background:none; border:1px solid var(--gilt); color:var(--gilt);
  width:26px; height:26px; border-radius:50%; cursor:pointer;
  display:inline-flex; align-items:center; justify-content:center;
}
.theme-toggle:hover{ background:rgba(184,137,43,0.15); }
.theme-toggle .icon{ width:13px; height:13px; }
.lang-link{
  position:absolute; right:34px; top:50%; transform:translateY(-50%);
  font-size:11px; color:var(--ink-light); text-decoration:underline;
  font-family:Georgia,serif; font-variant:small-caps;
}

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
  margin-bottom:22px; background:var(--panel-tint);
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
.mic-btn{ padding:6px 10px; font-size:14px; }
.receipt-input{ display:none; }
.mic-btn.listening{ background:rgba(122,31,31,0.15); border-color:var(--seal); animation:micPulse 1.2s ease-in-out infinite; }
@keyframes micPulse{
  0%, 100%{ box-shadow:0 0 0 0 rgba(122,31,31,0.35); }
  50%{ box-shadow:0 0 0 6px rgba(122,31,31,0); }
}

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
    background:var(--card-tint);
  }
  .page tbody tr.inactive{ background:var(--card-tint-dim); }

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

  .page td.cell-name{ display:flex; align-items:center; gap:6px; font-size:15px; border-bottom:1px dotted rgba(59,42,24,0.3); }
  .page td.cell-name input{ font-size:15px; font-weight:600; text-overflow:ellipsis; }
  .cat-dot{ display:inline-block; }

  .page td.amt-col{ display:inline-flex; width:58%; border-bottom:none; }
  .page td.cur-col{ display:inline-flex; width:40%; }
  .page td.cur-col[data-label]::before{ display:none; }
  .page td.cur-col select{ text-align:left; }

  .page td.del-col{
    position:absolute; top:10px; right:8px;
    width:auto; padding:0; border:none; text-align:right;
  }
  .page td.del-col .del-btn .icon{ width:16px; height:16px; }

  .totals{ flex-direction:column; align-items:stretch; gap:14px; }
  .totals-text{ max-width:none; }
  .seal-wrap{ margin:0 auto; }
}
</style>
