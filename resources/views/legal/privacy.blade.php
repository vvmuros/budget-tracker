<?php
$lang = request()->cookie('lang', 'sr');
$backRoute = auth()->check() ? route('budget.index') : route('login');
?>
<!DOCTYPE html>
<html lang="{{ $lang }}">
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
<title>{{ $lang === 'en' ? 'Privacy policy — Bilanso' : 'Politika privatnosti — Bilanso' }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Manrope:wght@700;800&display=swap" rel="stylesheet">
<style>
  :root{
    --leather:#F3F5F9; --leather-hi:#FFFFFF;
    --parchment:#FFFFFF; --ink:#161B26; --ink-light:#69718A; --gilt:#0D9488; --border:rgba(15,23,42,0.09);
  }
  @media (prefers-color-scheme: dark){
    :root:not([data-theme="light"]){
      --leather:#0A0D14; --leather-hi:#131826;
      --parchment:#151A24; --ink:#EAEDF5; --ink-light:#8B93A8; --gilt:#2DD4BF; --border:rgba(255,255,255,0.08);
    }
  }
  :root[data-theme="dark"]{
    --leather:#0A0D14; --leather-hi:#131826;
    --parchment:#151A24; --ink:#EAEDF5; --ink-light:#8B93A8; --gilt:#2DD4BF; --border:rgba(255,255,255,0.08);
  }
  *{box-sizing:border-box;}
  body{
    margin:0; min-height:100vh; display:flex; justify-content:center;
    background:var(--leather);
    font-family:'Inter',system-ui,sans-serif; color:var(--ink); padding:36px 16px;
  }
  .page{
    width:100%; max-width:680px; background:var(--parchment); border-radius:16px;
    padding:36px 34px 28px; box-shadow:0 30px 60px -24px rgba(0,0,0,0.35); border:1px solid var(--border);
  }
  h1{ font-family:'Manrope',sans-serif; font-weight:800; font-size:24px; margin:0 0 4px 0; }
  .updated{ font-size:12px; color:var(--ink-light); margin-bottom:26px; }
  h2{ font-family:'Manrope',sans-serif; font-weight:700; font-size:15px; margin:26px 0 8px 0; color:var(--gilt); }
  p, li{ font-size:14px; line-height:1.65; }
  ul{ margin:8px 0; padding-left:20px; }
  .note{
    margin-top:28px; padding:12px 14px; border:1px solid var(--border); border-radius:10px;
    font-size:12.5px; color:var(--ink-light);
  }
  .back{ display:inline-block; margin-top:24px; font-size:13px; color:var(--ink); }
</style>
</head>
<body>
  <div class="page">
    @if ($lang === 'en')
      <h1>Privacy policy</h1>
      <div class="updated">Bilanso — personal finance app</div>

      <p>This app stores the financial information you choose to enter (income, expenses, savings, categories) so it can show it back to you across months and years. Here's what we collect, why, and who else sees it.</p>

      <h2>What we collect</h2>
      <ul>
        <li>Account info: your name, email address, and a hashed (never plain-text) password.</li>
        <li>Ledger data: whatever income/expense/savings entries, amounts, currencies, and categories you enter.</li>
        <li>If you use voice input or the receipt-scan feature: the audio recording or receipt photo is sent to Google's Gemini API to be read, and is not permanently stored on our servers afterward.</li>
      </ul>

      <h2>Who else processes it</h2>
      <ul>
        <li><strong>Google Gemini API</strong> — when you use the chat, receipt-scan, or analysis features, the relevant text/image is sent to Google's Gemini API to be parsed, per Google's own API terms.</li>
        <li><strong>Resend</strong> — sends account emails (like password resets) on our behalf.</li>
        <li><strong>Render.com</strong> — hosts the application and the database.</li>
      </ul>
      <p>We don't sell your data or share it with anyone beyond what's needed to run the app.</p>

      <h2>Your choices</h2>
      <p>You can edit or delete any ledger entry yourself at any time from within the app. To request full account deletion or export of your data, contact us using the email associated with support for this app.</p>

      <div class="note">This is a plain-language description of how the app actually handles data, written by the person who built it — not a law firm. If you need a legally binding privacy policy for a specific jurisdiction, have it reviewed by a professional before relying on it.</div>
    @else
      <h1>Politika privatnosti</h1>
      <div class="updated">Bilanso — lična aplikacija za finansije</div>

      <p>Ova aplikacija čuva finansijske podatke koje sam uneseš (prihodi, troškovi, štednja, kategorije) da bi ti ih prikazala kroz mesece i godine. Evo šta prikupljamo, zašto, i ko još to vidi.</p>

      <h2>Šta prikupljamo</h2>
      <ul>
        <li>Podaci naloga: ime, email adresa i heširana (nikad u čitljivom obliku) lozinka.</li>
        <li>Podaci knjižice: unosi prihoda/troškova/štednje, iznosi, valute i kategorije koje uneseš.</li>
        <li>Ako koristiš glasovni unos ili sken računa: audio snimak ili slika računa se šalje Google Gemini API-ju radi čitanja podataka, bez trajnog čuvanja na našim serverima nakon toga.</li>
      </ul>

      <h2>Ko još obrađuje podatke</h2>
      <ul>
        <li><strong>Google Gemini API</strong> — kada koristiš chat, sken računa ili analizu, odgovarajući tekst/slika se šalje Google Gemini API-ju na obradu, po njihovim uslovima korišćenja.</li>
        <li><strong>Resend</strong> — šalje mejlove naloga (npr. reset lozinke) u naše ime.</li>
        <li><strong>Render.com</strong> — hostuje aplikaciju i bazu podataka.</li>
      </ul>
      <p>Ne prodajemo tvoje podatke niti ih delimo sa bilo kim van onoga što je neophodno da aplikacija radi.</p>

      <h2>Tvoje mogućnosti</h2>
      <p>Svaki unos u knjižici možeš sam izmeniti ili obrisati u bilo kom trenutku unutar aplikacije. Za brisanje celog naloga ili izvoz podataka, kontaktiraj nas preko email adrese vezane za podršku ove aplikacije.</p>

      <div class="note">Ovo je jednostavan, iskren opis kako aplikacija zaista radi sa podacima, napisan od strane osobe koja ju je napravila — ne od strane advokatske kancelarije. Ako ti treba pravno obavezujuća politika privatnosti za određenu jurisdikciju, neka je pregleda stručno lice pre nego što se osloniš na nju.</div>
    @endif

    <a class="back" href="{{ $backRoute }}">&larr; {{ $lang === 'en' ? 'Back' : 'Nazad' }}</a>
  </div>
</body>
</html>
