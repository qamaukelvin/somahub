<style>
  *{box-sizing:border-box;}
  body{font-family:Arial,sans-serif;background:#FBF8F2;color:#1B1B18;margin:0;}
  .wrap{max-width:900px;margin:0 auto;padding:0 20px;}
  .topnav{background:#1B4D3E;color:#fff;position:relative;}
  .navbar{display:flex;justify-content:space-between;align-items:center;padding:14px 20px;}
  .navlinks{display:flex;flex-wrap:wrap;gap:4px 16px;}
  .navlinks a{color:#F7F3E6;text-decoration:none;font-size:0.86rem;padding:4px 0;}
  .navlinks a:hover{text-decoration:underline;}
  .menu-toggle{display:none;background:none;border:none;color:#fff;font-size:1.4rem;cursor:pointer;}
  @media(max-width:760px){
    .navlinks{display:none;flex-direction:column;position:absolute;top:52px;left:0;right:0;background:#1B4D3E;padding:14px 20px;gap:12px;z-index:30;}
    .navlinks.open{display:flex;}
    .menu-toggle{display:block;}
  }
  main.wrap{padding:32px 20px 60px;}
  h1{font-size:1.5rem;margin-bottom:4px;color:#1B4D3E;}
  .sub{color:#666;margin-bottom:28px;}
  .cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;}
  .card-link{display:block;background:#fff;border:1px solid #E2DCC6;border-radius:8px;padding:20px;text-decoration:none;color:inherit;transition:box-shadow .15s;}
  .card-link:hover{box-shadow:0 4px 14px rgba(0,0,0,0.06);}
  .card-link h3{margin:0 0 8px;color:#1B4D3E;font-size:1.05rem;}
  .card-link p{margin:0;font-size:0.85rem;color:#666;}
  .card-link.locked{opacity:0.6;}
  table{width:100%;border-collapse:collapse;background:#fff;}
  th,td{text-align:left;padding:10px 12px;border-bottom:1px solid #E2DCC6;font-size:0.88rem;}
  th{background:#F4F1E6;font-size:0.75rem;text-transform:uppercase;letter-spacing:0.04em;color:#666;}
  .btn{display:inline-block;padding:10px 18px;background:#1B4D3E;color:#fff;border:none;border-radius:4px;font-weight:600;cursor:pointer;text-decoration:none;font-size:0.88rem;}
  .status-new{color:#8C3B2E;font-weight:600;}
</style>
