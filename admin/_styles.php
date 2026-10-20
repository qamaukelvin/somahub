<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
  :root{ --teal:#0F5257; --teal-deep:#0A3A3E; --amber:#F2A65A; --sand:#F7F2E7; --ink:#1C1C16; --muted:#6E6A5C; --line:#E5DFCC; }
  *{box-sizing:border-box;}
  body{font-family:'Manrope',sans-serif;background:var(--sand);color:var(--ink);margin:0;}
  .wrap{max-width:1080px;margin:0 auto;padding:0 20px;}
  .topnav{background:var(--teal-deep);color:#fff;position:relative;}
  .navbar{display:flex;justify-content:space-between;align-items:center;padding:16px 20px;}
  .navbar strong{font-weight:800;}
  .navlinks{display:flex;flex-wrap:wrap;gap:6px 18px;align-items:center;}
  .navlinks a{color:#DCEAEA;text-decoration:none;font-size:0.86rem;padding:4px 0;font-weight:600;}
  .navlinks a:hover{color:var(--amber);}
  .menu-toggle{display:none;background:none;border:none;color:#fff;font-size:1.4rem;cursor:pointer;}

  .nav-group{position:relative;}
  .nav-group-label{color:#DCEAEA;font-size:0.86rem;font-weight:600;cursor:pointer;padding:4px 0;}
  .nav-group:hover .nav-group-label{color:var(--amber);}
  .nav-dropdown{display:none;position:absolute;top:100%;left:0;background:var(--teal-deep);flex-direction:column;padding:8px 0;min-width:190px;box-shadow:0 10px 24px rgba(0,0,0,0.25);border-radius:8px;z-index:40;}
  .nav-group:hover .nav-dropdown{display:flex;}
  .nav-dropdown a{padding:9px 16px;}
  @media(max-width:820px){
    .navlinks{display:none;flex-direction:column;position:absolute;top:56px;left:0;right:0;background:var(--teal-deep);padding:16px 20px;gap:14px;z-index:30;align-items:flex-start;}
    .navlinks.open{display:flex;}
    .menu-toggle{display:block;}
    .nav-group{width:100%;}
    .nav-dropdown{display:flex !important;position:static;box-shadow:none;padding:8px 0 0 12px;min-width:0;}
    .nav-dropdown a{padding:6px 0;}
  }
  main.wrap{padding:36px 20px 60px;}
  .header-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:26px;flex-wrap:wrap;gap:10px;}
  h1{font-size:1.5rem;margin:0;font-weight:800;color:var(--teal-deep);}
  table{width:100%;border-collapse:collapse;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,0.05);}
  th,td{text-align:left;padding:13px 14px;border-bottom:1px solid var(--line);font-size:0.88rem;}
  th{background:var(--sand);font-size:0.7rem;text-transform:uppercase;letter-spacing:0.04em;color:var(--muted);}
  tr:last-child td{border-bottom:none;}

  /* Mobile: collapse the table into stacked cards instead of horizontal
     overflow. Each row becomes a card; each cell shows its column label
     (from data-label="...") next to the value, using the attribute the
     markup already sets rather than duplicating the header text as content. */
  @media(max-width:640px){
    table, thead, tbody, th, tr{ display:block; }
    thead{ display:none; }
    table{ background:none; box-shadow:none; border-radius:0; }
    tr{ background:#fff; border-radius:10px; box-shadow:0 1px 4px rgba(0,0,0,0.05); margin-bottom:12px; padding:6px 0; }
    td{ display:flex; justify-content:space-between; align-items:center; gap:12px; border-bottom:1px solid var(--line); padding:10px 14px; text-align:right; }
    tr td:last-child{ border-bottom:none; }
    td::before{
      content: attr(data-label);
      font-size:0.7rem; text-transform:uppercase; letter-spacing:0.04em; color:var(--muted); font-weight:700;
      text-align:left; flex-shrink:0;
    }
  }
  .btn{display:inline-block;padding:10px 18px;background:var(--teal);color:#fff;border:none;border-radius:24px;font-weight:700;cursor:pointer;text-decoration:none;font-size:0.86rem;}
  .btn:hover{background:var(--teal-deep);}
  .btn.danger{background:#C0392B;}
  .btn.danger:hover{background:#9C2C1F;}
  .btn.secondary{background:#fff;color:var(--teal);border:1.5px solid var(--teal);}
  .plan-badge{font-size:0.72rem;padding:3px 10px;border-radius:20px;background:#eee;font-weight:700;}
  .plan-free{background:#eee;color:#666;}
  .plan-paid{background:#DCEFE1;color:#1B4D3E;}
  .plan-promo_paid{background:#FBF0D1;color:#8C6D1F;}
  .box{background:#fff;border-radius:12px;padding:24px;box-shadow:0 1px 4px rgba(0,0,0,0.05);margin-bottom:20px;}
  form.stacked label{display:block;font-size:0.85rem;font-weight:700;margin-bottom:6px;color:var(--ink);}
  form.stacked input,form.stacked select{width:100%;padding:10px 12px;border:1.5px solid var(--line);border-radius:8px;margin-bottom:16px;box-sizing:border-box;font-family:inherit;}
  form.stacked input:focus,form.stacked select:focus{outline:none;border-color:var(--teal);}

  /* ---- visual refresh ---- */
  .navbar .brand{color:#fff;text-decoration:none;font-weight:800;display:flex;align-items:center;gap:8px;} .navbar .brand .dot{color:var(--amber);} .navbar .brand small{background:rgba(255,255,255,.14);border-radius:99px;padding:2px 9px;font-size:.66rem;letter-spacing:.06em;text-transform:uppercase;}
  .navlinks a.top.active,.nav-group-label.active{color:var(--amber);}
  .nav-dropdown a:hover{background:rgba(255,255,255,.08);}
  main.wrap{padding-top:30px;}
  h1{letter-spacing:-.01em;} h2{color:var(--teal-deep);font-size:1.15rem;}
  @media(min-width:641px){
    table{border:1px solid var(--line);border-radius:14px;box-shadow:0 2px 8px rgba(28,28,22,.05);}
    th{padding:14px 16px;font-weight:800;} td{padding:14px 16px;vertical-align:middle;}
    tr:nth-child(even) td{background:#FCFAF4;} tr:hover td{background:#F2F8F8;} thead tr:hover th{background:var(--sand);}
    td a{color:var(--teal);font-weight:700;text-decoration:none;} td a:hover{text-decoration:underline;}
  }
  td select,select{border-radius:999px;}
  .box{border:1px solid var(--line);}
  .btn{transition:transform .1s,box-shadow .1s;} .btn:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(15,82,87,.25);}
  .btn.danger-link,.link-danger{background:none;border:none;color:#C0392B;font-weight:700;cursor:pointer;padding:0;font:inherit;}
  .bulkbar{display:flex;gap:10px;align-items:center;margin:0 0 12px;flex-wrap:wrap;}
</style>