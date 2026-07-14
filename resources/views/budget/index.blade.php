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
    --leather:#F3F5F9; --gilt:#0D9488; --ink-light:#69718A; --border:rgba(15,23,42,0.09);
    --seal:#DC2626; --seal-bg:rgba(220,38,38,0.08); --on-seal:#FFFFFF;
  }
  @media (prefers-color-scheme: dark){
    :root:not([data-theme="light"]){
      --leather:#0A0D14; --gilt:#2DD4BF; --ink-light:#8B93A8; --border:rgba(255,255,255,0.08);
      --seal:#F87171; --seal-bg:rgba(248,113,113,0.1); --on-seal:#2B0A0A;
    }
  }
  :root[data-theme="dark"]{
    --leather:#0A0D14; --gilt:#2DD4BF; --ink-light:#8B93A8; --border:rgba(255,255,255,0.08);
    --seal:#F87171; --seal-bg:rgba(248,113,113,0.1); --on-seal:#2B0A0A;
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
  .user-bar form{ margin:0; flex-shrink:0; }
  .user-bar button{
    background:none; border:1px solid var(--border); color:var(--ink-light);
    font-family:'Inter',sans-serif; font-size:12.5px; border-radius:8px;
    padding:6px 14px; cursor:pointer;
  }
  .user-bar button:hover{ border-color:var(--gilt); color:var(--gilt); }
  @media (max-width:480px){
    body{ padding:20px 10px; }
  }
  .user-bar-actions{ display:flex; gap:10px; align-items:center; flex-shrink:0; }
  .danger-link{
    background:none; border:none; color:var(--seal); font-family:'Inter',sans-serif;
    font-size:11.5px; text-decoration:underline; cursor:pointer; padding:0;
  }
  .danger-link:hover{ opacity:0.8; }
  .delete-box{
    width:100%; max-width:780px; margin:0 0 14px 0; padding:14px 16px;
    border:1px solid var(--seal); background:var(--seal-bg); border-radius:12px;
    font-family:'Inter',sans-serif; color:var(--ink-light); font-size:12.5px;
  }
  .delete-box p{ margin:0 0 10px 0; line-height:1.5; }
  .delete-box form{ display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
  .delete-box input[type=password]{
    font-family:'Inter',sans-serif; font-size:13px; padding:8px 10px; background:rgba(0,0,0,0.15);
    border:1px solid var(--border); color:inherit; flex:1; min-width:160px; border-radius:8px;
  }
  .delete-box button[type=submit]{
    background:var(--seal); border:1px solid var(--seal); color:var(--on-seal);
    font-family:'Inter',sans-serif; font-weight:600; font-size:12.5px; padding:8px 14px; cursor:pointer; border-radius:8px;
  }
  .delete-box button[type=submit]:hover{ opacity:0.85; }
  .delete-box .cancel-btn{
    background:none; border:1px solid var(--border); color:var(--ink-light);
    font-family:'Inter',sans-serif; font-size:12.5px; padding:8px 14px; cursor:pointer; border-radius:8px;
  }
  .delete-box .err{ color:var(--seal); font-size:12px; margin-top:8px; width:100%; }
</style>
</head>
<body>
  <div class="user-bar">
    <span>{{ auth()->user()->name }} ({{ auth()->user()->email }})</span>
    <div class="user-bar-actions">
      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">{{ $lang === 'en' ? 'Log out' : 'Odjavi se' }}</button>
      </form>
      <button type="button" class="danger-link" id="delete-account-toggle">{{ $lang === 'en' ? 'Delete account' : 'Obriši nalog' }}</button>
    </div>
  </div>

  <div class="delete-box" id="delete-account-box" hidden>
    <p>
      {{ $lang === 'en'
        ? 'This permanently deletes your account and every ledger entry you have saved. This cannot be undone.'
        : 'Ovo trajno briše tvoj nalog i sve unose u knjižici koje si sačuvao. Ovo se ne može poništiti.' }}
    </p>
    <form method="POST" action="{{ route('account.delete') }}">
      @csrf
      <input type="password" name="password" placeholder="{{ $lang === 'en' ? 'Confirm your password' : 'Potvrdi lozinku' }}" required autocomplete="current-password">
      <button type="submit" onclick="return confirm('{{ $lang === 'en' ? 'Are you sure? This cannot be undone.' : 'Da li si siguran? Ovo se ne moze ponistiti.' }}')">{{ $lang === 'en' ? 'Permanently delete' : 'Trajno obriši' }}</button>
      <button type="button" class="cancel-btn" id="delete-account-cancel">{{ $lang === 'en' ? 'Cancel' : 'Otkaži' }}</button>
      @error('password')
        <div class="err">{{ $message }}</div>
      @enderror
    </form>
  </div>

  <div id="budget-app"></div>

  <script>
    (function(){
      var toggle = document.getElementById('delete-account-toggle');
      var box = document.getElementById('delete-account-box');
      var cancel = document.getElementById('delete-account-cancel');
      if (toggle && box) {
        toggle.addEventListener('click', function(){ box.hidden = !box.hidden; });
      }
      if (cancel && box) {
        cancel.addEventListener('click', function(){ box.hidden = true; });
      }
    })();
  </script>
</body>
</html>
