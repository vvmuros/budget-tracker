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

    <div class="card danger">
      <h2>{{ $lang === 'en' ? 'Delete account' : 'Obriši nalog' }}</h2>
      <p class="hint">
        {{ $lang === 'en'
          ? 'This permanently deletes your account and every ledger entry you have saved. This cannot be undone.'
          : 'Ovo trajno briše tvoj nalog i sve unose u knjižici koje si sačuvao. Ovo se ne može poništiti.' }}
      </p>
      <form method="POST" action="{{ route('account.delete') }}" class="delete-form">
        @csrf
        <input type="password" name="password" placeholder="{{ $lang === 'en' ? 'Confirm your password' : 'Potvrdi lozinku' }}" required autocomplete="current-password">
        <button type="submit" class="btn-danger" onclick="return confirm('{{ $lang === 'en' ? 'Are you sure? This cannot be undone.' : 'Da li si siguran? Ovo se ne moze ponistiti.' }}')">{{ $lang === 'en' ? 'Permanently delete' : 'Trajno obriši' }}</button>
        @error('password')
          <div class="status err">{{ $message }}</div>
        @enderror
      </form>
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

    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('/sw.js').catch(() => {});
    }
  </script>
</body>
</html>
