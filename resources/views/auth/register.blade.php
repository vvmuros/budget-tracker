<?php
$lang = request()->cookie('lang', 'sr');
$t = [
    'html_lang' => ['sr' => 'sr', 'en' => 'en'],
    'title' => ['sr' => 'Registracija — Knjižica troškova', 'en' => 'Register — Budget Book'],
    'heading' => ['sr' => 'Knjižica troškova', 'en' => 'Budget Book'],
    'sub' => ['sr' => 'otvori novu ličnu evidenciju', 'en' => 'open a new personal ledger'],
    'name' => ['sr' => 'Ime', 'en' => 'Name'],
    'email' => ['sr' => 'Email', 'en' => 'Email'],
    'password' => ['sr' => 'Lozinka', 'en' => 'Password'],
    'password_confirm' => ['sr' => 'Ponovi lozinku', 'en' => 'Confirm password'],
    'submit' => ['sr' => 'Registruj se', 'en' => 'Register'],
    'has_account' => ['sr' => 'Već imaš nalog?', 'en' => 'Already have an account?'],
    'login_link' => ['sr' => 'Prijavi se', 'en' => 'Log in'],
    'privacy_link' => ['sr' => 'Politika privatnosti', 'en' => 'Privacy policy'],
];
?>
<!DOCTYPE html>
<html lang="{{ $t['html_lang'][$lang] }}">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
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
<title>{{ $t['title'][$lang] }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=EB+Garamond:ital,wght@0,400;0,600;1,400&family=IM+Fell+English+SC&family=Cinzel:wght@600;900&display=swap" rel="stylesheet">
@if (config('services.turnstile.site_key'))
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
@endif
<style>
  :root{
    --leather:#2E1B14; --leather-hi:#4A2A1E;
    --parchment:#EFE1BE; --ink:#3B2A18; --ink-light:#7A6440;
    --gilt:#B8892B; --seal:#7A1F1F;
  }
  @media (prefers-color-scheme: dark){
    :root:not([data-theme="light"]){
      --parchment:#241A12; --ink:#EDE0C8; --ink-light:#B8A588;
      --gilt:#C9A244; --seal:#C6605F;
    }
  }
  :root[data-theme="dark"]{
    --parchment:#241A12; --ink:#EDE0C8; --ink-light:#B8A588;
    --gilt:#C9A244; --seal:#C6605F;
  }
  *{box-sizing:border-box;}
  body{
    margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
    background:radial-gradient(ellipse at 50% 0%, var(--leather-hi) 0%, var(--leather) 55%, #1B0F0A 100%);
    font-family:'EB Garamond',Georgia,serif; color:var(--ink); padding:20px;
  }
  .box{
    width:100%; max-width:380px; background:var(--parchment); border-radius:6px;
    padding:34px 30px; box-shadow:0 30px 60px -20px rgba(0,0,0,0.7), inset 0 0 0 2px rgba(184,137,43,0.35);
    position:relative;
  }
  .lang-switch{ position:absolute; top:12px; right:14px; font-size:11px; }
  .lang-switch a{ color:var(--ink-light); text-decoration:underline; }
  h1{ text-align:center; font-family:'Cinzel',Georgia,serif; font-size:22px; margin:0 0 4px 0; }
  .sub{ text-align:center; font-size:12px; font-style:italic; color:var(--ink-light); margin-bottom:22px; }
  label{ display:block; font-size:11px; text-transform:uppercase; letter-spacing:0.6px; color:var(--ink-light); margin-bottom:4px; font-variant:small-caps; }
  input{
    width:100%; font-family:Georgia,serif; font-size:14px; color:var(--ink);
    background:transparent; border:none; border-bottom:1px solid var(--ink-light);
    padding:6px 2px; margin-bottom:16px;
  }
  input:focus{ outline:none; border-bottom:1px solid var(--gilt); }
  button{
    width:100%; background:none; border:1px solid var(--gilt); color:var(--ink);
    font-family:Georgia,serif; font-variant:small-caps; font-size:13px; padding:10px 14px;
    cursor:pointer; letter-spacing:0.5px; margin-top:6px;
  }
  button:hover{ background:rgba(184,137,43,0.12); }
  .err{ color:var(--seal); font-size:12.5px; margin-bottom:14px; }
  .foot{ text-align:center; margin-top:18px; font-size:12px; }
  .foot a{ color:var(--ink); }
</style>
</head>
<body>
  <div class="box">
    <div class="lang-switch">
      <a href="{{ route('lang.switch', $lang === 'en' ? 'sr' : 'en') }}">{{ $lang === 'en' ? 'SR' : 'EN' }}</a>
    </div>
    <h1>{{ $t['heading'][$lang] }}</h1>
    <div class="sub">{{ $t['sub'][$lang] }}</div>

    @if ($errors->any())
      <div class="err">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('register') }}">
      @csrf
      <label>{{ $t['name'][$lang] }}</label>
      <input type="text" name="name" value="{{ old('name') }}" required autofocus>
      <label>{{ $t['email'][$lang] }}</label>
      <input type="email" name="email" value="{{ old('email') }}" required>
      <label>{{ $t['password'][$lang] }}</label>
      <input type="password" name="password" required>
      <label>{{ $t['password_confirm'][$lang] }}</label>
      <input type="password" name="password_confirmation" required>
      @if (config('services.turnstile.site_key'))
        <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" style="margin-bottom:16px;"></div>
      @endif
      <button type="submit">{{ $t['submit'][$lang] }}</button>
    </form>
    <div class="foot">{{ $t['has_account'][$lang] }} <a href="{{ route('login') }}">{{ $t['login_link'][$lang] }}</a></div>
    <div class="foot" style="margin-top:8px; font-size:11px;"><a href="{{ route('privacy') }}">{{ $t['privacy_link'][$lang] }}</a></div>
  </div>
  <script>
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('/sw.js').catch(() => {});
    }
  </script>
</body>
</html>
