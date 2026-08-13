<style>
  *{box-sizing:border-box;}
  body{font-family:Arial,sans-serif;background:#F4F1E6;color:#1B1B18;margin:0;}
  .wrap{max-width:1000px;margin:0 auto;padding:0 20px;}
  .topnav{background:#1B1B18;color:#fff;position:relative;}
  .navbar{display:flex;justify-content:space-between;align-items:center;padding:14px 20px;}
  .navlinks{display:flex;flex-wrap:wrap;gap:4px 16px;}
  .navlinks a{color:#eee;text-decoration:none;font-size:0.86rem;padding:4px 0;}
  .menu-toggle{display:none;background:none;border:none;color:#fff;font-size:1.4rem;cursor:pointer;}
  @media(max-width:760px){
    .navlinks{display:none;flex-direction:column;position:absolute;top:52px;left:0;right:0;background:#1B1B18;padding:14px 20px;gap:12px;z-index:30;}
    .navlinks.open{display:flex;}
    .menu-toggle{display:block;}
  }
  main.wrap{padding:32px 20px 60px;}
  .header-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:10px;}
  h1{font-size:1.4rem;margin:0;}
  table{width:100%;border-collapse:collapse;background:#fff;border-radius:6px;overflow:hidden;}
  th,td{text-align:left;padding:12px;border-bottom:1px solid #eee;font-size:0.88rem;}
  th{background:#f8f8f5;font-size:0.72rem;text-transform:uppercase;color:#888;}
  .btn{display:inline-block;padding:10px 18px;background:#1B1B18;color:#fff;border:none;border-radius:4px;font-weight:600;cursor:pointer;text-decoration:none;font-size:0.88rem;}
  .plan-badge{font-size:0.72rem;padding:3px 8px;border-radius:10px;background:#eee;}
  .plan-free{background:#eee;color:#666;}
  .plan-paid{background:#DCEFE1;color:#1B4D3E;}
  .plan-promo_paid{background:#FBF0D1;color:#8C6D1F;}
  form.stacked label{display:block;font-size:0.85rem;font-weight:600;margin-bottom:6px;}
  form.stacked input,form.stacked select{width:100%;padding:10px;border:1px solid #ccc;border-radius:4px;margin-bottom:16px;box-sizing:border-box;}

  /* Mobile card-style tables — each row becomes a card, each cell shows its
     column name via data-label. Falls back to normal table above 640px. */
  @media(max-width:640px){
    table, thead, tbody, th, td, tr{display:block;}
    thead{display:none;}
    table{background:none;border-radius:0;overflow:visible;}
    tr{background:#fff;border-radius:8px;margin-bottom:14px;padding:12px 14px;box-shadow:0 1px 3px rgba(0,0,0,0.08);}
    td{border:none;padding:8px 0;display:flex;justify-content:space-between;align-items:center;gap:12px;text-align:right;}
    td[data-label=""]{justify-content:flex-end;}
    td::before{content:attr(data-label);font-weight:700;color:#888;font-size:0.72rem;text-transform:uppercase;text-align:left;}
    td[data-label=""]::before{content:none;}
  }
</style>
