<?php
// Rafiki — Somahub's chat widget. Context-aware: shows different, relevant
// conversation trees depending on where it's included.
//
// Include with, setting these BEFORE the include:
//   $SOMAHUB_CHAT_CONTEXT = 'marketing';  // 'marketing' | 'school' | 'dashboard'
//   $SOMAHUB_CHAT_SCHOOL_NAME = $school['name'];   // only needed for 'school' context
//   $SOMAHUB_CHAT_SCHOOL_SLUG = $school['slug'];   // only needed for 'school' context
//   include __DIR__ . '/_chat_widget.php';
//
// If not set, defaults to 'marketing' context.

$SOMAHUB_WHATSAPP_NUMBER = '254707306888';
$context = $SOMAHUB_CHAT_CONTEXT ?? 'marketing';
$schoolName = $SOMAHUB_CHAT_SCHOOL_NAME ?? '';
$schoolSlug = $SOMAHUB_CHAT_SCHOOL_SLUG ?? '';
?>
<style>
  .sh-chat-bubble{
    position:fixed; bottom:20px; right:20px; z-index:999;
    background:#0F5257; color:#F2A65A; width:58px; height:58px; border-radius:50%;
    display:flex; align-items:center; justify-content:center; border:none; cursor:pointer;
    box-shadow:0 4px 16px rgba(0,0,0,0.25); font-weight:800; font-size:1.3rem;
    font-family:'Manrope',sans-serif; transition:transform .15s;
  }
  .sh-chat-bubble:hover{ transform:scale(1.06); }

  .sh-chat-panel{
    position:fixed; bottom:90px; right:20px; z-index:1000;
    width:340px; max-width:calc(100vw - 32px); max-height:72vh;
    background:#fff; border-radius:16px; box-shadow:0 12px 40px rgba(0,0,0,0.2);
    display:none; flex-direction:column; overflow:hidden;
    font-family:'Manrope',sans-serif;
  }
  .sh-chat-panel.open{ display:flex; }

  .sh-chat-header{
    background:#0F5257; color:#fff; padding:16px 18px; display:flex; align-items:center; gap:10px;
  }
  .sh-chat-avatar{
    width:34px; height:34px; border-radius:50%; background:#F2A65A; color:#0A3A3E;
    display:flex; align-items:center; justify-content:center; font-weight:800; font-size:0.95rem; flex-shrink:0;
  }
  .sh-chat-header-text{ flex:1; }
  .sh-chat-header strong{ font-size:0.95rem; display:block; }
  .sh-chat-header span{ font-size:0.74rem; color:#BFD8D9; }
  .sh-chat-close{ background:none; border:none; color:#fff; font-size:1.3rem; cursor:pointer; line-height:1; }

  .sh-chat-body{ flex:1; overflow-y:auto; padding:16px; background:#F7F2E7; }
  .sh-msg{ display:flex; gap:8px; margin-bottom:14px; align-items:flex-start; }
  .sh-msg .sh-mini-avatar{ width:26px; height:26px; border-radius:50%; background:#F2A65A; color:#0A3A3E; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:0.7rem; flex-shrink:0; }
  .sh-msg .sh-bubble-text{ background:#fff; border-radius:10px; padding:11px 14px; font-size:0.87rem; color:#1C1C16; line-height:1.5; box-shadow:0 1px 3px rgba(0,0,0,0.06); }

  .sh-options{ display:flex; flex-direction:column; gap:8px; margin-bottom:8px; margin-left:34px; }
  .sh-options button{
    background:#fff; border:1.5px solid #0F5257; color:#0F5257; border-radius:20px;
    padding:10px 16px; font-size:0.84rem; font-weight:600; cursor:pointer; text-align:left;
  }
  .sh-options button:hover{ background:#0F5257; color:#fff; }
  .sh-options a.sh-link-btn{
    display:block; background:#0F5257; color:#fff; border-radius:20px;
    padding:10px 16px; font-size:0.84rem; font-weight:700; text-align:center; text-decoration:none;
  }
  .sh-options a.sh-wa-link{ background:#25D366; }

  @media(max-width:480px){
    .sh-chat-panel{ right:16px; bottom:84px; width:calc(100vw - 32px); }
  }
</style>

<button class="sh-chat-bubble" id="shChatToggle" title="Chat with Rafiki">R</button>

<div class="sh-chat-panel" id="shChatPanel">
  <div class="sh-chat-header">
    <div class="sh-chat-avatar">R</div>
    <div class="sh-chat-header-text">
      <strong>Rafiki</strong>
      <span>Somahub's assistant · usually replies instantly</span>
    </div>
    <button class="sh-chat-close" id="shChatClose">&times;</button>
  </div>
  <div class="sh-chat-body" id="shChatBody"></div>
</div>

<script>
(function(){
  const WA_NUMBER = "<?= htmlspecialchars($SOMAHUB_WHATSAPP_NUMBER) ?>";
  const CONTEXT = "<?= htmlspecialchars($context) ?>";
  const SCHOOL_NAME = <?= json_encode($schoolName) ?>;
  const SCHOOL_SLUG = <?= json_encode($schoolSlug) ?>;

  const toggle = document.getElementById('shChatToggle');
  const panel = document.getElementById('shChatPanel');
  const closeBtn = document.getElementById('shChatClose');
  const body = document.getElementById('shChatBody');

  const waLink = (text) => `https://wa.me/${WA_NUMBER}?text=${encodeURIComponent(text)}`;

  // ============================================================
  // MARKETING TREE — for the Somahub homepage. Visitor is likely
  // a prospective school, a curious parent, or someone comparing options.
  // ============================================================
  const marketingTree = {
    root: {
      msg: "Hi! I'm Rafiki, Somahub's assistant. What can I help you with?",
      options: [
        { label: "How does Somahub work?", next: "how" },
        { label: "How much does it cost?", next: "cost" },
        { label: "I want to get my school online", next: "getstarted" },
        { label: "Check my child's results", link: "results-portal.php" },
        { label: "Is my data safe?", next: "privacy" },
        { label: "Something else — talk to a human", wa: "Hi Somahub, I have a question about..." },
      ]
    },
    how: {
      msg: "We build and host a website for your school. You review it, edit it yourself from a simple dashboard, and it goes live on a free somahub.top address — no coding needed.",
      options: [
        { label: "What's included for free?", next: "free" },
        { label: "How long does setup take?", next: "setuptime" },
        { label: "Can I use my own domain?", next: "domain" },
        { label: "Back to menu", next: "root" },
      ]
    },
    free: {
      msg: "The free plan includes a full website (Home, About, Academics, Admissions, Gallery, Contact), a free subdomain, and a self-edit dashboard — forever, at no cost.",
      options: [
        { label: "What does the paid plan add?", next: "cost" },
        { label: "Back to menu", next: "root" },
      ]
    },
    setuptime: {
      msg: "Most schools are live within a few days of sending us their details and a few photos. You can review and tweak everything before it goes public.",
      options: [
        { label: "I want to get started", next: "getstarted" },
        { label: "Back to menu", next: "root" },
      ]
    },
    domain: {
      msg: "Yes — on the paid plan, you can connect your own domain (like yourschool.ac.ke) instead of the free somahub.top subdomain. We handle the setup.",
      options: [
        { label: "Back to menu", next: "root" },
      ]
    },
    cost: {
      msg: "The free plan is KSh 0, always. The paid plan is KSh 2,500 per year (about KSh 625 a term) — it adds online enrollment, results checking, and published fees. Your own domain is a separate add-on. Free for your first term.",
      options: [
        { label: "What about a custom domain?", next: "domainprice" },
        { label: "Can I cancel anytime?", next: "cancel" },
        { label: "I want to get my school online", next: "getstarted" },
        { label: "See the full pricing page", link: "pricing.php" },
        { label: "Back to menu", next: "root" },
      ]
    },
    domainprice: {
      msg: "A budget domain (like yourschool.top) is KSh 900/year all-in. A .co.ke domain is KSh 1,800/year all-in — both include renewal tracking and setup.",
      options: [
        { label: "See the full pricing page", link: "pricing.php" },
        { label: "Back to menu", next: "root" },
      ]
    },
    cancel: {
      msg: "Yes. You can move back to the free plan at any time, no lock-in. There's no penalty for downgrading.",
      options: [
        { label: "Back to menu", next: "root" },
      ]
    },
    getstarted: {
      msg: "The fastest way is to message us your school's details on WhatsApp, or fill in the contact form on our homepage — we'll take it from there.",
      options: [
        { label: "Message us on WhatsApp", wa: "Hi Somahub, I'd like to get my school's website set up." },
        { label: "How are schools verified?", next: "verify" },
        { label: "Back to menu", next: "root" },
      ]
    },
    verify: {
      msg: "Every school on Somahub signs an agreement and uploads a signed, stamped copy for our review before getting a Verified badge — this helps parents trust that the schools they find here are genuine.",
      options: [
        { label: "Back to menu", next: "root" },
      ]
    },
    privacy: {
      msg: "We take this seriously — a child's results are only ever shown to someone who enters both the admission number AND the correct name or date of birth together. There's no way to browse all students on a school's site.",
      options: [
        { label: "Read the full privacy policy", link: "privacy.php" },
        { label: "Back to menu", next: "root" },
      ]
    },
  };

  // ============================================================
  // SCHOOL TREE — for an individual school's own site. Visitor is
  // almost always a parent, guardian, or prospective parent, not
  // someone shopping for a website platform.
  // ============================================================
  const schoolTree = {
    root: {
      msg: `Hi! I'm Rafiki, here to help with ${SCHOOL_NAME || "this school's"} website. What do you need?`,
      options: [
        { label: "Check my child's results", link: `results-check.php?school=${encodeURIComponent(SCHOOL_SLUG)}` },
        { label: "Apply for admission", link: `enrollment-apply.php?school=${encodeURIComponent(SCHOOL_SLUG)}` },
        { label: "See school fees", next: "fees" },
        { label: "Contact the school", next: "contact" },
        { label: "Is this school verified?", next: "verified" },
        { label: "Something isn't working on this site", wa: `Hi Somahub, I'm having trouble with the ${SCHOOL_NAME} website.` },
      ]
    },
    fees: {
      msg: "Fee information, when published, is shown right on the school's site under the Fees section — scroll down or use the menu at the top of the page.",
      options: [
        { label: "Back to menu", next: "root" },
      ]
    },
    contact: {
      msg: "You'll find the school's phone, email, and office hours in the Contact section of their site, near the bottom of the page.",
      options: [
        { label: "Back to menu", next: "root" },
      ]
    },
    verified: {
      msg: "A ✓ Verified badge next to the school's name means Somahub has confirmed this is a genuine, registered school through a signed agreement. If you don't see the badge, that just means verification is still in progress.",
      options: [
        { label: "Back to menu", next: "root" },
      ]
    },
    somahub_pitch: {
      msg: "Somahub builds free websites for schools across Kenya. If you're asking on behalf of your own school, we'd love to help.",
      options: [
        { label: "Tell me more", link: "https://somahub.top" },
        { label: "Back to menu", next: "root" },
      ]
    },
  };

  // ============================================================
  // DASHBOARD TREE — for logged-in school staff using their editor.
  // ============================================================
  const dashboardTree = {
    root: {
      msg: "Hi! I'm Rafiki. Need help using your dashboard?",
      options: [
        { label: "How do I edit a section?", next: "edit" },
        { label: "How do I upload photos?", next: "photos" },
        { label: "How do I add enrollment/results/fees?", next: "paidfeatures" },
        { label: "I forgot my password", next: "password" },
        { label: "How do I get verified?", next: "verify" },
        { label: "Something's broken — talk to support", wa: "Hi Somahub, I'm having a problem in my dashboard." },
      ]
    },
    edit: {
      msg: "Go to Website in your dashboard menu, then tap the pencil icon (or click Edit on desktop) next to any section to change its text and photos.",
      options: [
        { label: "Back to menu", next: "root" },
      ]
    },
    photos: {
      msg: "Inside any section's editor, tap the file upload button next to a photo field and choose an image from your phone or computer. JPG, PNG, or WEBP, up to 5MB.",
      options: [
        { label: "Back to menu", next: "root" },
      ]
    },
    paidfeatures: {
      msg: "Enrollment, Results, and Fees are part of the paid plan. If you don't see them yet, your school may still be on the free plan — message us to upgrade.",
      options: [
        { label: "Upgrade my plan", link: "checkout.php" },
        { label: "Back to menu", next: "root" },
      ]
    },
    password: {
      msg: "Message us your school name and we'll reset it for you right away.",
      options: [
        { label: "Message us on WhatsApp", wa: "Hi Somahub, I forgot my dashboard password. My school is..." },
        { label: "Back to menu", next: "root" },
      ]
    },
    verify: {
      msg: "Go to Verification in your dashboard menu and upload a signed, stamped copy of your Somahub agreement. We'll review it and confirm your Verified badge.",
      options: [
        { label: "Back to menu", next: "root" },
      ]
    },
  };

  const trees = { marketing: marketingTree, school: schoolTree, dashboard: dashboardTree };
  const tree = trees[CONTEXT] || marketingTree;

  function render(nodeKey) {
    const node = tree[nodeKey];
    body.innerHTML = '';

    const msgRow = document.createElement('div');
    msgRow.className = 'sh-msg';
    msgRow.innerHTML = `<div class="sh-mini-avatar">R</div><div class="sh-bubble-text"></div>`;
    msgRow.querySelector('.sh-bubble-text').textContent = node.msg;
    body.appendChild(msgRow);

    const optsEl = document.createElement('div');
    optsEl.className = 'sh-options';

    node.options.forEach(opt => {
      if (opt.wa) {
        const a = document.createElement('a');
        a.className = 'sh-link-btn sh-wa-link';
        a.href = waLink(opt.wa);
        a.target = '_blank';
        a.rel = 'noopener';
        a.textContent = '💬 ' + opt.label;
        optsEl.appendChild(a);
      } else if (opt.link) {
        const a = document.createElement('a');
        a.className = 'sh-link-btn';
        a.href = opt.link;
        a.textContent = opt.label;
        optsEl.appendChild(a);
      } else {
        const btn = document.createElement('button');
        btn.textContent = opt.label;
        btn.onclick = () => render(opt.next);
        optsEl.appendChild(btn);
      }
    });

    body.appendChild(optsEl);
    body.scrollTop = 0;
  }

  toggle.addEventListener('click', () => {
    panel.classList.toggle('open');
    if (panel.classList.contains('open') && body.innerHTML === '') {
      render('root');
    }
  });
  closeBtn.addEventListener('click', () => panel.classList.remove('open'));
})();
</script>
