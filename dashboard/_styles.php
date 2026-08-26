<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{ --teal:#0F5257; --teal-deep:#0A3A3E; --amber:#F2A65A; --sand:#F7F2E7; --ink:#1C1C16; --muted:#6E6A5C; --line:#E5DFCC; }
  *{box-sizing:border-box;}
  body{font-family:'Manrope',sans-serif;background:var(--sand);color:var(--ink);margin:0;}
  .wrap{max-width:900px;margin:0 auto;padding:0 20px;}
  .topnav{background:var(--teal);color:#fff;position:relative;}
  .navbar{display:flex;justify-content:space-between;align-items:center;padding:14px 20px;}
  .navlinks{display:flex;flex-wrap:wrap;gap:4px 16px;}
  .navlinks a{color:var(--sand);text-decoration:none;font-size:0.86rem;padding:4px 0;}
  .navlinks a:hover{text-decoration:underline;}
  .menu-toggle{display:none;background:none;border:none;color:#fff;font-size:1.4rem;cursor:pointer;}

  .nav-group{position:relative;}
  .nav-group-label{color:var(--sand);font-size:0.86rem;cursor:pointer;padding:4px 0;}
  .nav-group:hover .nav-group-label{text-decoration:underline;}
  .nav-dropdown{display:none;position:absolute;top:100%;left:0;background:var(--teal);flex-direction:column;padding:8px 0;min-width:190px;box-shadow:0 10px 24px rgba(0,0,0,0.25);border-radius:8px;z-index:40;}
  .nav-group:hover .nav-dropdown{display:flex;}
  .nav-dropdown a{padding:9px 16px;}
  @media(max-width:760px){
    .navlinks{display:none;flex-direction:column;position:absolute;top:52px;left:0;right:0;background:var(--teal);padding:14px 20px;gap:12px;z-index:30;align-items:flex-start;}
    .navlinks.open{display:flex;}
    .menu-toggle{display:block;}
    .nav-group{width:100%;}
    .nav-dropdown{display:flex !important;position:static;box-shadow:none;padding:8px 0 0 12px;min-width:0;}
    .nav-dropdown a{padding:6px 0;}
  }
  main.wrap{padding:32px 20px 60px;}
  h1{font-size:1.5rem;margin-bottom:4px;color:var(--teal);}
  .sub{color:#666;margin-bottom:28px;}
  .cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;}
  .card-link{display:block;background:#fff;border:1px solid var(--line);border-radius:8px;padding:20px;text-decoration:none;color:inherit;transition:box-shadow .15s;}
  .card-link:hover{box-shadow:0 4px 14px rgba(0,0,0,0.06);}
  .card-link h3{margin:0 0 8px;color:var(--teal);font-size:1.05rem;}
  .card-link p{margin:0;font-size:0.85rem;color:#666;}
  .card-link.locked{opacity:0.6;}
  table{width:100%;border-collapse:collapse;background:#fff;}
  th,td{text-align:left;padding:10px 12px;border-bottom:1px solid var(--line);font-size:0.88rem;}
  th{background:var(--sand);font-size:0.75rem;text-transform:uppercase;letter-spacing:0.04em;color:#666;}
  .btn{display:inline-block;padding:10px 18px;background:var(--teal);color:#fff;border:none;border-radius:4px;font-weight:600;cursor:pointer;text-decoration:none;font-size:0.88rem;}
  .status-new{color:#8C3B2E;font-weight:600;}

  /* Mobile card-style tables — each row becomes a card, each cell shows its
     column name via data-label. Falls back to normal table above 640px. */
  @media(max-width:640px){
    table, thead, tbody, th, td, tr{display:block;}
    thead{display:none;}
    table{background:none;}
    tr{background:#fff;border:1px solid var(--line);border-radius:8px;margin-bottom:14px;padding:12px 14px;}
    td{border:none;padding:8px 0;display:flex;justify-content:space-between;align-items:center;gap:12px;text-align:right;}
    td[data-label=""]{justify-content:flex-end;}
    td::before{content:attr(data-label);font-weight:700;color:#888;font-size:0.72rem;text-transform:uppercase;text-align:left;}
    td[data-label=""]::before{content:none;}
  }
</style>
