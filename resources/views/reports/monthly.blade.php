<?php
$monthNamesSr = ['Januar', 'Februar', 'Mart', 'April', 'Maj', 'Jun', 'Jul', 'Avgust', 'Septembar', 'Oktobar', 'Novembar', 'Decembar'];
$monthNamesEn = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
$categoryLabelsEn = [
    'Stanovanje' => 'Housing', 'Hrana' => 'Food', 'Prevoz' => 'Transport', 'Zdravlje' => 'Health',
    'Zabava' => 'Entertainment', 'Računi' => 'Bills', 'Otplate' => 'Debt payments', 'Ostalo' => 'Other',
    'Gotovina' => 'Cash', 'Štednja' => 'Savings', 'Investicije' => 'Investments', 'Nekretnine' => 'Real estate',
];

[$y, $m] = explode('-', $period);
$monthLabel = $lang === 'en'
    ? ($monthNamesEn[(int) $m - 1].' '.$y)
    : ($monthNamesSr[(int) $m - 1].' '.$y);

$fmt = fn ($n) => number_format(round($n), 0, ',', '.');
$catLabel = fn ($cat) => $lang === 'en' ? ($categoryLabelsEn[$cat] ?? $cat) : $cat;

$t = fn ($sr, $en) => $lang === 'en' ? $en : $sr;
?>
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
<meta charset="UTF-8">
<title>Bilanso — {{ $monthLabel }}</title>
<style>
  @page { margin: 28px 36px; }
  body{ font-family: 'DejaVu Sans', sans-serif; color:#161B26; font-size:12px; }
  .header{ display:table; width:100%; margin-bottom:18px; border-bottom:2px solid #0D9488; padding-bottom:12px; }
  .brand{ font-size:22px; font-weight:700; color:#0D9488; }
  .subtitle{ font-size:11px; color:#69718A; margin-top:2px; }
  .period{ font-size:15px; font-weight:700; float:right; color:#161B26; }

  .summary{ width:100%; margin:18px 0 22px 0; }
  .summary-cell{
    display:inline-block; width:23%; margin-right:1.3%; vertical-align:top;
    border:1px solid #E4E7EE; border-radius:6px; padding:10px 12px;
  }
  .summary-cell .lbl{ font-size:9px; text-transform:uppercase; letter-spacing:0.04em; color:#69718A; margin-bottom:4px; }
  .summary-cell .val{ font-size:16px; font-weight:700; }
  .val.pos{ color:#0D9488; }
  .val.neg{ color:#E11D48; }

  .section-title{
    font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.05em;
    color:#0D9488; margin:20px 0 8px 0; border-bottom:1px solid #E4E7EE; padding-bottom:4px;
  }
  table.items{ width:100%; border-collapse:collapse; margin-bottom:6px; }
  table.items th{
    text-align:left; font-size:9px; text-transform:uppercase; color:#69718A;
    padding:4px 6px; border-bottom:1px solid #E4E7EE;
  }
  table.items td{ padding:5px 6px; border-bottom:1px solid #F1F2F6; font-size:11px; }
  table.items td.amt{ text-align:right; white-space:nowrap; }
  .empty-note{ color:#9AA1B4; font-size:11px; padding:6px; }

  .cat-row{ display:table; width:100%; margin-bottom:5px; }
  .cat-name{ display:table-cell; width:26%; font-size:10.5px; vertical-align:middle; }
  .cat-bar-wrap{ display:table-cell; width:54%; vertical-align:middle; }
  .cat-bar-bg{ background:#F1F2F6; border-radius:4px; height:8px; width:100%; }
  .cat-bar-fill{ background:#0D9488; border-radius:4px; height:8px; }
  .cat-amt{ display:table-cell; width:20%; text-align:right; font-size:10.5px; vertical-align:middle; padding-left:8px; }

  .footer{ margin-top:26px; padding-top:8px; border-top:1px solid #E4E7EE; font-size:9px; color:#9AA1B4; text-align:center; }
</style>
</head>
<body>

  <div class="header">
    <div class="brand">Bilanso</div>
    <div class="subtitle">{{ $t('Mesečni izveštaj', 'Monthly report') }}</div>
    <div class="period">{{ $monthLabel }}</div>
  </div>

  <div class="summary">
    <div class="summary-cell">
      <div class="lbl">{{ $t('Primanja', 'Income') }}</div>
      <div class="val">{{ $fmt($incomeTotal) }} RSD</div>
    </div>
    <div class="summary-cell">
      <div class="lbl">{{ $t('Troškovi', 'Expenses') }}</div>
      <div class="val">{{ $fmt($expenseTotal) }} RSD</div>
    </div>
    <div class="summary-cell">
      <div class="lbl">{{ $t('Neto', 'Net') }}</div>
      <div class="val {{ $netTotal >= 0 ? 'pos' : 'neg' }}">{{ $netTotal >= 0 ? '+' : '' }}{{ $fmt($netTotal) }} RSD</div>
    </div>
    <div class="summary-cell">
      <div class="lbl">{{ $t('Ukupna ušteđevina', 'Total savings') }}</div>
      <div class="val">{{ $fmt($savingsTotal) }} RSD</div>
    </div>
  </div>

  @if($categories->isNotEmpty())
    <div class="section-title">{{ $t('Troškovi po kategoriji', 'Expenses by category') }}</div>
    @foreach($categories as $cat => $amount)
      <div class="cat-row">
        <div class="cat-name">{{ $catLabel($cat) }}</div>
        <div class="cat-bar-wrap">
          <div class="cat-bar-bg"><div class="cat-bar-fill" style="width:{{ max(2, round($amount / $categoryMax * 100)) }}%"></div></div>
        </div>
        <div class="cat-amt">{{ $fmt($amount) }} RSD</div>
      </div>
    @endforeach
  @endif

  <div class="section-title">{{ $t('Primanja', 'Income') }}</div>
  @if($incomeItems->isEmpty())
    <div class="empty-note">{{ $t('Nema aktivnih primanja ovog meseca.', 'No active income this month.') }}</div>
  @else
    <table class="items">
      <thead><tr><th>{{ $t('Stavka', 'Item') }}</th><th class="amt">{{ $t('Iznos', 'Amount') }}</th></tr></thead>
      <tbody>
        @foreach($incomeItems as $item)
          <tr><td>{{ $item['name'] ?? '' }}</td><td class="amt">{{ $fmt($item['amount'] ?? 0) }} {{ $item['currency'] ?? 'RSD' }}</td></tr>
        @endforeach
      </tbody>
    </table>
  @endif

  <div class="section-title">{{ $t('Troškovi', 'Expenses') }}</div>
  @if($expenseItems->isEmpty())
    <div class="empty-note">{{ $t('Nema aktivnih troškova ovog meseca.', 'No active expenses this month.') }}</div>
  @else
    <table class="items">
      <thead><tr><th>{{ $t('Stavka', 'Item') }}</th><th>{{ $t('Kategorija', 'Category') }}</th><th class="amt">{{ $t('Iznos', 'Amount') }}</th></tr></thead>
      <tbody>
        @foreach($expenseItems as $item)
          <tr>
            <td>{{ $item['name'] ?? '' }}</td>
            <td>{{ $catLabel($item['category'] ?? 'Ostalo') }}</td>
            <td class="amt">{{ $fmt($item['amount'] ?? 0) }} {{ $item['currency'] ?? 'RSD' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif

  <div class="section-title">{{ $t('Štednja i imovina', 'Savings & assets') }}</div>
  @if($savingsItems->isEmpty())
    <div class="empty-note">{{ $t('Nema sačuvanih stavki štednje.', 'No saved savings items.') }}</div>
  @else
    <table class="items">
      <thead><tr><th>{{ $t('Stavka', 'Item') }}</th><th>{{ $t('Kategorija', 'Category') }}</th><th class="amt">{{ $t('Iznos', 'Amount') }}</th></tr></thead>
      <tbody>
        @foreach($savingsItems as $item)
          <tr>
            <td>{{ $item['name'] ?? '' }}</td>
            <td>{{ $catLabel($item['category'] ?? 'Ostalo') }}</td>
            <td class="amt">{{ $fmt($item['amount'] ?? 0) }} {{ $item['currency'] ?? 'RSD' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif

  <div class="footer">
    Bilanso · {{ $t('Kurs', 'Rate') }}: 1 USD = {{ $fmt($rates['usd'] ?? 0) }} RSD, 1 EUR = {{ $fmt($rates['eur'] ?? 0) }} RSD
    · {{ $t('Generisano', 'Generated') }} {{ now()->format('d.m.Y H:i') }}
  </div>

</body>
</html>
