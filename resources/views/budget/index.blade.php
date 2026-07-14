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
<title>Bilanso</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Manrope:wght@700;800&display=swap" rel="stylesheet">
@vite(['resources/css/app.css', 'resources/js/app.js'])
<style>
  :root{
    --leather:#F3F5F9; --card:#FFFFFF; --gilt:#0D9488; --ink:#161B26; --ink-light:#69718A; --border:rgba(15,23,42,0.09);
  }
  @media (prefers-color-scheme: dark){
    :root:not([data-theme="light"]){
      --leather:#0A0D14; --card:#131826; --gilt:#2DD4BF; --ink:#EAEDF5; --ink-light:#8B93A8; --border:rgba(255,255,255,0.08);
    }
  }
  :root[data-theme="dark"]{
    --leather:#0A0D14; --card:#131826; --gilt:#2DD4BF; --ink:#EAEDF5; --ink-light:#8B93A8; --border:rgba(255,255,255,0.08);
  }
  html,body{ height:100%; margin:0; }
  body{
    padding:36px 14px;
    min-height:100vh;
    background:var(--leather);
    display:flex;
    flex-direction:column;
    align-items:center;
  }
  .user-bar{
    width:100%; max-width:780px; margin-bottom:14px;
    display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;
    font-family:'Inter',sans-serif; color:var(--ink-light); font-size:13px;
  }
  .user-bar span{ word-break:break-word; }
  @media (max-width:480px){
    body{ padding:20px 10px; }
  }
  .user-menu{ position:relative; flex-shrink:0; }
  .user-menu-trigger{
    background:none; border:1px solid var(--border); color:var(--ink-light);
    font-family:'Inter',sans-serif; font-size:12.5px; border-radius:8px;
    padding:6px 10px; cursor:pointer; display:inline-flex; align-items:center; gap:6px;
  }
  .user-menu-trigger:hover{ border-color:var(--gilt); color:var(--gilt); }
  .user-menu-trigger .chevron{ width:10px; height:10px; }
  .user-menu-dropdown{
    position:absolute; right:0; top:calc(100% + 6px); z-index:10;
    background:var(--card); border:1px solid var(--border); border-radius:10px;
    padding:6px; min-width:160px; display:none; flex-direction:column; gap:2px;
    box-shadow:0 12px 30px -10px rgba(0,0,0,0.5);
  }
  .user-menu-dropdown:not([hidden]){ display:flex; }
  .user-menu-dropdown a, .user-menu-dropdown button{
    display:block; width:100%; text-align:left; background:none; border:none;
    color:var(--ink); font-family:'Inter',sans-serif; font-size:13px;
    padding:8px 10px; border-radius:6px; cursor:pointer; text-decoration:none;
  }
  .user-menu-dropdown a:hover, .user-menu-dropdown button:hover{ background:var(--border); }
  .user-menu-dropdown form{ margin:0; }
</style>
</head>
<body>
  <div class="user-bar">
    <span>{{ auth()->user()->name }} ({{ auth()->user()->email }})</span>
    <div class="user-menu">
      <button type="button" class="user-menu-trigger" id="user-menu-trigger" aria-haspopup="true" aria-expanded="false">
        {{ $lang === 'en' ? 'Account' : 'Nalog' }}
        <svg viewBox="0 0 16 16" class="chevron"><path d="M4 6 L8 10 L12 6" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </button>
      <div class="user-menu-dropdown" id="user-menu-dropdown" hidden>
        <a href="{{ route('settings') }}">{{ $lang === 'en' ? 'Settings' : 'Podešavanja' }}</a>
        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button type="submit">{{ $lang === 'en' ? 'Sign out' : 'Odjavi se' }}</button>
        </form>
      </div>
    </div>
  </div>

  <div id="budget-app"></div>

  <script>
    (function(){
      var trigger = document.getElementById('user-menu-trigger');
      var dropdown = document.getElementById('user-menu-dropdown');
      if (!trigger || !dropdown) return;

      function closeMenu(){
        dropdown.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
      }
      function openMenu(){
        dropdown.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
      }

      trigger.addEventListener('click', function(e){
        e.stopPropagation();
        if (dropdown.hidden) openMenu(); else closeMenu();
      });

      // pointerdown (not click) — click-on-document is unreliable on iOS
      // Safari for taps outside naturally-interactive elements.
      document.addEventListener('pointerdown', function(e){
        if (!dropdown.hidden && !dropdown.contains(e.target) && !trigger.contains(e.target)) {
          closeMenu();
        }
      });

      document.addEventListener('keydown', function(e){
        if (e.key === 'Escape') closeMenu();
      });
    })();
  </script>
</body>
</html>
