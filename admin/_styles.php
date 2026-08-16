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
  @media(max-width:760px){
    .navlinks{display:none;flex-direction:column;position:absolute;top:56px;left:0;right:0;background:var(--teal-deep);padding:16px 20px;gap:14px;z-index:30;}
    .navlinks.open{display:flex;}
    .menu-toggle{display:block;}
  }
  main.wrap{padding:36px 20px 60px;}
  .header-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:26px;flex-wrap:wrap;gap:10px;}
  h1{font-size:1.5rem;margin:0;font-weight:800;color:var(--teal-deep);}
  table{width:100%;border-collapse:collapse;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,0.05);}
  th,td{text-align:left;padding:13px 14px;border-bottom:1px solid var(--line);font-size:0.88rem;}
  th{background:var(--sand);font-size:0.7rem;text-transform:uppercase;letter-spacing:0.04em;color:var(--muted);}
  tr:last-child td{border-bottom:none;}
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
</style>