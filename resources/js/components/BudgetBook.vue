<template>
  <div class="tome">
    <div class="cover">
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
            <input ref="chatInputField" type="text" v-model="chatInput" :placeholder="t('chatPlaceholder')" :disabled="chatSending || isListening">
            <button
              v-if="voiceSupported" type="button" class="mic-btn" :class="{ listening: isListening }"
              :disabled="chatSending" @click="toggleVoiceInput" :aria-label="t('voiceLabel')"
            >
              <svg v-if="!isListening" viewBox="0 0 16 16" class="icon"><path d="M8 2.5 a2 2 0 0 1 2 2 v3.5 a2 2 0 0 1 -4 0 v-3.5 a2 2 0 0 1 2 -2 Z" /><path d="M4.5 8 a3.5 3.5 0 0 0 7 0 M8 11.5 V13.5 M6 13.5 H10" /></svg>
              <svg v-else viewBox="0 0 16 16" class="icon"><rect x="4" y="4" width="8" height="8" rx="1" /></svg>
            </button>
            <button type="button" class="mic-btn" :disabled="chatSending || scanningReceipt || isListening" @click="triggerReceiptPicker" :aria-label="t('receiptLabel')">
              <svg viewBox="0 0 16 16" class="icon"><path d="M2 6 a1 1 0 0 1 1 -1 h1.5 l1 -1.5 h5 l1 1.5 H13 a1 1 0 0 1 1 1 v6 a1 1 0 0 1 -1 1 H3 a1 1 0 0 1 -1 -1 Z" /><circle cx="8" cy="9" r="2.3" /></svg>
            </button>
            <input ref="receiptInputEl" type="file" accept="image/*" capture="environment" class="receipt-input" @change="onReceiptSelected">
            <button type="submit" :disabled="chatSending || isListening || !chatInput.trim()">{{ t('send') }}</button>
          </form>
        </div>

        <div class="masthead">
          <h1>{{ t('title') }}</h1>
          <div class="sub">{{ t('subtitle') }}</div>
          <div v-if="!loading" class="greeting">{{ dataGreeting }}</div>
        </div>

        <div v-if="loading" class="foot-note">{{ t('loadingBook') }}</div>

        <div v-else-if="showYearView" class="year-view">
          <div class="section-title">{{ t('yearAnalysisHeading') }} {{ currentYearLabel }}</div>

          <div v-if="yearLoading" class="foot-note">{{ t('loadingAnalysis') }}</div>

          <template v-else-if="yearMonths.length">
            <div class="year-legend">
              <span class="legend-item"><span class="swatch line-swatch" :style="{ background: YEAR_COLORS.income }"></span>{{ t('income') }}</span>
              <span class="legend-item" v-for="cat in yearChart.categoriesUsed" :key="cat">
                <span class="swatch" :style="{ background: categoryColor(cat, allExpenseCategories) }"></span>{{ categoryLabel(cat) }}
              </span>
            </div>

            <div class="year-chart-wrap">
              <svg class="year-chart" :viewBox="`0 0 ${yearChart.width} ${yearChart.height}`" preserveAspectRatio="xMidYMid meet">
                <defs>
                  <linearGradient id="year-grad-income" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" :stop-color="YEAR_COLORS.income" stop-opacity="0.35" />
                    <stop offset="100%" :stop-color="YEAR_COLORS.income" stop-opacity="0" />
                  </linearGradient>
                </defs>
                <line
                  v-for="(gl, idx) in yearChart.gridlines" :key="'gl'+idx"
                  :x1="yearChart.padding" :x2="yearChart.width - yearChart.padding"
                  :y1="gl" :y2="gl" class="year-gridline"
                />
                <line
                  v-if="hoveredIndex !== null"
                  :x1="yearChart.x(hoveredIndex)" :x2="yearChart.x(hoveredIndex)"
                  :y1="yearChart.padding" :y2="yearChart.height - yearChart.padding"
                  class="year-hover-line"
                />
                <rect
                  v-for="seg in yearChart.segments" :key="seg.key"
                  :x="seg.x" :y="seg.y" :width="seg.width" :height="seg.height"
                  :fill="seg.color" class="year-bar-seg"
                />
                <path :d="yearChart.incomeArea" fill="url(#year-grad-income)" stroke="none" />
                <path :d="yearChart.incomePath" fill="none" :stroke="YEAR_COLORS.income" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />
                <circle
                  v-for="(m, i) in yearChart.months" v-show="m.hasData" :key="'inc'+i"
                  :cx="yearChart.x(i)" :cy="yearChart.y(m.income)" r="4"
                  :fill="YEAR_COLORS.income" stroke="var(--parchment)" stroke-width="2"
                />
                <text
                  v-for="(m, i) in yearChart.months" :key="'lbl'+i"
                  :x="yearChart.x(i)" :y="yearChart.height - 8" class="year-axis-label"
                  :class="{ 'is-future': !m.hasData }" text-anchor="middle"
                >{{ monthAbbrev(m.period) }}</text>
                <rect
                  v-for="(m, i) in yearChart.months" v-show="m.hasData" :key="'hit'+i"
                  :x="yearChart.x(i) - yearChart.colWidth / 2" y="0" :width="yearChart.colWidth" :height="yearChart.height"
                  fill="transparent" class="year-hit"
                  @pointerenter="hoveredIndex = i" @pointerleave="hoveredIndex = null"
                  @click="hoveredIndex = hoveredIndex === i ? null : i"
                />
              </svg>
              <div v-if="hoveredIndex !== null" class="year-tooltip" :style="yearTooltipStyle">
                <strong>{{ periodLabel(yearChart.months[hoveredIndex].period) }}</strong>
                <div class="year-tooltip-row"><span class="swatch line-swatch" :style="{ background: YEAR_COLORS.income }"></span>{{ t('income') }}: {{ fmt(yearChart.months[hoveredIndex].income) }} RSD</div>
                <div class="year-tooltip-row" v-for="slice in yearChart.tooltipCategories(hoveredIndex)" :key="slice.category">
                  <span class="swatch" :style="{ background: slice.color }"></span>{{ categoryLabel(slice.category) }}: {{ fmt(slice.amount) }} RSD
                </div>
                <div class="year-tooltip-row net" :class="yearChart.months[hoveredIndex].net >= 0 ? 'pos' : 'neg'">{{ t('net') }}: {{ signed(yearChart.months[hoveredIndex].net) }} RSD</div>
              </div>
            </div>

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
                <tr v-for="item in visibleIncome" :key="keyFor(item)" :class="{ inactive: !isIncomeActive(item), flash: newlyAddedKey === keyFor(item) }">
                  <td class="cell-name">
                    <input type="text" v-model="item.name" :title="item.name" @change="saveIncome">
                    <span v-if="item.createdAt" class="created-badge">{{ formatCreatedAt(item.createdAt) }}</span>
                    <span v-if="divertedForIncome(item.id) > 0" class="diverted-badge" :title="t('divertedToSavingsTitle')">
                      −{{ fmt(fromRSD(divertedForIncome(item.id), item.currency)) }} {{ item.currency }} → {{ t('savingsAndAssets') }}
                    </span>
                  </td>
                  <td class="amt-col" :data-label="t('amount')"><input type="number" v-model.number="item.amount" step="1" @change="saveIncome"></td>
                  <td class="cur-col" :data-label="t('currency')">
                    <select v-model="item.currency" @change="saveIncome">
                      <option value="RSD">RSD</option>
                      <option value="EUR">EUR</option>
                      <option value="USD">USD</option>
                    </select>
                  </td>
                  <td class="freq-col" :data-label="t('frequency')">
                    <select :key="freqMode(item)" :value="freqMode(item)" @change="setFreqMode(item, $event.target.value, onIncomeFreqChange)">
                      <option value="monthly">{{ t('monthly') }}</option>
                      <option value="custom">{{ t('customInterval') }}</option>
                      <option value="onetime">{{ t('oneTime') }}</option>
                    </select>
                    <div v-if="item.freq > 1" class="freq-custom">
                      <span>{{ t('every') }}</span>
                      <input type="number" min="2" max="24" v-model.number="item.freq" @change="saveIncome" class="freq-interval-input">
                      <span>{{ t('monthsUnit') }}</span>
                      <span>{{ t('fromMonth') }}</span>
                      <input type="month" v-model="item.dueAnchor" @change="saveIncome" class="freq-anchor-input">
                    </div>
                  </td>
                  <td class="chk-col" :data-label="t('active')"><input type="checkbox" v-model="item.active" @change="saveIncome"></td>
                  <td class="del-col"><button class="del-btn" @click="removeRow(income, item, saveIncome)" :aria-label="t('deleteRow')"><svg viewBox="0 0 16 16" class="icon"><path d="M4 4 L12 12 M12 4 L4 12" /></svg></button></td>
                </tr>
              </TransitionGroup>
            </table>
            <button v-if="!quickAdd || quickAdd.kind !== 'income'" class="add-row" @click="startQuickAdd('income')"><svg viewBox="0 0 16 16" class="icon"><path d="M8 3 V13 M3 8 H13" /></svg> {{ t('addIncome') }}</button>
            <div v-if="quickAdd && quickAdd.kind === 'income'" class="quick-add-form">
              <input type="text" v-model="quickAdd.name" :placeholder="t('newIncomeName')" autofocus class="quick-add-name" @keyup.enter="confirmQuickAdd">
              <input type="number" v-model.number="quickAdd.amount" :placeholder="t('amount')" class="quick-add-amount" @keyup.enter="confirmQuickAdd">
              <select v-model="quickAdd.currency">
                <option value="RSD">RSD</option>
                <option value="EUR">EUR</option>
                <option value="USD">USD</option>
              </select>
              <button type="button" class="del-btn" @click="confirmQuickAdd" :aria-label="t('confirmAdd')">✓</button>
              <button type="button" class="del-btn" @click="cancelQuickAdd" :aria-label="t('cancelAdd')">✕</button>
            </div>
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
                  <th class="source-col">{{ t('pullFromSavingsColumn') }}</th>
                  <th class="chk-col">{{ t('active') }}</th>
                  <th class="del-col"></th>
                </tr>
              </thead>
              <TransitionGroup tag="tbody" name="row">
                <tr v-for="item in visibleExpenses" :key="keyFor(item)" :class="{ inactive: !isExpenseActive(item), flash: newlyAddedKey === keyFor(item) }">
                  <td class="cell-name">
                    <span class="cat-dot" :style="{ background: categoryColor(item.category, allExpenseCategories) }"></span>
                    <input type="text" v-model="item.name" :title="item.name" @change="saveExpenses">
                    <span v-if="item.createdAt" class="created-badge">{{ formatCreatedAt(item.createdAt) }}</span>
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
                    <select :key="freqMode(item)" :value="freqMode(item)" @change="setFreqMode(item, $event.target.value, onExpenseFreqChange)">
                      <option value="monthly">{{ t('monthly') }}</option>
                      <option value="custom">{{ t('customInterval') }}</option>
                      <option value="onetime">{{ t('oneTime') }}</option>
                    </select>
                    <div v-if="item.freq > 1" class="freq-custom">
                      <span>{{ t('every') }}</span>
                      <input type="number" min="2" max="24" v-model.number="item.freq" @change="saveExpenses" class="freq-interval-input">
                      <span>{{ t('monthsUnit') }}</span>
                      <span>{{ t('fromMonth') }}</span>
                      <input type="month" v-model="item.dueAnchor" @change="saveExpenses" class="freq-anchor-input">
                    </div>
                  </td>
                  <td class="end-col" :data-label="t('untilMonth')">
                    <input
                      v-if="editingEndPeriod === keyFor(item)" type="month" v-model="item.endPeriod" autofocus
                      class="month-picker-native" @change="saveExpenses" @blur="editingEndPeriod = null"
                    >
                    <button v-else type="button" class="month-picker-btn" @click="editingEndPeriod = keyFor(item)">
                      {{ item.endPeriod ? formatEndPeriod(item.endPeriod) : '—' }}
                    </button>
                  </td>
                  <td class="cat-col" :data-label="t('category')">
                    <div v-if="categorySuggestion && categorySuggestion.itemKey === keyFor(item)" class="cat-suggest">
                      <span>{{ t('didYouMean') }} {{ categoryLabel(categorySuggestion.match) }}?</span>
                      <button class="del-btn" type="button" @click="acceptCategorySuggestion" :title="t('useExistingCategoryTitle')">✓</button>
                      <button class="del-btn" type="button" @click="rejectCategorySuggestion" :title="t('createNewCategoryTitle')">✕</button>
                    </div>
                    <input
                      v-else-if="addingCategoryFor === keyFor(item)" type="text" v-model="newCategoryName" autofocus
                      class="month-picker-native" :placeholder="t('newCategoryPlaceholder')"
                      @keyup.enter="$event.target.blur()" @change="confirmNewCategory(item, 'expense')" @blur="addingCategoryFor = null"
                    >
                    <select v-else :key="item.category" :value="item.category" @change="onCategorySelect($event, item, 'expense')">
                      <option v-for="cat in expenseCategoryOptions" :key="cat" :value="cat">{{ categoryLabel(cat) }}</option>
                      <option value="__add__">{{ t('addCategoryOption') }}</option>
                    </select>
                  </td>
                  <td class="chk-col" :data-label="t('active')"><input type="checkbox" v-model="item.active" @change="saveExpenses"></td>
                  <td class="source-col" :data-label="t('pullFromSavingsColumn')">
                    <select v-if="savings.length" :value="item.paidFromSavings ? item.paidFromSavings.savingsId : ''" @change="onExpenseSourceChange(item, $event)">
                      <option value="">{{ t('savingsSourceNone') }}</option>
                      <option v-for="s in savings" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                  </td>
                  <td class="del-col"><button class="del-btn" @click="removeExpenseRow(item)" :aria-label="t('deleteRow')"><svg viewBox="0 0 16 16" class="icon"><path d="M4 4 L12 12 M12 4 L4 12" /></svg></button></td>
                </tr>
              </TransitionGroup>
            </table>
            <button v-if="!quickAdd || quickAdd.kind !== 'expense'" class="add-row" @click="startQuickAdd('expense')"><svg viewBox="0 0 16 16" class="icon"><path d="M8 3 V13 M3 8 H13" /></svg> {{ t('addExpense') }}</button>
            <div v-if="quickAdd && quickAdd.kind === 'expense'" class="quick-add-form">
              <input type="text" v-model="quickAdd.name" :placeholder="t('newExpenseName')" autofocus class="quick-add-name" @keyup.enter="confirmQuickAdd">
              <input type="number" v-model.number="quickAdd.amount" :placeholder="t('amount')" class="quick-add-amount" @keyup.enter="confirmQuickAdd">
              <select v-model="quickAdd.currency">
                <option value="RSD">RSD</option>
                <option value="EUR">EUR</option>
                <option value="USD">USD</option>
              </select>
              <button type="button" class="del-btn" @click="confirmQuickAdd" :aria-label="t('confirmAdd')">✓</button>
              <button type="button" class="del-btn" @click="cancelQuickAdd" :aria-label="t('cancelAdd')">✕</button>
            </div>
            <button v-if="oneTimeExpensesCount > 0" class="reset-link load-more-btn" @click="showOneTimeExpenses = !showOneTimeExpenses">
              {{ showOneTimeExpenses ? t('hideOneTimeExpenses') : t('showOneTimeExpenses') }} ({{ oneTimeExpensesCount }})
            </button>

            <div v-if="customExpenseCategories.length" class="category-manage">
              <button type="button" class="reset-link load-more-btn" @click="showManageExpenseCategories = !showManageExpenseCategories">
                {{ t('manageCategories') }} ({{ customExpenseCategories.length }})
              </button>
              <div v-if="showManageExpenseCategories" class="category-manage-list">
                <div v-for="cat in customExpenseCategories" :key="cat" class="category-manage-row">
                  <input
                    v-if="renamingCategory && renamingCategory.kind === 'expense' && renamingCategory.from === cat"
                    type="text" v-model="renamingCategory.to" autofocus class="month-picker-native"
                    @keyup.enter="$event.target.blur()" @change="commitCategoryRename" @blur="cancelCategoryRename"
                  >
                  <span v-else>{{ categoryLabel(cat) }}</span>
                  <div class="category-manage-actions">
                    <button class="del-btn" type="button" @click="startCategoryRename('expense', cat)" :title="t('renameCategoryTitle')">✎</button>
                    <button class="del-btn" type="button" @click="deleteCategory('expense', cat)" :title="t('deleteCategoryTitle')">🗑</button>
                  </div>
                </div>
              </div>
            </div>

            <div class="savings-line" v-if="recurringExpTotal > 0">
              {{ t('recurringExpensesLabel') }} <strong>{{ fmt(recurringExpTotal) }} RSD</strong>
            </div>

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
                  <th class="source-col">{{ t('pullFromIncome') }}</th>
                  <th class="del-col"></th>
                </tr>
              </thead>
              <TransitionGroup tag="tbody" name="row">
                <tr v-for="item in sortedSavings" :key="keyFor(item)" :class="{ flash: newlyAddedKey === keyFor(item) }">
                  <td class="cell-name">
                    <span class="cat-dot" :style="{ background: categoryColor(item.category, allSavingsCategories) }"></span>
                    <input type="text" v-model="item.name" :title="item.name" @change="saveSavings">
                    <span v-if="item.createdAt" class="created-badge">{{ formatCreatedAt(item.createdAt) }}</span>
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
                    <div v-if="categorySuggestion && categorySuggestion.itemKey === keyFor(item)" class="cat-suggest">
                      <span>{{ t('didYouMean') }} {{ categoryLabel(categorySuggestion.match) }}?</span>
                      <button class="del-btn" type="button" @click="acceptCategorySuggestion" :title="t('useExistingCategoryTitle')">✓</button>
                      <button class="del-btn" type="button" @click="rejectCategorySuggestion" :title="t('createNewCategoryTitle')">✕</button>
                    </div>
                    <input
                      v-else-if="addingCategoryFor === keyFor(item)" type="text" v-model="newCategoryName" autofocus
                      class="month-picker-native" :placeholder="t('newCategoryPlaceholder')"
                      @keyup.enter="$event.target.blur()" @change="confirmNewCategory(item, 'savings')" @blur="addingCategoryFor = null"
                    >
                    <select v-else :key="item.category" :value="item.category" @change="onCategorySelect($event, item, 'savings')">
                      <option v-for="cat in savingsCategoryOptions" :key="cat" :value="cat">{{ categoryLabel(cat) }}</option>
                      <option value="__add__">{{ t('addCategoryOption') }}</option>
                    </select>
                  </td>
                  <td class="source-col" :data-label="t('pullFromIncome')">
                    <select :value="item.sourceIncomeId || ''" @change="onSavingsSourceChange(item, $event)">
                      <option value="">{{ t('savingsSourceNone') }}</option>
                      <option v-for="it in income" :key="it.id" :value="it.id">{{ it.name }}</option>
                    </select>
                  </td>
                  <td class="del-col"><button class="del-btn" @click="removeRow(savings, item, saveSavings)" :aria-label="t('deleteRow')"><svg viewBox="0 0 16 16" class="icon"><path d="M4 4 L12 12 M12 4 L4 12" /></svg></button></td>
                </tr>
              </TransitionGroup>
            </table>
            <button v-if="!quickAdd || quickAdd.kind !== 'savings'" class="add-row" @click="startQuickAdd('savings')"><svg viewBox="0 0 16 16" class="icon"><path d="M8 3 V13 M3 8 H13" /></svg> {{ t('addSaving') }}</button>
            <div v-if="quickAdd && quickAdd.kind === 'savings'" class="quick-add-form">
              <input type="text" v-model="quickAdd.name" :placeholder="t('newSavingName')" autofocus class="quick-add-name" @keyup.enter="confirmQuickAdd">
              <input type="number" v-model.number="quickAdd.amount" :placeholder="t('amount')" class="quick-add-amount" @keyup.enter="confirmQuickAdd">
              <select v-model="quickAdd.currency">
                <option value="RSD">RSD</option>
                <option value="EUR">EUR</option>
                <option value="USD">USD</option>
              </select>
              <button type="button" class="del-btn" @click="confirmQuickAdd" :aria-label="t('confirmAdd')">✓</button>
              <button type="button" class="del-btn" @click="cancelQuickAdd" :aria-label="t('cancelAdd')">✕</button>
            </div>

            <div v-if="customSavingsCategories.length" class="category-manage">
              <button type="button" class="reset-link load-more-btn" @click="showManageSavingsCategories = !showManageSavingsCategories">
                {{ t('manageCategories') }} ({{ customSavingsCategories.length }})
              </button>
              <div v-if="showManageSavingsCategories" class="category-manage-list">
                <div v-for="cat in customSavingsCategories" :key="cat" class="category-manage-row">
                  <input
                    v-if="renamingCategory && renamingCategory.kind === 'savings' && renamingCategory.from === cat"
                    type="text" v-model="renamingCategory.to" autofocus class="month-picker-native"
                    @keyup.enter="$event.target.blur()" @change="commitCategoryRename" @blur="cancelCategoryRename"
                  >
                  <span v-else>{{ categoryLabel(cat) }}</span>
                  <div class="category-manage-actions">
                    <button class="del-btn" type="button" @click="startCategoryRename('savings', cat)" :title="t('renameCategoryTitle')">✎</button>
                    <button class="del-btn" type="button" @click="deleteCategory('savings', cat)" :title="t('deleteCategoryTitle')">🗑</button>
                  </div>
                </div>
              </div>
            </div>

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
import { reactive, ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import axios from 'axios';

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
    title: 'Bilanso',
    subtitle: 'primanja, troškovi i štednja, na jednom mestu',
    loadingBook: 'Učitavanje…',
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
    customInterval: 'na X meseci...',
    every: 'svakih',
    monthsUnit: 'meseci',
    fromMonth: 'od',
    oneTime: 'jednokratno',
    deleteRow: 'Obriši red',
    confirmAdd: 'Dodaj',
    cancelAdd: 'Otkaži',
    addCategoryOption: '+ Dodaj kategoriju',
    newCategoryPlaceholder: 'Naziv kategorije',
    didYouMean: 'Misliš na',
    useExistingCategoryTitle: 'Da, koristi tu kategoriju',
    createNewCategoryTitle: 'Ne, napravi novu kategoriju',
    manageCategories: 'Uredi dodate kategorije',
    renameCategoryTitle: 'Preimenuj kategoriju',
    deleteCategoryTitle: 'Obriši kategoriju',
    pullFromIncome: 'Povuci iz prihoda',
    pullFromSavingsColumn: 'Izvuci iz',
    savingsSourceNone: 'Ništa',
    divertedToSavingsTitle: 'Povučeno iz ovog primanja u štednju ovog meseca',
    addIncome: 'Dodaj primanje',
    addExpense: 'Dodaj trošak',
    addSaving: 'Dodaj stavku štednje',
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
    recurringExpensesLabel: 'Mesečni troškovi koji se ponavljaju:',
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
    voiceNotAllowed: 'Nisam dobio dozvolu za mikrofon — proveri podešavanja browsera/telefona.',
    voiceSent: '🎤 (glasovna poruka)',
    voiceUnclear: 'Nisam razumeo šta si rekao/la — probaj ponovo, sporije i jasnije.',
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
    title: 'Bilanso',
    subtitle: 'income, expenses and savings, all in one place',
    loadingBook: 'Loading…',
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
    customInterval: 'every X months...',
    every: 'every',
    monthsUnit: 'months',
    fromMonth: 'from',
    oneTime: 'one-time',
    deleteRow: 'Delete row',
    confirmAdd: 'Add',
    cancelAdd: 'Cancel',
    addCategoryOption: '+ Add category',
    newCategoryPlaceholder: 'Category name',
    didYouMean: 'Did you mean',
    useExistingCategoryTitle: 'Yes, use that category',
    createNewCategoryTitle: 'No, create a new category',
    manageCategories: 'Manage added categories',
    renameCategoryTitle: 'Rename category',
    deleteCategoryTitle: 'Delete category',
    pullFromIncome: 'Pull from income',
    pullFromSavingsColumn: 'Pull from',
    savingsSourceNone: 'Nothing',
    divertedToSavingsTitle: 'Diverted from this income into savings this month',
    addIncome: 'Add income',
    addExpense: 'Add expense',
    addSaving: 'Add savings item',
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
    recurringExpensesLabel: 'Recurring monthly expenses:',
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
    voiceNotAllowed: 'Microphone permission denied — check your browser/phone settings.',
    voiceSent: '🎤 (voice message)',
    voiceUnclear: "Didn't catch that — try again, slower and clearer.",
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

function aiErrorMessage(e, fallback) {
  if (e?.response?.status === 429 && e.response.data?.error) return e.response.data.error;
  return fallback;
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
const CATEGORY_COLORS = ['#2DD4BF', '#818CF8', '#FBBF24', '#FB7185', '#38BDF8', '#A3E635', '#E879F9', '#FB923C'];

function categoryColor(category, list) {
  const idx = list.indexOf(category);
  return CATEGORY_COLORS[idx] ?? CATEGORY_COLORS[CATEGORY_COLORS.length - 1];
}

// Custom categories the user has added, and how often each category has ever
// been picked — kept separate so adding/reordering by usage never reshuffles
// an existing category's chart color (color lookup always uses insertion order).
const customExpenseCategories = reactive([]);
const customSavingsCategories = reactive([]);
const categoryUsageExpense = reactive({});
const categoryUsageSavings = reactive({});

const allExpenseCategories = computed(() => [...EXPENSE_CATEGORIES, ...customExpenseCategories]);
const allSavingsCategories = computed(() => [...SAVINGS_CATEGORIES, ...customSavingsCategories]);

function sortByUsage(list, usageMap) {
  return [...list].sort((a, b) => (usageMap[b] || 0) - (usageMap[a] || 0));
}
const expenseCategoryOptions = computed(() => sortByUsage(allExpenseCategories.value, categoryUsageExpense));
const savingsCategoryOptions = computed(() => sortByUsage(allSavingsCategories.value, categoryUsageSavings));

function persistCustomCategories(kind) {
  if (kind === 'expense') persist('custom-categories-expense', customExpenseCategories);
  else persist('custom-categories-savings', customSavingsCategories);
}
function bumpCategoryUsage(kind, category) {
  const usage = kind === 'expense' ? categoryUsageExpense : categoryUsageSavings;
  usage[category] = (usage[category] || 0) + 1;
  persist(kind === 'expense' ? 'category-usage-expense' : 'category-usage-savings', usage);
}

const addingCategoryFor = ref(null);
const newCategoryName = ref('');

function onCategorySelect(event, item, kind) {
  const value = event.target.value;
  if (value === '__add__') {
    newCategoryName.value = '';
    addingCategoryFor.value = keyFor(item);
    return;
  }
  item.category = value;
  bumpCategoryUsage(kind, value);
  if (kind === 'expense') saveExpenses(); else saveSavings();
}
function normalizeCategory(s) {
  return (s || '').toString().trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}
// Same word typed without diacritics ("racuni" vs "Računi") is treated as an
// exact match and applied silently; a match only via the English label
// ("bills" vs "Računi") is close enough to ask about, not close enough to assume.
function findExistingCategory(name, list) {
  const norm = normalizeCategory(name);
  const exact = list.find(cat => normalizeCategory(cat) === norm);
  if (exact) return { category: exact, exact: true };
  const alias = list.find(cat => normalizeCategory(CATEGORY_LABELS_EN[cat]) === norm);
  if (alias) return { category: alias, exact: false };
  return null;
}

function applyCategory(item, kind, category) {
  item.category = category;
  bumpCategoryUsage(kind, category);
  if (kind === 'expense') saveExpenses(); else saveSavings();
}

const categorySuggestion = ref(null);

function confirmNewCategory(item, kind) {
  const name = newCategoryName.value.trim();
  addingCategoryFor.value = null;
  if (!name) return;

  const existing = kind === 'expense' ? allExpenseCategories.value : allSavingsCategories.value;
  const found = findExistingCategory(name, existing);

  if (found?.exact) {
    applyCategory(item, kind, found.category);
    return;
  }
  if (found) {
    categorySuggestion.value = { itemKey: keyFor(item), item, kind, typed: name, match: found.category };
    return;
  }

  const list = kind === 'expense' ? customExpenseCategories : customSavingsCategories;
  list.push(name);
  persistCustomCategories(kind);
  applyCategory(item, kind, name);
}
function acceptCategorySuggestion() {
  const s = categorySuggestion.value;
  if (!s) return;
  applyCategory(s.item, s.kind, s.match);
  categorySuggestion.value = null;
}
function rejectCategorySuggestion() {
  const s = categorySuggestion.value;
  if (!s) return;
  const list = s.kind === 'expense' ? customExpenseCategories : customSavingsCategories;
  list.push(s.typed);
  persistCustomCategories(s.kind);
  applyCategory(s.item, s.kind, s.typed);
  categorySuggestion.value = null;
}

const showManageExpenseCategories = ref(false);
const showManageSavingsCategories = ref(false);
const renamingCategory = ref(null);

function startCategoryRename(kind, from) {
  renamingCategory.value = { kind, from, to: from };
}
function cancelCategoryRename() {
  renamingCategory.value = null;
}
async function applyCategoryChange(kind, action, from, to) {
  await axios.post('/api/budget/categories', { kind, action, from, to });
  await loadState(currentPeriod.value);
}
async function commitCategoryRename() {
  const r = renamingCategory.value;
  if (!r) return;
  const to = r.to.trim();
  renamingCategory.value = null;
  if (!to || to === r.from) return;
  await applyCategoryChange(r.kind, 'rename', r.from, to);
}
async function deleteCategory(kind, from) {
  await applyCategoryChange(kind, 'delete', from);
}

const expenses = reactive(clone(defaultExpenses));
const income = reactive(clone(defaultIncome));
const savings = reactive(clone(defaultSavings));
const rates = reactive(clone(defaultRates));
const conv = reactive({ amount: 1000, from: 'RSD', to: 'EUR' });

function clone(x) { return JSON.parse(JSON.stringify(x)); }
function replaceArray(target, source) { target.splice(0, target.length, ...source); }

function generateId() {
  return Math.random().toString(36).slice(2, 10) + Date.now().toString(36);
}

const rowIdMap = new WeakMap();
let rowIdCounter = 0;
function keyFor(item) {
  if (item.id) return item.id;
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

  let needsExpensesIdBackfill = false;
  let needsIncomeIdBackfill = false;
  let needsSavingsIdBackfill = false;

  try {
    const { data } = await axios.get('/api/budget', { params: { period } });
    const d = data.data || {};
    if (d['expense-items']) {
      replaceArray(expenses, JSON.parse(d['expense-items']).map(it => {
        if (!it.id) needsExpensesIdBackfill = true;
        return { id: it.id || generateId(), category: 'Ostalo', ...it };
      }));
    }
    if (d['income-items']) {
      replaceArray(income, JSON.parse(d['income-items']).map(it => {
        if (!it.id) needsIncomeIdBackfill = true;
        return { id: it.id || generateId(), ...it };
      }));
    }
    if (d['expense-rates']) Object.assign(rates, JSON.parse(d['expense-rates']));
    if (d['savings-items']) {
      replaceArray(savings, JSON.parse(d['savings-items']).map(it => {
        if (!it.id) needsSavingsIdBackfill = true;
        return { id: it.id || generateId(), category: 'Ostalo', ...it };
      }));
    }
    if (d['custom-categories-expense']) replaceArray(customExpenseCategories, JSON.parse(d['custom-categories-expense']));
    if (d['custom-categories-savings']) replaceArray(customSavingsCategories, JSON.parse(d['custom-categories-savings']));
    if (d['category-usage-expense']) Object.assign(categoryUsageExpense, JSON.parse(d['category-usage-expense']));
    if (d['category-usage-savings']) Object.assign(categoryUsageSavings, JSON.parse(d['category-usage-savings']));

    if (data.is_new_period && data.previous_net !== null && data.template_period && !dismissedPeriods.has(period)) {
      bannerPreviousNet.value = data.previous_net;
      bannerAmount.value = Math.round(data.previous_net);
      bannerPreviousLabel.value = periodLabel(data.template_period);
      bannerVisible.value = data.previous_net > 0;
    }

    // Older rows saved before items had a stable id — persist the
    // freshly-assigned ones now so future edits mirror correctly.
    if (needsExpensesIdBackfill) saveExpenses();
    if (needsIncomeIdBackfill) saveIncome();
    if (needsSavingsIdBackfill) saveSavings();
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
function saveSavings() {
  resyncSavingsDiverted();
  persist('savings-items', savings);
}
function saveRates() { persist('expense-rates', rates, currentPeriod.value); }

// New items land at the top of the (possibly long) list, so a brief flash
// marks where one just appeared instead of relying on scrolling to it.
const newlyAddedKey = ref(null);
function flashNewItem(item) {
  const key = keyFor(item);
  newlyAddedKey.value = key;
  setTimeout(() => {
    if (newlyAddedKey.value === key) newlyAddedKey.value = null;
  }, 900);
}

// Adding used to insert a blank row straight into the (possibly long,
// possibly scrolled-away) list and then try to focus/scroll to it — on
// mobile, focusing a row that Vue only just rendered often failed to bring
// up the on-screen keyboard at all. A small form right under the "Add"
// button instead means there's nothing to scroll to and nothing unusual
// about the focus, since the user taps a field that's already on screen.
const quickAdd = ref(null);

function startQuickAdd(kind) {
  quickAdd.value = { kind, name: '', amount: 0, currency: 'RSD' };
}
function cancelQuickAdd() {
  quickAdd.value = null;
}
function confirmQuickAdd() {
  const qa = quickAdd.value;
  if (!qa) return;
  const name = qa.name.trim();
  quickAdd.value = null;
  if (!name) return;

  const base = { id: generateId(), name, amount: qa.amount || 0, currency: qa.currency, createdAt: Date.now() };

  if (qa.kind === 'expense') {
    const item = { ...base, freq: 1, active: true, endPeriod: null, category: 'Ostalo' };
    expenses.push(item);
    saveExpenses();
    flashNewItem(item);
  } else if (qa.kind === 'income') {
    const item = { ...base, freq: 1, active: true };
    income.push(item);
    saveIncome();
    flashNewItem(item);
  } else if (qa.kind === 'savings') {
    const item = { ...base, category: 'Ostalo' };
    savings.push(item);
    saveSavings();
    flashNewItem(item);
  }
}

function removeRow(arr, item, save) {
  const idx = arr.indexOf(item);
  if (idx !== -1) arr.splice(idx, 1);
  save();
}

function onSavingsSourceChange(item, event) {
  const value = event.target.value;
  item.sourceIncomeId = value || null;
  item.sourceIncomePeriod = value ? currentPeriod.value : null;
  saveSavings();
}

// How much of a given income item is currently earmarked by savings rows
// linked to it for the month being viewed — computed live from the savings
// list so it's always correct, including after amount/currency edits or
// deleting the linked row (nothing to separately "undo").
function divertedForIncome(incomeId) {
  return savings.reduce((sum, s) => (
    s.sourceIncomeId === incomeId && s.sourceIncomePeriod === currentPeriod.value
      ? sum + toRSD(s.amount, s.currency)
      : sum
  ), 0);
}

// Mirrors the live diverted total onto the income item's own stored field,
// so the backend (yearly chart, monthly reminder) — which only sees this
// month's saved income JSON, not the separate global savings list — agrees
// with what the current-month view shows.
function resyncSavingsDiverted() {
  let changed = false;
  income.forEach(it => {
    const converted = fromRSD(divertedForIncome(it.id), it.currency);
    if (Math.round((it.savingsDiverted || 0) * 100) !== Math.round(converted * 100)) {
      it.savingsDiverted = converted;
      changed = true;
    }
  });
  if (changed) saveIncome();
}

function restorePaidFromSavings(item) {
  const info = item.paidFromSavings;
  if (!info) return;
  const savingsItem = savings.find(s => s.id === info.savingsId);
  if (savingsItem) {
    const restored = fromRSD(toRSD(info.amount, info.currency), savingsItem.currency);
    savingsItem.amount = Math.round((savingsItem.amount + restored) * 100) / 100;
  }
}

function onExpenseSourceChange(item, event) {
  const newSavingsId = event.target.value || null;

  restorePaidFromSavings(item);

  if (newSavingsId) {
    const savingsItem = savings.find(s => s.id === newSavingsId);
    if (savingsItem) {
      const deducted = fromRSD(toRSD(item.amount, item.currency), savingsItem.currency);
      savingsItem.amount = Math.round((savingsItem.amount - deducted) * 100) / 100;
      item.paidFromSavings = { savingsId: newSavingsId, amount: item.amount, currency: item.currency };
    }
  } else {
    item.paidFromSavings = null;
  }

  saveSavings();
  saveExpenses();
}

function removeExpenseRow(item) {
  restorePaidFromSavings(item);
  saveSavings();
  removeRow(expenses, item, saveExpenses);
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
function fromRSD(amountRsd, currency) {
  if (currency === 'USD') return rates.usd ? amountRsd / rates.usd : 0;
  if (currency === 'EUR') return rates.eur ? amountRsd / rates.eur : 0;
  return amountRsd;
}
function fmt(n) { return Math.round(n).toLocaleString('sr-RS'); }
function fmt2(n) { return n.toLocaleString('sr-RS', { maximumFractionDigits: 2 }); }
function signed(n) { return (n >= 0 ? '+' : '') + fmt(n); }

function monthsBetween(fromPeriod, toPeriod) {
  const [fy, fm] = fromPeriod.split('-').map(Number);
  const [ty, tm] = toPeriod.split('-').map(Number);
  return (ty - fy) * 12 + (tm - fm);
}
function isDueThisPeriod(item, period) {
  if (!item.freq || item.freq <= 1) return true;
  if (!item.dueAnchor) return true;
  const diff = monthsBetween(item.dueAnchor, period);
  return diff >= 0 && diff % item.freq === 0;
}
function isExpenseActive(item) {
  return item.active
    && (!item.endPeriod || currentPeriod.value <= item.endPeriod)
    && isDueThisPeriod(item, currentPeriod.value);
}
function isIncomeActive(item) {
  return item.active
    && (!item.endPeriod || currentPeriod.value <= item.endPeriod)
    && isDueThisPeriod(item, currentPeriod.value);
}

function freqMode(item) {
  if (item.freq === 0) return 'onetime';
  if (item.freq === 1) return 'monthly';
  return 'custom';
}
function setFreqMode(item, mode, onChange) {
  if (mode === 'monthly') {
    item.freq = 1;
    item.dueAnchor = null;
  } else if (mode === 'onetime') {
    item.freq = 0;
    item.dueAnchor = null;
  } else if (mode === 'custom') {
    item.freq = item.freq > 1 ? item.freq : 2;
    if (!item.dueAnchor) item.dueAnchor = currentPeriod.value;
  }
  onChange(item);
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

function byNewestFirst(a, b) {
  return (b.createdAt || 0) - (a.createdAt || 0);
}

const visibleExpenses = computed(() => {
  const recurring = expenses.filter(it => it.freq !== 0).slice().sort(byNewestFirst);
  const oneTime = expenses.filter(it => it.freq === 0).slice().sort(byNewestFirst);
  return showOneTimeExpenses.value ? [...recurring, ...oneTime] : recurring;
});
function onExpenseFreqChange(item) {
  if (item.freq === 0) showOneTimeExpenses.value = true;
  saveExpenses();
}
function onIncomeFreqChange(item) {
  if (item.freq === 0) showOneTimeIncome.value = true;
  saveIncome();
}

const visibleIncome = computed(() => {
  const recurring = income.filter(it => it.freq !== 0).slice().sort(byNewestFirst);
  const oneTime = income.filter(it => it.freq === 0).slice().sort(byNewestFirst);
  return showOneTimeIncome.value ? [...recurring, ...oneTime] : recurring;
});

const sortedSavings = computed(() => savings.slice().sort(byNewestFirst));

const expThis = computed(() => expenses.reduce((sum, it) => (isExpenseActive(it) && !it.paidFromSavings) ? sum + toRSD(it.amount, it.currency) : sum, 0));
const recurringExpTotal = computed(() => expenses.reduce((sum, it) => (isExpenseActive(it) && it.freq !== 0 && !it.paidFromSavings) ? sum + toRSD(it.amount, it.currency) : sum, 0));
const incThis = computed(() => income.reduce((sum, it) => isIncomeActive(it) ? sum + toRSD(it.amount, it.currency) - divertedForIncome(it.id) : sum, 0));
const expAvg = computed(() => expenses.reduce((sum, it) => {
  if (!it.active || (it.endPeriod && currentPeriod.value > it.endPeriod)) return sum;
  const r = toRSD(it.amount, it.currency);
  return sum + (it.freq > 0 ? r / it.freq : r);
}, 0));
const incAvg = computed(() => income.reduce((sum, it) => {
  if (!it.active || (it.endPeriod && currentPeriod.value > it.endPeriod)) return sum;
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
      color: categoryColor(category, allExpenseCategories.value),
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
const chatInputField = ref(null);

onMounted(() => {
  if (new URLSearchParams(window.location.search).get('quick') !== '1') return;
  nextTick(() => chatInputField.value?.focus());
  const url = new URL(window.location.href);
  url.searchParams.delete('quick');
  window.history.replaceState({}, '', url);
});

const CHAT_ACTION_LABELS = computed(() => ({
  add_expense: t('chatActionExpense'),
  add_income: t('chatActionIncome'),
  add_saving: t('chatActionSaving'),
}));
function freqLabel(freq) {
  if (freq === 0) return t('oneTime');
  if (freq === 1) return t('monthly');
  return `${t('every')} ${freq} ${t('monthsUnit')}`;
}

function scrollChatToBottom() {
  nextTick(() => {
    if (chatLogEl.value) chatLogEl.value.scrollTop = chatLogEl.value.scrollHeight;
  });
}

const voiceSupported = !!(navigator.mediaDevices?.getUserMedia && window.MediaRecorder);
const isListening = ref(false);
const MAX_RECORDING_MS = 30000;
let mediaRecorder = null;
let mediaStream = null;
let audioChunks = [];
let recordingTimeout = null;

function blobToBase64(blob) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onloadend = () => resolve(reader.result.split(',')[1]);
    reader.onerror = () => reject(reader.error);
    reader.readAsDataURL(blob);
  });
}

async function toggleVoiceInput() {
  if (!voiceSupported) return;

  if (isListening.value) {
    mediaRecorder?.stop();
    return;
  }

  try {
    mediaStream = await navigator.mediaDevices.getUserMedia({ audio: true });
  } catch (e) {
    chatLog.push({ role: 'assistant', text: t('voiceNotAllowed') });
    scrollChatToBottom();
    return;
  }

  const preferredType = ['audio/webm', 'audio/ogg', 'audio/mp4'].find(m => MediaRecorder.isTypeSupported(m));
  mediaRecorder = preferredType ? new MediaRecorder(mediaStream, { mimeType: preferredType }) : new MediaRecorder(mediaStream);
  audioChunks = [];

  mediaRecorder.ondataavailable = (event) => {
    if (event.data.size > 0) audioChunks.push(event.data);
  };
  mediaRecorder.onstart = () => {
    isListening.value = true;
    recordingTimeout = setTimeout(() => mediaRecorder?.stop(), MAX_RECORDING_MS);
  };
  mediaRecorder.onstop = () => {
    clearTimeout(recordingTimeout);
    isListening.value = false;
    mediaStream?.getTracks().forEach(track => track.stop());
    const blob = new Blob(audioChunks, { type: mediaRecorder.mimeType || preferredType || 'audio/webm' });
    if (blob.size > 0) sendVoiceMessage(blob);
  };

  mediaRecorder.start();
}

onUnmounted(() => {
  clearTimeout(recordingTimeout);
  if (mediaRecorder && mediaRecorder.state !== 'inactive') mediaRecorder.stop();
  mediaStream?.getTracks().forEach(track => track.stop());
});

async function sendVoiceMessage(blob) {
  chatSending.value = true;
  chatLog.push({ role: 'user', text: t('voiceSent') });
  chatLog.push({ role: 'assistant', text: t('thinking') });
  scrollChatToBottom();

  try {
    const base64 = await blobToBase64(blob);
    const mimeType = (blob.type || 'audio/webm').split(';')[0];
    const { data } = await axios.post('/api/budget/voice', {
      audio: base64,
      mime_type: mimeType,
      expense_categories: allExpenseCategories.value,
      savings_categories: allSavingsCategories.value,
    });
    chatLog.pop();

    const validActions = ['add_expense', 'add_income', 'add_saving'];
    if (validActions.includes(data.action) && data.name && data.amount > 0) {
      const currency = data.currency || 'RSD';
      const freq = data.freq ?? 1;
      const category = data.category || 'Ostalo';
      const freqText = data.action === 'add_saving' ? '' : `, ${freqLabel(freq)}`;
      const catText = data.action === 'add_income' ? '' : `, ${t('categoryWord')} ${categoryLabel(category)}`;
      chatLog.push({
        role: 'assistant',
        text: `${t('confirmAddPrefix')} ${CHAT_ACTION_LABELS.value[data.action]} "${data.name}": ${data.amount} ${currency}${freqText}${catText}?`,
        confirm: { action: data.action, name: data.name, amount: data.amount, currency, freq, category },
      });
    } else {
      chatLog.push({ role: 'assistant', text: t('voiceUnclear') });
    }
  } catch (e) {
    chatLog.pop();
    chatLog.push({ role: 'assistant', text: aiErrorMessage(e, t('chatError')) });
  } finally {
    chatSending.value = false;
    scrollChatToBottom();
  }
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
      expense_categories: allExpenseCategories.value,
      savings_categories: allSavingsCategories.value,
    });
    chatLog.pop();

    const validActions = ['add_expense', 'add_income', 'add_saving'];
    if (validActions.includes(data.action) && data.name && data.amount > 0) {
      const currency = data.currency || 'RSD';
      const freq = data.freq ?? 1;
      const category = data.category || 'Ostalo';
      const freqText = data.action === 'add_saving' ? '' : `, ${freqLabel(freq)}`;
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
    chatLog.push({ role: 'assistant', text: aiErrorMessage(e, t('chatError')) });
  } finally {
    chatSending.value = false;
    scrollChatToBottom();
  }
}

function applyChatAction(msg) {
  const { action, name, amount, currency, freq, category } = msg.confirm;
  if (action === 'add_expense') {
    expenses.push({ id: generateId(), name, amount, currency, freq, active: true, endPeriod: null, category: category || 'Ostalo', createdAt: Date.now() });
    saveExpenses();
  } else if (action === 'add_income') {
    income.push({ id: generateId(), name, amount, currency, freq, active: true, createdAt: Date.now() });
    saveIncome();
  } else if (action === 'add_saving') {
    savings.push({ id: generateId(), name, amount, currency, category: category || 'Ostalo', createdAt: Date.now() });
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
      expense_categories: allExpenseCategories.value,
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
    chatLog.push({ role: 'assistant', text: aiErrorMessage(e, t('receiptError')) });
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
    analysisError.value = aiErrorMessage(e, t('analysisUnavailable'));
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
const YEAR_COLORS = { income: '#2DD4BF' };

async function toggleYearView() {
  showYearView.value = !showYearView.value;
  hoveredIndex.value = null;
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

function formatCreatedAt(timestamp) {
  const d = new Date(timestamp);
  const day = d.getDate();
  const month = MONTH_NAMES.value[d.getMonth()].slice(0, 3);
  return lang.value === 'en' ? `${month} ${day}` : `${day}. ${month.toLowerCase()}`;
}

const editingEndPeriod = ref(null);

function smoothPath(points) {
  if (!points.length) return '';
  if (points.length === 1) return `M ${points[0][0].toFixed(1)},${points[0][1].toFixed(1)}`;
  let d = `M ${points[0][0].toFixed(1)},${points[0][1].toFixed(1)}`;
  for (let i = 0; i < points.length - 1; i++) {
    const p0 = points[i - 1] || points[i];
    const p1 = points[i];
    const p2 = points[i + 1];
    const p3 = points[i + 2] || p2;
    const c1x = p1[0] + (p2[0] - p0[0]) / 6;
    const c1y = p1[1] + (p2[1] - p0[1]) / 6;
    const c2x = p2[0] - (p3[0] - p1[0]) / 6;
    const c2y = p2[1] - (p3[1] - p1[1]) / 6;
    d += ` C ${c1x.toFixed(1)},${c1y.toFixed(1)} ${c2x.toFixed(1)},${c2y.toFixed(1)} ${p2[0].toFixed(1)},${p2[1].toFixed(1)}`;
  }
  return d;
}

const hoveredIndex = ref(null);

const yearChart = computed(() => {
  const width = 640, height = 220, padding = 32;
  const dataMonths = yearMonths.value;
  const gridlines = [0, 0.25, 0.5, 0.75, 1].map(pct => height - padding - pct * (height - padding * 2));

  if (!dataMonths.length) {
    return {
      width, height, padding, colWidth: 0, months: [], segments: [], categoriesUsed: [],
      x: () => 0, y: () => 0, incomePath: '', incomeArea: '', gridlines,
      tooltipCategories: () => [],
    };
  }

  const year = dataMonths[0].period.split('-')[0];
  const byPeriod = new Map(dataMonths.map(m => [m.period, m]));
  const months = Array.from({ length: 12 }, (_, idx) => {
    const period = `${year}-${String(idx + 1).padStart(2, '0')}`;
    const m = byPeriod.get(period);
    return m
      ? { period, income: m.income, expense: m.expense, net: m.net, categories: m.categories || {}, hasData: true }
      : { period, income: 0, expense: 0, net: 0, categories: {}, hasData: false };
  });

  const categoriesUsed = allExpenseCategories.value.filter(cat => months.some(m => (m.categories[cat] || 0) > 0));

  const maxVal = Math.max(1, ...dataMonths.map(m => Math.max(m.income, m.expense)));
  const stepX = (width - padding * 2) / 11;
  const x = (i) => padding + i * stepX;
  const y = (v) => height - padding - (v / maxVal) * (height - padding * 2);
  const baseline = height - padding;
  const barWidth = stepX * 0.56;

  const activeIdx = months.map((m, i) => (m.hasData ? i : null)).filter(i => i !== null);
  const incomePoints = activeIdx.map(i => [x(i), y(months[i].income)]);

  const areaClose = (points) => (points.length
    ? `${smoothPath(points)} L ${points[points.length - 1][0].toFixed(1)},${baseline} L ${points[0][0].toFixed(1)},${baseline} Z`
    : '');

  const segments = [];
  activeIdx.forEach(i => {
    let running = 0;
    categoriesUsed.forEach(cat => {
      const value = months[i].categories[cat] || 0;
      if (value <= 0) return;
      const topY = y(running + value);
      const bottomY = y(running);
      segments.push({
        key: `${i}-${cat}`,
        x: x(i) - barWidth / 2,
        y: topY,
        width: barWidth,
        height: Math.max(0.5, bottomY - topY),
        color: categoryColor(cat, allExpenseCategories.value),
      });
      running += value;
    });
  });

  return {
    width, height, padding, colWidth: stepX, months, x, y, gridlines, categoriesUsed, segments,
    incomePath: smoothPath(incomePoints),
    incomeArea: areaClose(incomePoints),
    tooltipCategories: (idx) => Object.entries(months[idx].categories)
      .filter(([, amount]) => amount > 0)
      .map(([category, amount]) => ({ category, amount, color: categoryColor(category, allExpenseCategories.value) }))
      .sort((a, b) => b.amount - a.amount),
  };
});

const yearTooltipStyle = computed(() => {
  if (hoveredIndex.value === null) return {};
  const chart = yearChart.value;
  const m = chart.months[hoveredIndex.value];
  const leftPct = (chart.x(hoveredIndex.value) / chart.width) * 100;
  const topPct = (Math.min(chart.y(m.income), chart.y(m.expense)) / chart.height) * 100;
  return {
    left: Math.min(88, Math.max(12, leftPct)) + '%',
    top: Math.max(4, topPct) + '%',
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
  --leather:#F3F5F9;
  --leather-hi:#FFFFFF;
  --parchment:#FFFFFF;
  --parchment-dark:#F4F6FA;
  --ink:#161B26;
  --ink-light:#69718A;
  --gilt:#0D9488;
  --gilt-bright:#0F766E;
  --seal:#DC2626;
  --seal-hi:#EF4444;
  --pos:#059669;
  --on-accent:#FFFFFF;
  --border:rgba(15,23,42,0.09);
  --glow:rgba(13,148,136,0.22);
  --card-tint:rgba(15,23,42,0.035);
  --card-tint-dim:rgba(15,23,42,0.02);
  --panel-tint:rgba(15,23,42,0.035);
}
@media (prefers-color-scheme: dark){
  :root:not([data-theme="light"]){
    --leather:#0A0D14;
    --leather-hi:#131826;
    --parchment:#151A24;
    --parchment-dark:#10141C;
    --ink:#EAEDF5;
    --ink-light:#8B93A8;
    --gilt:#2DD4BF;
    --gilt-bright:#5EEAD4;
    --seal:#F87171;
    --seal-hi:#FB9494;
    --pos:#34D399;
    --on-accent:#0B1F1C;
    --border:rgba(255,255,255,0.08);
    --glow:rgba(45,212,191,0.35);
    --card-tint:rgba(255,255,255,0.05);
    --card-tint-dim:rgba(255,255,255,0.02);
    --panel-tint:rgba(255,255,255,0.04);
  }
}
:root[data-theme="dark"]{
  --leather:#0A0D14;
  --leather-hi:#131826;
  --parchment:#151A24;
  --parchment-dark:#10141C;
  --ink:#EAEDF5;
  --ink-light:#8B93A8;
  --gilt:#2DD4BF;
  --gilt-bright:#5EEAD4;
  --seal:#F87171;
  --seal-hi:#FB9494;
  --pos:#34D399;
  --on-accent:#0B1F1C;
  --border:rgba(255,255,255,0.08);
  --glow:rgba(45,212,191,0.35);
  --card-tint:rgba(255,255,255,0.05);
  --card-tint-dim:rgba(255,255,255,0.02);
  --panel-tint:rgba(255,255,255,0.04);
}
.tome{ width:100%; max-width:780px; position:relative; margin:0 auto; }
@media (min-width:900px){
  .tome{ max-width:960px; }
}

.cover{
  background:var(--leather);
  border-radius:22px;
  padding:6px;
  box-shadow:0 40px 80px -32px rgba(0,0,0,0.45);
  position:relative;
}

.page{
  background:var(--parchment);
  border:1px solid var(--border);
  border-radius:16px;
  padding:32px 30px 30px 30px;
  position:relative;
  overflow:hidden;
  font-family:'Inter',system-ui,-apple-system,sans-serif;
  color:var(--ink);
  font-variant-numeric:tabular-nums;
}

.month-nav{
  display:flex; align-items:center; justify-content:center; gap:16px;
  margin-bottom:8px;
}
.icon{
  width:14px; height:14px; display:inline-block; vertical-align:middle;
  fill:none; stroke:currentColor; stroke-width:1.6; stroke-linecap:round; stroke-linejoin:round;
}
.icon circle, .icon rect{ fill:none; stroke:currentColor; stroke-width:1.6; }

.month-nav .nav-btn{
  background:var(--card-tint); border:1px solid var(--border); color:var(--ink-light);
  display:inline-flex; align-items:center; justify-content:center;
  width:30px; height:30px; cursor:pointer; border-radius:10px;
  transition:border-color 0.15s, color 0.15s;
}
.month-nav .nav-btn:hover:not(:disabled){ border-color:var(--gilt); color:var(--gilt); }
.month-nav .nav-btn:disabled{ opacity:0.4; cursor:default; }
.month-nav .nav-btn .icon{ width:12px; height:12px; }

.icon-link{ display:inline-flex; align-items:center; gap:5px; }
.icon-link .icon{ color:var(--ink-light); }
.month-nav .month-label{
  font-family:'Manrope',sans-serif; text-transform:uppercase; letter-spacing:0.06em;
  font-size:13px; font-weight:700; color:var(--gilt); min-width:140px; text-align:center;
}

.banner{
  display:flex; flex-direction:column; gap:8px;
  background:var(--card-tint); border:1px solid var(--border);
  border-radius:14px; padding:14px 18px; margin-bottom:20px;
  font-size:12.5px;
}
.banner-actions{ display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.banner-actions input{
  width:100px; font-family:'Inter',sans-serif; font-size:13px; color:var(--ink);
  background:transparent; border:none; border-bottom:1px solid var(--border); padding:3px 2px;
}
.banner-actions input:focus{ outline:none; border-bottom:1px solid var(--gilt); }
.banner .add-row{ margin:0; padding:8px 14px; }
.banner .reset-link{ margin:0; }

.masthead{ text-align:center; border-bottom:1px solid var(--border); padding-bottom:18px; margin-bottom:24px; position:relative; }
.masthead h1{ margin:0; font-size:28px; letter-spacing:-0.02em; color:var(--ink); font-family:'Manrope',sans-serif; font-weight:800; }
.masthead .sub{ font-size:13px; color:var(--ink-light); margin-top:6px; }
.masthead .greeting{ font-size:12.5px; color:var(--gilt); margin-top:12px; opacity:0.95; font-weight:500; }

.section-title{
  text-align:left;
  font-size:12px;
  text-transform:uppercase;
  letter-spacing:0.08em;
  color:var(--ink-light);
  font-family:'Inter',sans-serif;
  font-weight:700;
  margin:30px 0 14px 0;
}
.section-title:first-of-type{ margin-top:0; }

.page table{ width:100%; border-collapse:collapse; margin-bottom:6px; }
.page thead th{
  font-size:11px; text-transform:uppercase; letter-spacing:0.05em;
  color:var(--ink-light); font-weight:600;
  text-align:left; padding:0 8px 10px 8px;
  border-bottom:1px solid var(--border);
}
.page tbody td{ padding:10px 8px; border-bottom:1px solid var(--border); vertical-align:middle; }
.page tbody tr.inactive{ opacity:0.45; }

.page input[type=text], .page input[type=number]{
  font-family:'Inter',sans-serif; font-size:14px; color:var(--ink);
  background:transparent; border:none; border-bottom:1px solid transparent;
  width:100%; padding:4px 2px;
}
.page input[type=text]:focus, .page input[type=number]:focus{ outline:none; border-bottom:1px solid var(--gilt); }
.page td.cell-name input{ display:block; text-overflow:ellipsis; overflow:hidden; white-space:nowrap; }
.cat-dot{ display:none; width:8px; height:8px; border-radius:50%; flex-shrink:0; }
.page select{
  font-family:'Inter',sans-serif; font-size:13px; color:var(--ink);
  background:transparent; border:none; border-bottom:1px solid transparent; padding:4px 0;
}
.page select:focus{ outline:none; border-bottom:1px solid var(--gilt); }
.page select option{ color:#111827; background:#fff; }

.amt-col{ width:90px; }
.cur-col{ width:64px; }
.freq-col{ width:150px; }
.freq-custom{ display:flex; flex-wrap:wrap; align-items:center; gap:3px; margin-top:4px; font-size:11px; color:var(--ink-light); }
.freq-interval-input{
  width:32px; font-family:'Inter',sans-serif; font-size:12px; color:var(--ink);
  background:transparent; border:none; border-bottom:1px solid var(--border); padding:2px;
}
.freq-interval-input:focus{ outline:none; border-bottom:1px solid var(--gilt); }
.freq-anchor-input{
  font-family:'Inter',sans-serif; font-size:11px; color:var(--ink); color-scheme:light dark;
  background:transparent; border:none; border-bottom:1px solid var(--border); padding:2px; max-width:100px;
}
.freq-anchor-input:focus{ outline:none; border-bottom:1px solid var(--gilt); }
.end-col{ width:118px; }
.cat-col{ width:110px; }
.chk-col{ width:36px; text-align:center; }
.del-col{ width:30px; text-align:center; white-space:nowrap; }
.cat-suggest{
  display:flex; align-items:center; gap:4px; font-family:'Inter',sans-serif;
  font-size:11px; color:var(--ink-light); white-space:normal;
}
.quick-add-form{
  display:flex; flex-wrap:wrap; align-items:center; gap:6px; margin-top:8px;
  padding:10px; border:1px solid var(--border); border-radius:10px;
}
.quick-add-form input, .quick-add-form select{
  font-family:'Inter',sans-serif; font-size:13px; color:var(--ink); background:transparent;
  border:1px solid var(--border); border-radius:6px; padding:6px 8px;
}
.quick-add-name{ flex:1 1 160px; min-width:0; }
.quick-add-amount{ width:100px; }

.category-manage{ margin-top:4px; }
.category-manage-list{
  display:flex; flex-direction:column; gap:2px; margin-top:6px;
  border:1px solid var(--border); border-radius:10px; padding:6px;
}
.category-manage-row{
  display:flex; align-items:center; justify-content:space-between; gap:8px;
  padding:4px 6px; font-family:'Inter',sans-serif; font-size:13px; color:var(--ink);
}
.category-manage-row input{
  font-family:'Inter',sans-serif; font-size:13px; color:var(--ink); color-scheme:light dark;
  background:transparent; border:none; border-bottom:1px solid var(--gilt); padding:2px; flex:1;
}
.category-manage-actions{ display:flex; align-items:center; gap:2px; flex-shrink:0; }
.source-col{ width:130px; }
.diverted-badge, .created-badge{
  display:block; font-family:'Inter',sans-serif; font-size:11px; color:var(--ink-light);
  white-space:normal;
}

.month-picker-native{
  font-family:'Inter',sans-serif; font-size:12.5px; color:var(--ink); color-scheme:light dark;
  background:transparent; border:none; border-bottom:1px solid var(--gilt);
  width:100%; padding:4px 2px;
}
.month-picker-btn{
  background:none; border:none; color:var(--ink); font-family:'Inter',sans-serif; font-size:13px;
  cursor:pointer; text-decoration:underline; text-decoration-style:dotted; text-decoration-color:var(--ink-light);
  padding:3px 2px; text-align:left; white-space:nowrap;
}

.del-btn{ background:none; border:none; color:var(--seal); cursor:pointer; display:inline-flex; padding:4px; }
.del-btn:hover{ opacity:0.7; }
.del-btn .icon{ width:13px; height:13px; }

.add-row{
  margin:14px 0 10px 0;
  background:var(--gilt);
  border:1px solid var(--gilt);
  color:var(--on-accent);
  font-family:'Inter',sans-serif;
  font-weight:600;
  font-size:13.5px;
  padding:10px 18px;
  border-radius:10px;
  cursor:pointer;
  display:inline-flex;
  align-items:center;
  gap:7px;
  transition:opacity 0.15s, transform 0.1s;
}
.add-row:hover:not(:disabled){ opacity:0.88; }
.add-row:active:not(:disabled){ transform:scale(0.98); }
.add-row:disabled{ opacity:0.5; cursor:default; }
.add-row .icon{ color:var(--on-accent); }

.chart-section{ margin:18px 0 26px 0; }
.cat-bar-row{
  display:flex; align-items:center; gap:10px;
  margin-bottom:6px; padding:3px 0;
}
.cat-bar-label{
  width:88px; flex-shrink:0; font-size:11.5px; color:var(--ink);
  text-align:right; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.cat-bar-track{ flex:1; height:14px; background:var(--card-tint); border-radius:7px; overflow:hidden; }
.cat-bar-fill{ height:100%; border-radius:7px; }
.cat-bar-value{
  width:150px; flex-shrink:0; font-size:11.5px; color:var(--ink-light);
  text-align:left; white-space:nowrap;
}
.cat-bar-pct{ color:var(--ink-light); opacity:0.8; }

.year-toggle-row{ display:flex; align-items:center; justify-content:center; gap:16px; margin-bottom:6px; position:relative; }
.theme-toggle{
  position:absolute; right:0; top:50%; transform:translateY(-50%);
  background:var(--card-tint); border:1px solid var(--border); color:var(--gilt);
  width:28px; height:28px; border-radius:10px; cursor:pointer;
  display:inline-flex; align-items:center; justify-content:center;
}
.theme-toggle:hover{ border-color:var(--gilt); }
.theme-toggle .icon{ width:13px; height:13px; }
.lang-link{
  position:absolute; right:36px; top:50%; transform:translateY(-50%);
  font-size:11px; color:var(--ink-light); text-decoration:underline;
  font-family:'Inter',sans-serif;
}

.year-view{ padding-top:4px; }
.year-legend{ display:flex; flex-wrap:wrap; justify-content:center; gap:8px 16px; margin:10px 0 16px 0; font-size:11.5px; color:var(--ink); }
.legend-item{ display:inline-flex; align-items:center; gap:6px; }
.legend-item .swatch{ width:10px; height:10px; border-radius:50%; display:inline-block; }
.legend-item .swatch.line-swatch{ border-radius:2px; }

.year-chart-wrap{ position:relative; margin-bottom:18px; }
.year-chart{ width:100%; height:auto; display:block; }
.year-gridline{ stroke:var(--border); stroke-width:1; }
.year-hover-line{ stroke:var(--gilt); stroke-width:1; stroke-dasharray:3,3; opacity:0.6; }
.year-hit{ cursor:pointer; }
.year-bar-seg{ transition:opacity 0.15s; }
.year-axis-label{ font-size:9px; fill:var(--ink-light); font-family:'Inter',sans-serif; }
.year-axis-label.is-future{ opacity:0.4; }

.year-tooltip{
  position:absolute; transform:translate(-50%,-100%); margin-top:-8px;
  background:#0B1420; color:#EAEDF5; border:1px solid rgba(45,212,191,0.4);
  padding:8px 10px; font-size:11px; white-space:nowrap; pointer-events:none;
  box-shadow:0 4px 12px rgba(0,0,0,0.35); z-index:5; font-family:'Inter',sans-serif;
  border-radius:8px;
}
.year-tooltip strong{ display:block; margin-bottom:4px; color:#5EEAD4; font-size:12px; }
.year-tooltip-row{ display:flex; align-items:center; gap:6px; margin-top:2px; }
.year-tooltip-row .swatch{ width:8px; height:8px; border-radius:50%; display:inline-block; flex-shrink:0; }
.year-tooltip-row .swatch.line-swatch{ border-radius:2px; }
.year-tooltip-row.net{ margin-top:5px; padding-top:5px; border-top:1px solid rgba(45,212,191,0.25); font-weight:600; }
.year-tooltip-row.pos{ color:#34D399; }
.year-tooltip-row.neg{ color:#F87171; }

.year-table{ width:100%; border-collapse:collapse; font-size:12px; }
.year-table th{
  font-size:10px; text-transform:uppercase; letter-spacing:0.05em; color:var(--ink-light);
  font-weight:600; text-align:left; padding:0 6px 8px 6px; border-bottom:1px solid var(--border);
}
.year-table td{ padding:8px 6px; border-bottom:1px solid var(--border); color:var(--ink); }
.year-table td.pos{ color:var(--pos); }
.year-table td.neg{ color:var(--seal); }

.rates{
  display:flex; gap:26px; flex-wrap:wrap;
  border-top:1px solid var(--border); border-bottom:1px solid var(--border);
  padding:18px 2px; margin:26px 0 20px 0; font-size:12.5px;
}
.rates label{ color:var(--ink-light); display:block; margin-bottom:4px; font-size:10px; text-transform:uppercase; letter-spacing:0.05em; }
.rates input{ width:80px; border-bottom:1px solid var(--border); }
.rates .note{ color:var(--ink-light); font-size:11.5px; align-self:flex-end; }

.converter{
  display:flex; align-items:flex-end; gap:14px; flex-wrap:wrap;
  padding-bottom:22px; margin-bottom:24px;
  border-bottom:1px solid var(--border); font-size:12.5px;
}
.converter label{ display:block; color:var(--ink-light); font-size:10px; text-transform:uppercase; margin-bottom:4px; letter-spacing:0.05em; }
.converter input[type=number]{ width:100px; border-bottom:1px solid var(--border); }
.converter select{ border-bottom:1px solid var(--border); }
.converter .eq{ font-size:15px; padding-bottom:4px; color:var(--ink-light); }
.converter .result{ font-weight:700; padding-bottom:4px; font-size:14px; font-family:'Manrope',sans-serif; }

.totals{ display:flex; align-items:center; justify-content:space-between; gap:20px; flex-wrap:wrap; }
.totals-text{ max-width:340px; }
.totals-text .row{ display:flex; justify-content:space-between; font-size:13px; padding:7px 0; border-bottom:1px solid var(--border); }
.totals-text .row .lbl{ color:var(--ink-light); }
.totals-text .row.main .lbl, .totals-text .row.main .val{ color:var(--ink); font-weight:700; }
.totals-text .row .val.pos{ color:var(--pos); }
.totals-text .row .val.neg{ color:var(--seal); }

.seal-wrap{
  width:132px; height:132px; border-radius:50%; flex-shrink:0; position:relative;
  background:linear-gradient(135deg, var(--gilt), #818CF8 130%);
  box-shadow:0 14px 30px -10px var(--glow), inset 0 1px 0 rgba(255,255,255,0.25);
  display:flex; flex-direction:column; align-items:center; justify-content:center;
  color:#0B1420; text-align:center;
}
.seal-wrap .lbl{ font-size:10px; letter-spacing:0.08em; text-transform:uppercase; margin-bottom:5px; font-family:'Inter',sans-serif; font-weight:600; opacity:0.75; }
.seal-wrap .val{ font-size:17px; font-weight:800; line-height:1.2; font-family:'Manrope',sans-serif; }
.seal-wrap .cur{ font-size:9px; margin-top:4px; font-weight:500; opacity:0.75; }

.savings-line{
  font-size:12.5px; color:var(--ink-light); text-align:center;
  margin-top:18px; padding-top:16px; border-top:1px solid var(--border);
}
.savings-line strong{ color:var(--ink); }
.savings-line span{ color:var(--ink); }

.foot-note{ margin-top:24px; font-size:11.5px; color:var(--ink-light); text-align:center; }
.reset-link{ background:none; border:none; color:var(--ink-light); font-family:'Inter',sans-serif; font-size:11px; text-decoration:underline; cursor:pointer; padding:0; }
.load-more-btn{ display:block; margin:8px 0 4px 0; }

.chat-box{
  border:1px solid var(--border); border-radius:14px; padding:12px 14px;
  margin-bottom:22px; background:var(--panel-tint);
}
.chat-log{ max-height:180px; overflow-y:auto; margin-bottom:8px; font-size:12.5px; }
.chat-log:empty{ display:none; }
.chat-msg{ margin-bottom:8px; }
.chat-msg.user{ text-align:right; }
.chat-msg.user span{ color:var(--ink); font-weight:600; }
.chat-msg.assistant{ text-align:left; }
.chat-msg.assistant span{ color:var(--ink-light); }
.chat-confirm{ display:flex; gap:8px; margin-top:4px; }
.chat-confirm button{ padding:5px 12px; font-size:11.5px; margin:0; }
.chat-input{ display:flex; gap:8px; }
.chat-input input{
  flex:1; font-family:'Inter',sans-serif; font-size:13.5px; color:var(--ink);
  background:transparent; border:none; border-bottom:1px solid var(--border); padding:6px 2px;
}
.chat-input input:focus{ outline:none; border-bottom:1px solid var(--gilt); }
.chat-input button{
  background:var(--card-tint); border:1px solid var(--border); color:var(--ink);
  font-family:'Inter',sans-serif; font-weight:500; font-size:12.5px;
  padding:8px 14px; border-radius:10px; cursor:pointer; transition:border-color 0.15s;
}
.chat-input button:hover:not(:disabled){ border-color:var(--gilt); }
.chat-input button:disabled{ opacity:0.5; cursor:default; }
.chat-input button[type=submit]{ background:var(--gilt); border-color:var(--gilt); color:var(--on-accent); font-weight:600; }
.chat-input button[type=submit]:hover:not(:disabled){ opacity:0.88; }
.mic-btn{ padding:8px 11px; font-size:14px; }
.receipt-input{ display:none; }
.mic-btn.listening{ background:rgba(239,68,68,0.12); border-color:var(--seal); animation:micPulse 1.2s ease-in-out infinite; }
@keyframes micPulse{
  0%, 100%{ box-shadow:0 0 0 0 rgba(239,68,68,0.3); }
  50%{ box-shadow:0 0 0 6px rgba(239,68,68,0); }
}

.analyze-row{ text-align:center; margin:16px 0; }

.flash{ animation:accentFlash 0.5s ease; }
@keyframes accentFlash{
  0%{ background:rgba(45,212,191,0.3); }
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
  .chat-log{ max-height:140px; }
  .chat-input{ flex-wrap:wrap; }
  .chat-input input{ min-width:0; flex-basis:100%; }

  .cat-bar-row{ flex-wrap:wrap; }
  .cat-bar-label{ width:auto; text-align:left; flex:1 1 auto; }
  .cat-bar-track{ flex-basis:100%; order:3; }
  .cat-bar-value{ width:auto; text-align:right; }

  .page table, .page thead, .page tbody, .page tr, .page td{ display:block; }
  .page thead{ display:none; }
  .amt-col, .cur-col, .freq-col, .end-col, .cat-col, .chk-col, .del-col, .source-col{ width:auto; }

  .page tbody tr{
    position:relative;
    border:1px solid var(--border);
    border-radius:10px;
    padding:9px 36px 2px 10px;
    margin-bottom:7px;
    background:var(--card-tint);
  }
  .page tbody tr.inactive{ background:var(--card-tint-dim); }

  .page tbody td{ padding:4px 0; border-bottom:1px solid var(--border); }
  .page td[data-label]{
    display:flex; align-items:center; justify-content:space-between; gap:10px;
  }
  .page td[data-label]::before{
    content:attr(data-label);
    font-size:9px; text-transform:uppercase; letter-spacing:0.04em;
    color:var(--ink-light); flex-shrink:0;
  }
  .page td[data-label] input, .page td[data-label] select{ text-align:right; }
  .page td[data-label]:last-of-type{ border-bottom:none; }

  .page td.cell-name{ display:flex; flex-wrap:wrap; align-items:center; gap:2px 6px; font-size:14px; padding:6px 0; border-bottom:1px solid var(--border); }
  .page td.cell-name input{ font-size:14px; font-weight:600; text-overflow:ellipsis; }
  .page td.cell-name .diverted-badge, .page td.cell-name .created-badge{ flex-basis:100%; }
  .cat-dot{ display:inline-block; }

  .page td.amt-col{ display:inline-flex; width:58%; border-bottom:none; }
  .page td.cur-col{ display:inline-flex; width:40%; }
  .page td.cur-col[data-label]::before{ display:none; }
  .page td.cur-col select{ text-align:left; }

  .page td.end-col{ display:inline-flex; width:48%; border-bottom:none; }
  .page td.cat-col{ display:inline-flex; width:50%; }

  .page td.chk-col{ padding:3px 0; }

  .page td.del-col{
    position:absolute; top:8px; right:8px;
    width:auto; padding:0; border:none; text-align:right;
  }
  .page td.del-col .del-btn .icon{ width:15px; height:15px; }
  .page td.del-col .del-btn{ padding:2px; }

  .totals{ flex-direction:column; align-items:stretch; gap:14px; }
  .totals-text{ max-width:none; }
  .seal-wrap{ margin:0 auto; }
}
</style>
