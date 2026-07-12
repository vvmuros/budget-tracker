<!DOCTYPE html>
<html lang="sr">
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
<title>Knjižica troškova — antikvarno izdanje</title>
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
</style>
</head>
<body>
  <div class="user-bar">
    <span>{{ auth()->user()->name }} ({{ auth()->user()->email }})</span>
    <form method="POST" action="{{ route('logout') }}">
      @csrf
      <button type="submit">Odjavi se</button>
    </form>
  </div>

  <div id="budget-app"></div>
</body>
</html>
