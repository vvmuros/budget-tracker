<!DOCTYPE html>
<html lang="sr">
<head>
<meta charset="UTF-8">
<title>Registracija — Knjižica troškova</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=EB+Garamond:ital,wght@0,400;0,600;1,400&family=IM+Fell+English+SC&family=Cinzel:wght@600;900&display=swap" rel="stylesheet">
<style>
  :root{
    --leather:#2E1B14; --leather-hi:#4A2A1E;
    --parchment:#EFE1BE; --ink:#3B2A18; --ink-light:#7A6440;
    --gilt:#B8892B; --seal:#7A1F1F;
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
  }
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
    <h1>Knjižica troškova</h1>
    <div class="sub">otvori novu ličnu evidenciju</div>

    @if ($errors->any())
      <div class="err">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('register') }}">
      @csrf
      <label>Ime</label>
      <input type="text" name="name" value="{{ old('name') }}" required autofocus>
      <label>Email</label>
      <input type="email" name="email" value="{{ old('email') }}" required>
      <label>Lozinka</label>
      <input type="password" name="password" required>
      <label>Ponovi lozinku</label>
      <input type="password" name="password_confirmation" required>
      <button type="submit">Registruj se</button>
    </form>
    <div class="foot">Već imaš nalog? <a href="{{ route('login') }}">Prijavi se</a></div>
  </div>
</body>
</html>
