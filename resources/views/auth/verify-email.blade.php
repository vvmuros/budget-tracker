<?php
$lang = request()->cookie('lang', 'sr');
$t = [
    'html_lang' => ['sr' => 'sr', 'en' => 'en'],
    'title' => ['sr' => 'Potvrdi email — Knjižica troškova', 'en' => 'Verify email — Budget Book'],
    'heading' => ['sr' => 'Knjižica troškova', 'en' => 'Budget Book'],
    'sub' => ['sr' => 'potvrdi svoj email', 'en' => 'verify your email'],
    'body' => [
        'sr' => 'Poslali smo link za potvrdu na',
        'en' => "We've sent a verification link to",
    ],
    'body2' => [
        'sr' => 'Klikni na link u mejlu da otključaš svoju knjižicu.',
        'en' => 'Click the link in the email to unlock your ledger.',
    ],
    'resend' => ['sr' => 'Pošalji ponovo', 'en' => 'Resend email'],
    'logout' => ['sr' => 'Odjavi se', 'en' => 'Log out'],
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
    position:relative; text-align:center;
  }
  .lang-switch{ position:absolute; top:12px; right:14px; font-size:11px; }
  .lang-switch a{ color:var(--ink-light); text-decoration:underline; }
  h1{ text-align:center; font-family:'Cinzel',Georgia,serif; font-size:22px; margin:0 0 4px 0; }
  .sub{ text-align:center; font-size:12px; font-style:italic; color:var(--ink-light); margin-bottom:22px; }
  .body-text{ font-size:14px; line-height:1.6; margin-bottom:6px; }
  .body-text strong{ color:var(--gilt); }
  .body-text2{ font-size:12.5px; color:var(--ink-light); margin-bottom:22px; }
  button{
    width:100%; background:none; border:1px solid var(--gilt); color:var(--ink);
    font-family:Georgia,serif; font-variant:small-caps; font-size:13px; padding:10px 14px;
    cursor:pointer; letter-spacing:0.5px; margin-top:6px;
  }
  button:hover{ background:rgba(184,137,43,0.12); }
  .status{ color:var(--pos, #2E5B3E); font-size:12.5px; margin-bottom:14px; }
  .foot{ text-align:center; margin-top:18px; font-size:12px; }
  .foot button{ border:none; text-decoration:underline; color:var(--ink-light); width:auto; padding:0; }
  .foot button:hover{ background:none; color:var(--ink); }
</style>
</head>
<body>
  <div class="box">
    <div class="lang-switch">
      <a href="{{ route('lang.switch', $lang === 'en' ? 'sr' : 'en') }}">{{ $lang === 'en' ? 'SR' : 'EN' }}</a>
    </div>
    <h1>{{ $t['heading'][$lang] }}</h1>
    <div class="sub">{{ $t['sub'][$lang] }}</div>

    @if (session('status'))
      <div class="status">{{ session('status') }}</div>
    @endif

    <div class="body-text">{{ $t['body'][$lang] }} <strong>{{ auth()->user()->email }}</strong>.</div>
    <div class="body-text2">{{ $t['body2'][$lang] }}</div>

    <form method="POST" action="{{ route('verification.send') }}">
      @csrf
      <button type="submit">{{ $t['resend'][$lang] }}</button>
    </form>
    <div class="foot">
      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">{{ $t['logout'][$lang] }}</button>
      </form>
    </div>
  </div>
  <script>
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('/sw.js').catch(() => {});
    }
  </script>
</body>
</html>
