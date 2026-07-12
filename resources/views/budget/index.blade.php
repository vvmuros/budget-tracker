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
<meta name="theme-color" content="#2E1B14">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
<title>{{ $lang === 'en' ? 'Budget Book — antique edition' : 'Knjižica troškova — antikvarno izdanje' }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=EB+Garamond:ital,wght@0,400;0,600;1,400&family=IM+Fell+English+SC&family=Cinzel:wght@600;900&display=swap" rel="stylesheet">
@vite(['resources/css/app.css', 'resources/js/app.js'])
<style>
  html,body{ height:100%; margin:0; }
  body{
    padding:36px 14px;
    min-height:100vh;
    background:radial-gradient(ellipse at 50% 0%, #4A2A1E 0%, #2E1B14 55%, #1B0F0A 100%);
    display:flex;
    flex-direction:column;
    align-items:center;
  }
  .user-bar{
    width:100%; max-width:780px; margin-bottom:14px;
    display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;
    font-family:'EB Garamond',Georgia,serif; color:#D8AE4C; font-size:13px;
  }
  .user-bar span{ word-break:break-word; }
  .user-bar form{ margin:0; flex-shrink:0; }
  .user-bar button{
    background:none; border:1px solid #B8892B; color:#EFE1BE;
    font-family:Georgia,serif; font-variant:small-caps; font-size:12px;
    padding:6px 14px; cursor:pointer;
  }
  .user-bar button:hover{ background:rgba(184,137,43,0.2); }
  @media (max-width:480px){
    body{ padding:20px 10px; }
  }
  .user-bar-actions{ display:flex; gap:10px; align-items:center; flex-shrink:0; }
  .danger-link{
    background:none; border:none; color:#C6605F; font-family:Georgia,serif;
    font-size:11px; text-decoration:underline; cursor:pointer; padding:0;
  }
  .danger-link:hover{ color:#E08A88; }
  .delete-box{
    width:100%; max-width:780px; margin:0 0 14px 0; padding:14px 16px;
    border:1px solid #7A1F1F; background:rgba(122,31,31,0.12);
    font-family:'EB Garamond',Georgia,serif; color:#EFE1BE; font-size:12.5px;
  }
  .delete-box p{ margin:0 0 10px 0; line-height:1.5; }
  .delete-box form{ display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
  .delete-box input[type=password]{
    font-family:Georgia,serif; font-size:13px; padding:6px 8px; background:rgba(0,0,0,0.2);
    border:1px solid #B8892B; color:#EFE1BE; flex:1; min-width:160px;
  }
  .delete-box button[type=submit]{
    background:#7A1F1F; border:1px solid #9C3232; color:#EFE1BE;
    font-family:Georgia,serif; font-variant:small-caps; font-size:12px; padding:6px 14px; cursor:pointer;
  }
  .delete-box button[type=submit]:hover{ background:#9C3232; }
  .delete-box .cancel-btn{
    background:none; border:1px solid #B8892B; color:#EFE1BE;
    font-family:Georgia,serif; font-variant:small-caps; font-size:12px; padding:6px 14px; cursor:pointer;
  }
  .delete-box .err{ color:#E08A88; font-size:12px; margin-top:8px; width:100%; }
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
