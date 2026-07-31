<?php
$lang = request()->cookie('lang', 'sr');
?>
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="manifest" href="/manifest.json">
<script>
  (function(){
    var saved = localStorage.getItem('theme');
    var theme = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    document.documentElement.setAttribute('data-theme', theme);
  })();
</script>
<meta name="theme-color" content="#0A0D14">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
<title>{{ $lang === 'en' ? 'Settings — Bilanso' : 'Podešavanja — Bilanso' }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Manrope:wght@700;800&display=swap" rel="stylesheet">
<style>
  :root{
    --leather:#F3F5F9; --card:#FFFFFF; --gilt:#0D9488; --on-accent:#FFFFFF;
    --ink:#161B26; --ink-light:#69718A; --border:rgba(15,23,42,0.09);
    --seal:#DC2626; --seal-bg:rgba(220,38,38,0.06); --on-seal:#FFFFFF; --pos:#059669;
  }
  @media (prefers-color-scheme: dark){
    :root:not([data-theme="light"]){
      --leather:#0A0D14; --card:#131826; --gilt:#2DD4BF; --on-accent:#0B1F1C;
      --ink:#EAEDF5; --ink-light:#8B93A8; --border:rgba(255,255,255,0.08);
      --seal:#F87171; --seal-bg:rgba(248,113,113,0.08); --on-seal:#2B0A0A; --pos:#34D399;
    }
  }
  :root[data-theme="dark"]{
    --leather:#0A0D14; --card:#131826; --gilt:#2DD4BF; --on-accent:#0B1F1C;
    --ink:#EAEDF5; --ink-light:#8B93A8; --border:rgba(255,255,255,0.08);
    --seal:#F87171; --seal-bg:rgba(248,113,113,0.08); --on-seal:#2B0A0A; --pos:#34D399;
  }
  *{box-sizing:border-box;}
  html,body{ height:100%; margin:0; }
  body{
    padding:36px 16px; min-height:100vh; display:flex; justify-content:center;
    background:var(--leather); font-family:'Inter',system-ui,sans-serif; color:var(--ink);
  }
  .wrap{ width:100%; max-width:560px; }
  .back{ display:inline-flex; align-items:center; gap:6px; font-size:13px; color:var(--ink-light); text-decoration:none; margin-bottom:18px; }
  .back:hover{ color:var(--gilt); }
  h1{ font-family:'Manrope',sans-serif; font-weight:800; font-size:22px; margin:0 0 22px 0; }
  .card{
    background:var(--card); border:1px solid var(--border); border-radius:14px;
    padding:20px 20px 22px; margin-bottom:16px;
  }
  .card.danger{ border-color:var(--seal); background:var(--seal-bg); }
  h2{ font-family:'Manrope',sans-serif; font-weight:700; font-size:14px; margin:0 0 4px 0; }
  .card .hint{ font-size:12.5px; color:var(--ink-light); margin:0 0 14px 0; line-height:1.5; }
  .row{ display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
  .row + .row{ margin-top:10px; }
  .btn{
    background:var(--gilt); border:1px solid var(--gilt); color:var(--on-accent);
    font-family:'Inter',sans-serif; font-weight:600; font-size:13px; padding:8px 16px;
    border-radius:8px; cursor:pointer;
  }
  .btn:hover{ opacity:0.88; }
  .btn:disabled{ opacity:0.5; cursor:default; }
  .btn-ghost{
    background:none; border:1px solid var(--border); color:var(--ink);
    font-family:'Inter',sans-serif; font-size:13px; padding:8px 16px; border-radius:8px; cursor:pointer;
  }
  .btn-ghost:hover{ border-color:var(--gilt); color:var(--gilt); }
  .btn-danger{
    background:var(--seal); border:1px solid var(--seal); color:var(--on-seal);
    font-family:'Inter',sans-serif; font-weight:600; font-size:13px; padding:8px 16px; border-radius:8px; cursor:pointer;
  }
  .btn-danger:hover{ opacity:0.85; }
  .status{ font-size:12.5px; color:var(--pos); margin-top:10px; }
  .status.err{ color:var(--seal); }
  select, input[type=file]{
    font-family:'Inter',sans-serif; font-size:12.5px; color:var(--ink);
    background:transparent; border:1px solid var(--border); border-radius:8px; padding:6px 8px;
  }
  /* The dropdown's own closed face inherits background:transparent fine, but
     the open option list is rendered natively and does not — without this it
     silently defaults to a white popup, making --ink's light dark-mode text
     unreadable (white-on-white). */
  select option{ background:var(--card); color:var(--ink); }
  input[type=password]{
    font-family:'Inter',sans-serif; font-size:13px; padding:8px 10px; background:transparent;
    border:1px solid var(--border); color:var(--ink); border-radius:8px; flex:1; min-width:160px;
  }
  .delete-form{ display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-top:12px; }
  .lang-links{ display:flex; gap:10px; }
  .lang-links a{
    font-family:'Inter',sans-serif; font-size:13px; text-decoration:none; padding:8px 14px;
    border:1px solid var(--border); border-radius:8px; color:var(--ink);
  }
  .lang-links a.active{ border-color:var(--gilt); color:var(--gilt); font-weight:600; }
  .modal-overlay{ position:fixed; inset:0; background:rgba(0,0,0,.6); display:flex; align-items:center; justify-content:center; z-index:1000; }
  .modal-overlay[hidden]{ display:none; }
  .modal-card{ background:var(--card); border:1px solid var(--border); border-radius:12px; padding:24px; max-width:360px; width:90%; color:var(--ink); }
</style>
</head>
<body>
  <div class="wrap">
    <a class="back" href="{{ route('budget.index') }}">&larr; {{ $lang === 'en' ? 'Back to book' : 'Nazad na knjižicu' }}</a>
    <h1>{{ $lang === 'en' ? 'Settings' : 'Podešavanja' }}</h1>

    <div class="card">
      <h2>{{ $lang === 'en' ? 'Account' : 'Nalog' }}</h2>
      <p class="hint">{{ auth()->user()->name }} &middot; {{ auth()->user()->email }}</p>
    </div>

    <div class="card">
      <h2>{{ $lang === 'en' ? 'Appearance' : 'Izgled' }}</h2>
      <div class="row">
        <span class="hint" style="margin:0;">{{ $lang === 'en' ? 'Theme' : 'Tema' }}</span>
        <button type="button" class="btn-ghost" id="theme-toggle"></button>
      </div>
      <div class="row">
        <span class="hint" style="margin:0;">{{ $lang === 'en' ? 'Language' : 'Jezik' }}</span>
        <div class="lang-links">
          <a href="{{ route('lang.switch', 'sr') }}" class="{{ $lang === 'sr' ? 'active' : '' }}">SR</a>
          <a href="{{ route('lang.switch', 'en') }}" class="{{ $lang === 'en' ? 'active' : '' }}">EN</a>
        </div>
      </div>
    </div>

    <div class="card">
      <h2>{{ $lang === 'en' ? 'Notifications' : 'Obaveštenja' }}</h2>
      <p class="hint">
        {{ $lang === 'en'
          ? "Get a reminder on the 1st of the month to log last month's leftover as savings."
          : 'Dobij podsetnik 1. u mesecu da upišeš ostatak iz prošlog meseca u štednju.' }}
      </p>
      <div class="row">
        <button type="button" class="btn-ghost" id="push-toggle">…</button>
        <button type="button" class="btn-ghost" id="push-test" hidden>{{ $lang === 'en' ? 'Send test notification' : 'Pošalji probno obaveštenje' }}</button>
      </div>
      <div class="status" id="push-status"></div>
    </div>

    <div class="card">
      <h2>{{ $lang === 'en' ? 'Import from CSV/Excel' : 'Uvoz iz CSV/Excel fajla' }}</h2>
      <p class="hint">
        {{ $lang === 'en'
          ? 'Export your data from another budgeting app or your bank as CSV or Excel (.xls/.xlsx), then map its columns here. Works with any file — you choose which column is which.'
          : 'Izvezi podatke iz druge budžet aplikacije ili banke kao CSV ili Excel (.xls/.xlsx), pa ovde mapiraj kolone. Radi sa bilo kojim fajlom — ti biraš koja kolona je šta.' }}
      </p>
      <input type="file" id="import-file" accept=".csv,text/csv,.xls,.xlsx,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">

      <div id="import-raw-preview-wrap" hidden style="margin-top:12px;">
        <p class="hint">
          {{ $lang === 'en'
            ? 'Some bank exports have a few rows of logo/account info before the real table. Check the row numbers below and adjust if the columns look wrong.'
            : 'Neki izvodi iz banke imaju par redova sa logom/brojem računa pre prave tabele. Proveri brojeve redova ispod i podesi ako kolone izgledaju pogrešno.' }}
        </p>
        <p class="hint" id="import-raw-preview-count"></p>
        <div id="import-raw-preview" style="overflow-x:auto; max-height:220px; overflow-y:auto;"></div>
        <div class="row" style="margin-top:8px;">
          <span class="hint" style="margin:0;">{{ $lang === 'en' ? 'Data starts at row' : 'Podaci počinju od reda' }}</span>
          <input type="number" id="skip-rows" min="1" style="max-width:90px;">
        </div>
      </div>

      <div id="import-mapping" hidden style="margin-top:14px;">
        <div class="row"><span class="hint" style="margin:0;">{{ $lang === 'en' ? 'Date column' : 'Kolona datuma' }}</span><select id="map-date"></select></div>
        <div class="row"><span class="hint" style="margin:0;">{{ $lang === 'en' ? 'Name/description column' : 'Kolona naziva/opisa' }}</span><select id="map-name"></select></div>
        <div class="row"><span class="hint" style="margin:0;">{{ $lang === 'en' ? 'Amount column' : 'Kolona iznosa' }}</span><select id="map-amount"></select></div>
        <div class="row"><span class="hint" style="margin:0;">{{ $lang === 'en' ? 'Currency column (optional)' : 'Kolona valute (opciono)' }}</span><select id="map-currency"></select></div>
        <div class="row"><span class="hint" style="margin:0;">{{ $lang === 'en' ? 'Category column (optional)' : 'Kolona kategorije (opciono)' }}</span><select id="map-category"></select></div>
        <div class="row"><span class="hint" style="margin:0;">{{ $lang === 'en' ? 'Date format' : 'Format datuma' }}</span>
          <select id="date-format">
            <option value="Y-m-d">GGGG-MM-DD (2026-07-31)</option>
            <option value="d/m/Y">DD/MM/GGGG (31/07/2026)</option>
            <option value="m/d/Y">MM/DD/GGGG (07/31/2026)</option>
            <option value="d.m.Y">DD.MM.GGGG (31.07.2026)</option>
          </select>
        </div>
        <div class="row"><span class="hint" style="margin:0;">{{ $lang === 'en' ? 'Default currency' : 'Podrazumevana valuta' }}</span>
          <select id="default-currency"><option>RSD</option><option>EUR</option><option>USD</option></select>
        </div>
        <div class="row"><span class="hint" style="margin:0;">{{ $lang === 'en' ? 'Income vs. expense' : 'Primanje ili trošak' }}</span>
          <select id="kind-mode">
            <option value="sign">{{ $lang === 'en' ? 'From amount sign (− expense, + income)' : 'Iz predznaka iznosa (− trošak, + primanje)' }}</option>
            <option value="fixed">{{ $lang === 'en' ? 'Everything is the same' : 'Sve je isto' }}</option>
          </select>
        </div>
        <div class="row" id="fixed-kind-row" hidden>
          <span class="hint" style="margin:0;">{{ $lang === 'en' ? 'Import everything as' : 'Uvezi sve kao' }}</span>
          <select id="default-kind">
            <option value="expense">{{ $lang === 'en' ? 'Expenses' : 'Troškove' }}</option>
            <option value="income">{{ $lang === 'en' ? 'Income' : 'Primanja' }}</option>
          </select>
        </div>
        <label class="row" style="cursor:pointer;">
          <span class="hint" style="margin:0;">{{ $lang === 'en' ? 'First row is a header' : 'Prvi red je zaglavlje' }}</span>
          <input type="checkbox" id="has-header" checked>
        </label>

        <div id="import-preview-wrap" style="margin-top:12px; overflow-x:auto;"></div>

        <div class="row" style="margin-top:14px;">
          <button type="button" class="btn" id="import-commit-btn">{{ $lang === 'en' ? 'Import' : 'Uvezi' }}</button>
        </div>
      </div>
      <div class="status" id="import-status"></div>
    </div>

    <div class="card danger">
      <h2>{{ $lang === 'en' ? 'Delete account' : 'Obriši nalog' }}</h2>
      <p class="hint">
        {{ $lang === 'en'
          ? 'This permanently deletes your account and every ledger entry you have saved. This cannot be undone.'
          : 'Ovo trajno briše tvoj nalog i sve unose u knjižici koje si sačuvao. Ovo se ne može poništiti.' }}
      </p>
      <form method="POST" action="{{ route('account.delete') }}" class="delete-form" id="delete-account-form">
        @csrf
        @if(auth()->user()->google_id)
          <input type="hidden" name="confirm_text" id="confirm-text-field">
        @else
          <input type="password" name="password" placeholder="{{ $lang === 'en' ? 'Confirm your password' : 'Potvrdi lozinku' }}" required autocomplete="current-password">
        @endif
        <button type="button" class="btn-danger" id="delete-account-btn">{{ $lang === 'en' ? 'Permanently delete' : 'Trajno obriši' }}</button>
        @error('password')
          <div class="status err">{{ $message }}</div>
        @enderror
        @error('confirm_text')
          <div class="status err">{{ $message }}</div>
        @enderror
      </form>
    </div>
  </div>

  <div id="delete-confirm-modal" class="modal-overlay" hidden>
    <div class="modal-card">
      <p style="margin:0 0 12px;">
        {{ $lang === 'en'
          ? 'This permanently deletes your account and every ledger entry you have saved. This cannot be undone.'
          : 'Ovo trajno briše tvoj nalog i sve unose u knjižici koje si sačuvao. Ovo se ne može poništiti.' }}
      </p>
      <p class="hint" style="margin:0 0 8px;">
        {{ $lang === 'en' ? 'Type DELETE to confirm:' : 'Ukucaj DELETE da potvrdiš:' }}
      </p>
      <input type="text" id="delete-confirm-input" autocomplete="off" style="width:100%; margin-bottom:14px;">
      <div style="display:flex; gap:8px; justify-content:flex-end;">
        <button type="button" class="btn-ghost" id="delete-confirm-cancel">{{ $lang === 'en' ? 'Cancel' : 'Otkaži' }}</button>
        <button type="button" class="btn-danger" id="delete-confirm-submit" disabled>{{ $lang === 'en' ? 'Permanently delete' : 'Trajno obriši' }}</button>
      </div>
    </div>
  </div>

  <script>
    (function(){
      var t = {
        enable: {{ Illuminate\Support\Js::from($lang === 'en' ? 'Enable reminder' : 'Uključi podsetnik') }},
        disable: {{ Illuminate\Support\Js::from($lang === 'en' ? 'Disable reminder' : 'Isključi podsetnik') }},
        notSupported: {{ Illuminate\Support\Js::from($lang === 'en' ? 'Not supported on this browser/device.' : 'Nije podržano na ovom browseru/uređaju.') }},
        permissionDenied: {{ Illuminate\Support\Js::from($lang === 'en' ? 'Notification permission was denied.' : 'Dozvola za notifikacije je odbijena.') }},
        enabled: {{ Illuminate\Support\Js::from($lang === 'en' ? 'Reminders are on.' : 'Podsetnici su uključeni.') }},
        disabled: {{ Illuminate\Support\Js::from($lang === 'en' ? 'Reminders are off.' : 'Podsetnici su isključeni.') }},
        testSent: {{ Illuminate\Support\Js::from($lang === 'en' ? 'Test sent — check your notifications.' : 'Poslato — proveri notifikacije.') }},
        error: {{ Illuminate\Support\Js::from($lang === 'en' ? 'Something went wrong.' : 'Nešto nije u redu.') }},
      };

      var themeBtn = document.getElementById('theme-toggle');
      function paintThemeBtn(){
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        themeBtn.textContent = isDark
          ? {{ Illuminate\Support\Js::from($lang === 'en' ? 'Switch to light' : 'Prebaci na svetlu') }}
          : {{ Illuminate\Support\Js::from($lang === 'en' ? 'Switch to dark' : 'Prebaci na tamnu') }};
      }
      paintThemeBtn();
      themeBtn.addEventListener('click', function(){
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        var next = isDark ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('theme', next);
        paintThemeBtn();
      });

      var csrfToken = document.querySelector('meta[name="csrf-token"]').content;
      function postJson(url, body){
        return fetch(url, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
          body: JSON.stringify(body || {}),
        }).then(function(res){
          if (!res.ok) throw new Error('request failed');
          return res.json();
        });
      }

      function urlBase64ToUint8Array(base64String){
        var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var rawData = atob(base64);
        return Uint8Array.from([...rawData].map(function(c){ return c.charCodeAt(0); }));
      }

      var pushToggle = document.getElementById('push-toggle');
      var pushTest = document.getElementById('push-test');
      var pushStatus = document.getElementById('push-status');
      var pushSupported = !!(window.isSecureContext && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window);
      var pushEnabled = false;

      function setStatus(text, isError){
        pushStatus.textContent = text || '';
        pushStatus.classList.toggle('err', !!isError);
      }

      function paintPushToggle(){
        pushToggle.textContent = pushEnabled ? t.disable : t.enable;
        pushTest.hidden = !pushEnabled;
      }

      if (!pushSupported) {
        pushToggle.disabled = true;
        pushToggle.textContent = t.enable;
        setStatus(t.notSupported, true);
      } else {
        navigator.serviceWorker.ready.then(function(reg){
          return reg.pushManager.getSubscription();
        }).then(function(sub){
          pushEnabled = !!sub;
          paintPushToggle();
        });

        pushToggle.addEventListener('click', function(){
          pushToggle.disabled = true;
          navigator.serviceWorker.ready.then(function(reg){
            if (pushEnabled) {
              return reg.pushManager.getSubscription().then(function(sub){
                if (!sub) return;
                return postJson('/api/push/unsubscribe', { endpoint: sub.endpoint }).then(function(){ return sub.unsubscribe(); });
              }).then(function(){
                pushEnabled = false;
                setStatus(t.disabled, false);
              });
            }

            return Notification.requestPermission().then(function(permission){
              if (permission !== 'granted') {
                setStatus(t.permissionDenied, true);
                return;
              }
              return fetch('/api/push/public-key').then(function(res){ return res.json(); }).then(function(data){
                if (!data.key) throw new Error('no key');
                return reg.pushManager.subscribe({
                  userVisibleOnly: true,
                  applicationServerKey: urlBase64ToUint8Array(data.key),
                });
              }).then(function(sub){
                return postJson('/api/push/subscribe', sub.toJSON());
              }).then(function(){
                pushEnabled = true;
                setStatus(t.enabled, false);
              });
            });
          }).catch(function(){
            setStatus(t.error, true);
          }).finally(function(){
            pushToggle.disabled = false;
            paintPushToggle();
          });
        });

        pushTest.addEventListener('click', function(){
          pushTest.disabled = true;
          postJson('/api/push/test').then(function(){
            setStatus(t.testSent, false);
          }).catch(function(){
            setStatus(t.error, true);
          }).finally(function(){
            pushTest.disabled = false;
          });
        });
      }
    })();

    (function(){
      var t = {
        selectColumn: {{ Illuminate\Support\Js::from($lang === 'en' ? '— none —' : '— nema —') }},
        readError: {{ Illuminate\Support\Js::from($lang === 'en' ? "Couldn't read that file. Is it a valid CSV or Excel file?" : 'Nisam mogao da pročitam fajl. Da li je validan CSV ili Excel fajl?') }},
        importing: {{ Illuminate\Support\Js::from($lang === 'en' ? 'Importing…' : 'Uvozim…') }},
        importError: {{ Illuminate\Support\Js::from($lang === 'en' ? 'Import failed. Check your column choices and try again.' : 'Uvoz nije uspeo. Proveri izbor kolona i probaj ponovo.') }},
      };

      var skipReasonLabels = {
        invalid_date: {{ Illuminate\Support\Js::from($lang === 'en' ? "date didn't match the chosen format" : 'datum se ne poklapa sa izabranim formatom') }},
        invalid_amount: {{ Illuminate\Support\Js::from($lang === 'en' ? 'amount was empty or zero' : 'iznos je prazan ili nula') }},
        empty_name: {{ Illuminate\Support\Js::from($lang === 'en' ? 'name/description was empty' : 'naziv/opis je prazan') }},
        duplicate: {{ Illuminate\Support\Js::from($lang === 'en' ? 'looked like a duplicate already in that month' : 'delovalo je kao duplikat koji vec postoji u tom mesecu') }},
      };

      var importAnywayLabel = {{ Illuminate\Support\Js::from($lang === 'en' ? 'Import them anyway' : 'Uvezi ih ipak') }};

      var dateFormatLabels = {
        'Y-m-d': 'GGGG-MM-DD', 'd/m/Y': 'DD/MM/GGGG', 'm/d/Y': 'MM/DD/GGGG', 'd.m.Y': 'DD.MM.GGGG',
      };
      function dateFormatAdjustedText(usedFormat) {
        return ({{ Illuminate\Support\Js::from($lang === 'en' ? 'Note: the selected date format did not match the file, so' : 'Napomena: izabrani format datuma se nije poklapao sa fajlom, pa je') }})
          + ' ' + (dateFormatLabels[usedFormat] || usedFormat) + ' '
          + ({{ Illuminate\Support\Js::from($lang === 'en' ? 'was used automatically instead.' : 'automatski korišćen umesto njega.') }});
      }

      function resultText(imported, skipped, months, skipReasons) {
        var monthList = months.length ? months.join(', ') : '—';
        var text = {{ Illuminate\Support\Js::from($lang === 'en' ? 'Imported' : 'Uvezeno') }} + ' ' + imported
          + ' (' + {{ Illuminate\Support\Js::from($lang === 'en' ? 'skipped' : 'preskočeno') }} + ' ' + skipped + ') · '
          + {{ Illuminate\Support\Js::from($lang === 'en' ? 'months' : 'meseci') }} + ': ' + monthList;

        if (skipped > 0 && skipReasons) {
          var reasonParts = Object.keys(skipReasons)
            .filter(function(key){ return skipReasons[key] > 0; })
            .map(function(key){ return skipReasons[key] + ' — ' + skipReasonLabels[key]; });
          if (reasonParts.length) {
            text += '. ' + reasonParts.join(', ');
          }
        }

        return text;
      }

      function duplicateListText(duplicates) {
        return duplicates.map(function(d){
          return '· ' + d.name + ' — ' + d.amount + ' ' + d.currency + ' (' + d.date + ')';
        }).join('\n');
      }

      var csrfToken = document.querySelector('meta[name="csrf-token"]').content;
      var fileInput = document.getElementById('import-file');
      var mappingBox = document.getElementById('import-mapping');
      var importStatus = document.getElementById('import-status');
      var previewWrap = document.getElementById('import-preview-wrap');
      var commitBtn = document.getElementById('import-commit-btn');
      var kindModeSelect = document.getElementById('kind-mode');
      var fixedKindRow = document.getElementById('fixed-kind-row');
      var rawPreviewWrap = document.getElementById('import-raw-preview-wrap');
      var rawPreview = document.getElementById('import-raw-preview');
      var rawPreviewCount = document.getElementById('import-raw-preview-count');
      var skipRowsInput = document.getElementById('skip-rows');

      // Number inputs change value on mouse-wheel scroll in most browsers —
      // easy to trigger by accident while scrolling the raw-preview table
      // right above it (this is exactly what turned an auto-detected 10
      // into 20 during testing). Blur on wheel so scrolling never touches it.
      skipRowsInput.addEventListener('wheel', function(){
        skipRowsInput.blur();
      });

      var columnSelects = {
        date: document.getElementById('map-date'),
        name: document.getElementById('map-name'),
        amount: document.getElementById('map-amount'),
        currency: document.getElementById('map-currency'),
        category: document.getElementById('map-category'),
      };

      kindModeSelect.addEventListener('change', function(){
        fixedKindRow.hidden = kindModeSelect.value !== 'fixed';
      });

      function setImportStatus(text, isError){
        importStatus.textContent = text || '';
        importStatus.classList.toggle('err', !!isError);
      }

      function buildSimpleTable(headerRow, dataRows, rowNumberOffset){
        var table = document.createElement('table');
        table.style.width = '100%';
        table.style.fontSize = '11.5px';
        table.style.borderCollapse = 'collapse';

        if (headerRow) {
          var thead = document.createElement('tr');
          if (rowNumberOffset !== undefined) thead.appendChild(document.createElement('th'));
          headerRow.forEach(function(h){
            var th = document.createElement('th');
            th.textContent = h;
            th.style.textAlign = 'left';
            th.style.padding = '4px 6px';
            th.style.borderBottom = '1px solid var(--border)';
            thead.appendChild(th);
          });
          table.appendChild(thead);
        }

        dataRows.forEach(function(row, i){
          var tr = document.createElement('tr');
          if (rowNumberOffset !== undefined) {
            var rowNumTd = document.createElement('td');
            rowNumTd.textContent = rowNumberOffset + i;
            rowNumTd.style.padding = '4px 6px';
            rowNumTd.style.borderBottom = '1px solid var(--border)';
            rowNumTd.style.color = 'var(--muted, #888)';
            tr.appendChild(rowNumTd);
          }
          row.forEach(function(cell){
            var td = document.createElement('td');
            td.textContent = cell;
            td.style.padding = '4px 6px';
            td.style.borderBottom = '1px solid var(--border)';
            tr.appendChild(td);
          });
          table.appendChild(tr);
        });

        return table;
      }

      function fetchPreview(explicitSkipRows){
        var file = fileInput.files[0];
        if (!file) return;

        setImportStatus('', false);

        var body = new FormData();
        body.append('file', file);
        if (explicitSkipRows !== undefined && explicitSkipRows !== null && explicitSkipRows !== '') {
          body.append('skip_rows', explicitSkipRows);
        }

        fetch('/api/import/preview', {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
          body: body,
        }).then(function(res){
          if (!res.ok) throw new Error('preview failed');
          return res.json();
        }).then(function(data){
          rawPreview.innerHTML = '';
          rawPreview.appendChild(buildSimpleTable(null, data.raw_preview_rows, 1));
          rawPreviewWrap.hidden = false;
          skipRowsInput.value = data.skip_rows + 1;

          var shown = data.raw_preview_rows.length;
          rawPreviewCount.textContent = shown < data.raw_total_rows
            ? ({{ Illuminate\Support\Js::from($lang === 'en' ? 'Showing the first' : 'Prikazano prvih') }}
                + ' ' + shown + ' ' + ({{ Illuminate\Support\Js::from($lang === 'en' ? 'of' : 'od') }})
                + ' ' + data.raw_total_rows
                + ' ' + ({{ Illuminate\Support\Js::from($lang === 'en' ? 'rows — the entire file is still imported.' : 'redova — ceo fajl se ipak uvozi.') }}))
            : '';

          Object.keys(columnSelects).forEach(function(key){
            var select = columnSelects[key];
            select.innerHTML = '';
            if (key === 'currency' || key === 'category') {
              var noneOpt = document.createElement('option');
              noneOpt.value = '';
              noneOpt.textContent = t.selectColumn;
              select.appendChild(noneOpt);
            }
            data.headers.forEach(function(header, i){
              var opt = document.createElement('option');
              opt.value = i;
              opt.textContent = header || ('#' + (i + 1));
              select.appendChild(opt);
            });
          });

          previewWrap.innerHTML = '';
          previewWrap.appendChild(buildSimpleTable(data.headers, data.sample_rows));

          if (data.detected_date_column !== null && data.detected_date_column !== undefined) {
            columnSelects.date.value = data.detected_date_column;
          }
          if (data.detected_date_format) {
            document.getElementById('date-format').value = data.detected_date_format;
          }

          mappingBox.hidden = false;
        }).catch(function(){
          setImportStatus(t.readError, true);
        });
      }

      fileInput.addEventListener('change', function(){
        mappingBox.hidden = true;
        rawPreviewWrap.hidden = true;
        fetchPreview();
      });

      skipRowsInput.addEventListener('change', function(){
        var value = parseInt(skipRowsInput.value, 10);
        if (isNaN(value) || value < 1) value = 1;
        fetchPreview(value - 1);
      });

      function buildCommitBody(file, includeDuplicates){
        var body = new FormData();
        body.append('file', file);
        body.append('has_header', document.getElementById('has-header').checked ? '1' : '0');
        body.append('date_column', columnSelects.date.value);
        body.append('name_column', columnSelects.name.value);
        body.append('amount_column', columnSelects.amount.value);
        if (columnSelects.currency.value !== '') body.append('currency_column', columnSelects.currency.value);
        if (columnSelects.category.value !== '') body.append('category_column', columnSelects.category.value);
        body.append('date_format', document.getElementById('date-format').value);
        body.append('default_currency', document.getElementById('default-currency').value);
        body.append('kind_mode', kindModeSelect.value);
        if (kindModeSelect.value === 'fixed') body.append('default_kind', document.getElementById('default-kind').value);
        if (includeDuplicates) body.append('include_duplicates', '1');
        var skipRowsValue = parseInt(skipRowsInput.value, 10);
        body.append('skip_rows', (!isNaN(skipRowsValue) && skipRowsValue >= 1) ? (skipRowsValue - 1) : 0);
        return body;
      }

      function runCommit(file, includeDuplicates){
        commitBtn.disabled = true;
        setImportStatus(t.importing, false);

        return fetch('/api/import/commit', {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
          body: buildCommitBody(file, includeDuplicates),
        }).then(function(res){
          if (!res.ok) throw new Error('import failed');
          return res.json();
        }).then(function(data){
          var text = resultText(data.imported, data.skipped, data.months || [], data.skip_reasons);
          importStatus.textContent = '';
          importStatus.classList.remove('err');
          importStatus.appendChild(document.createTextNode(text));

          if (data.date_format_adjusted) {
            var note = document.createElement('div');
            note.style.marginTop = '6px';
            note.textContent = dateFormatAdjustedText(data.used_date_format);
            importStatus.appendChild(note);
          }

          if (data.duplicates && data.duplicates.length) {
            var details = document.createElement('pre');
            details.style.whiteSpace = 'pre-wrap';
            details.style.margin = '8px 0';
            details.textContent = duplicateListText(data.duplicates);
            importStatus.appendChild(details);

            var anywayBtn = document.createElement('button');
            anywayBtn.type = 'button';
            anywayBtn.className = 'btn';
            anywayBtn.textContent = importAnywayLabel;
            anywayBtn.addEventListener('click', function(){
              runCommit(file, true);
            });
            importStatus.appendChild(anywayBtn);
          }
        }).catch(function(){
          setImportStatus(t.importError, true);
        }).finally(function(){
          commitBtn.disabled = false;
        });
      }

      commitBtn.addEventListener('click', function(){
        var file = fileInput.files[0];
        if (!file) return;

        runCommit(file, false);
      });
    })();

    (function(){
      var deleteBtn = document.getElementById('delete-account-btn');
      var modal = document.getElementById('delete-confirm-modal');
      var input = document.getElementById('delete-confirm-input');
      var cancelBtn = document.getElementById('delete-confirm-cancel');
      var submitBtn = document.getElementById('delete-confirm-submit');
      var form = document.getElementById('delete-account-form');
      var hiddenConfirmText = document.getElementById('confirm-text-field');
      if (!deleteBtn) return;

      function openModal(){
        input.value = '';
        submitBtn.disabled = true;
        modal.hidden = false;
        input.focus();
      }
      function closeModal(){
        modal.hidden = true;
      }

      deleteBtn.addEventListener('click', openModal);
      cancelBtn.addEventListener('click', closeModal);
      input.addEventListener('input', function(){
        submitBtn.disabled = input.value !== 'DELETE';
      });
      submitBtn.addEventListener('click', function(){
        if (input.value !== 'DELETE') return;
        if (hiddenConfirmText) hiddenConfirmText.value = input.value;
        form.submit();
      });
    })();

    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('/sw.js').catch(() => {});
    }
  </script>
</body>
</html>
