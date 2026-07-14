<?php
$lang = request()->cookie('lang', 'sr');
$t = [
    'html_lang' => ['sr' => 'sr', 'en' => 'en'],
    'title' => ['sr' => 'Zaboravljena šifra — Bilanso', 'en' => 'Forgot password — Bilanso'],
    'heading' => ['sr' => 'Bilanso', 'en' => 'Bilanso'],
    'sub' => ['sr' => 'unesi email za reset lozinke', 'en' => 'enter your email to reset your password'],
    'email' => ['sr' => 'Email', 'en' => 'Email'],
    'submit' => ['sr' => 'Pošalji link za reset', 'en' => 'Send reset link'],
    'back_to_login' => ['sr' => 'Nazad na prijavu', 'en' => 'Back to log in'],
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
<meta name="theme-color" content="#0A0D14">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
<title>{{ $t['title'][$lang] }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Manrope:wght@700;800&display=swap" rel="stylesheet">
<style>
  :root{
    --leather:#F3F5F9; --leather-hi:#FFFFFF;
    --parchment:#FFFFFF; --ink:#161B26; --ink-light:#69718A;
    --gilt:#0D9488; --seal:#DC2626; --pos:#059669; --on-accent:#FFFFFF; --border:rgba(15,23,42,0.09);
  }
  @media (prefers-color-scheme: dark){
    :root:not([data-theme="light"]){
      --leather:#0A0D14; --leather-hi:#131826;
      --parchment:#151A24; --ink:#EAEDF5; --ink-light:#8B93A8;
      --gilt:#2DD4BF; --seal:#F87171; --pos:#34D399; --on-accent:#0B1F1C; --border:rgba(255,255,255,0.08);
    }
  }
  :root[data-theme="dark"]{
    --leather:#0A0D14; --leather-hi:#131826;
    --parchment:#151A24; --ink:#EAEDF5; --ink-light:#8B93A8;
    --gilt:#2DD4BF; --seal:#F87171; --pos:#34D399; --on-accent:#0B1F1C; --border:rgba(255,255,255,0.08);
  }
  *{box-sizing:border-box;}
  body{
    margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
    background:var(--leather);
    font-family:'Inter',system-ui,sans-serif; color:var(--ink); padding:20px;
  }
  .box{
    width:100%; max-width:380px; background:var(--parchment); border-radius:16px;
    padding:34px 30px; box-shadow:0 30px 60px -24px rgba(0,0,0,0.35); border:1px solid var(--border);
    position:relative;
  }
  .lang-switch{ position:absolute; top:12px; right:14px; font-size:11px; }
  .lang-switch a{ color:var(--ink-light); text-decoration:underline; }
  h1{ text-align:center; font-family:'Manrope',sans-serif; font-weight:800; font-size:22px; margin:0 0 4px 0; }
  .sub{ text-align:center; font-size:12.5px; color:var(--ink-light); margin-bottom:22px; }
  label{ display:block; font-size:11px; text-transform:uppercase; letter-spacing:0.05em; color:var(--ink-light); margin-bottom:4px; }
  input{
    width:100%; font-family:'Inter',sans-serif; font-size:14px; color:var(--ink);
    background:transparent; border:none; border-bottom:1px solid var(--border);
    padding:6px 2px; margin-bottom:16px;
  }
  input:focus{ outline:none; border-bottom:1px solid var(--gilt); }
  button{
    width:100%; background:var(--gilt); border:1px solid var(--gilt); color:var(--on-accent);
    font-family:'Inter',sans-serif; font-weight:600; font-size:13.5px; padding:10px 14px;
    cursor:pointer; margin-top:6px; border-radius:10px;
  }
  button:hover{ opacity:0.88; }
  .err{ color:var(--seal); font-size:12.5px; margin-bottom:14px; }
  .status{ color:var(--pos); font-size:12.5px; margin-bottom:14px; }
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
    @if (session('status'))
      <div class="status">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
      @csrf
      <label>{{ $t['email'][$lang] }}</label>
      <input type="email" name="email" value="{{ old('email') }}" required autofocus>
      <button type="submit">{{ $t['submit'][$lang] }}</button>
    </form>
    <div class="foot"><a href="{{ route('login') }}">{{ $t['back_to_login'][$lang] }}</a></div>
  </div>
  <script>
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('/sw.js').catch(() => {});
    }
  </script>
</body>
</html>
