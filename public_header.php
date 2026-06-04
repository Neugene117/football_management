<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
define('APP_BASE', '');
require_once __DIR__ . '/includes/functions.php';

// Guarantee that sample data exists if this is a fresh database
ensure_sample_data();

$settings = load_system_settings();

// 1. Fetch Ticker Matches
$tickerMatches = db_fetch_all("
    SELECT m.*, ht.name AS home_name, at.name AS away_name, mr.home_score, mr.away_score
    FROM matches m
    INNER JOIN teams ht ON ht.id = m.home_team_id
    INNER JOIN teams at ON at.id = m.away_team_id
    LEFT JOIN match_results mr ON mr.match_id = m.id AND mr.status = 'approved'
    WHERE m.status IN ('scheduled', 'in_progress', 'completed', 'cancelled')
    ORDER BY m.match_date DESC, m.match_time DESC LIMIT 6
");

// 2. Fetch Hero counts (needed for Home page hero stats)
$totalTeams = db_table_count('teams');
$totalPlayers = db_table_count('players');
$totalMatches = db_table_count('matches', "status IN ('scheduled', 'in_progress', 'completed', 'cancelled')");
$totalMatchdays = (int) (db_fetch_one("SELECT COUNT(DISTINCT matchday) AS total FROM matches WHERE status IN ('scheduled', 'in_progress', 'completed', 'cancelled')")['total'] ?? 0);

$activeTeams = db_table_count('teams', 'is_active = 1');
$scheduledMatches = db_table_count('matches', "status = 'scheduled'");
$liveMatches = db_table_count('matches', "status = 'in_progress'");
$seasonGoals = (int) (db_fetch_one("SELECT SUM(home_score + away_score) AS total FROM match_results WHERE status = 'approved'")['total'] ?? 0);
if ($seasonGoals <= 0) {
    $seasonGoals = 48; // Fallback default
}

$currentPage = $currentPage ?? 'home';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Rwanda Football Federation Management System</title>
  <style>
  @import url('https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700;800;900&family=Barlow+Condensed:wght@500;600;700;800;900&display=swap');
  *{box-sizing:border-box;margin:0;padding:0}
  :root{
    --org:#F97316;--org-d:#EA580C;--org-l:#FED7AA;--org-xl:#FFF7ED;
    --navy:#0F1F4B;--navy-m:#1E3A8A;
    --white:#fff;--off:#F8FAFC;--gray:#64748B;--gray-l:#E2E8F0;--gray-ll:#F1F5F9;
    --text:#0F172A;--text2:#475569;--text3:#94A3B8;
    --r:6px;--rm:10px;--rl:16px;
  }
  body{font-family:'Barlow',sans-serif;background:#fff;color:var(--text);overflow-x:hidden;font-size:14px;line-height:1.5}
  img{display:block}

  /* NAV */
  nav{background:var(--navy);padding:0 20px;display:flex;align-items:center;justify-content:space-between;height:52px;position:sticky;top:0;z-index:100;border-bottom:2px solid var(--org)}
  .nav-logo{display:flex;align-items:center;gap:8px;text-decoration:none}
  .logo-ball{width:30px;height:30px}
  .logo-text{font-family:'Barlow Condensed',sans-serif;font-size:16px;font-weight:800;color:#fff;letter-spacing:.3px;line-height:1.1}
  .logo-text span{color:var(--org)}
  .nav-links{display:flex;align-items:center;gap:0}
  .nav-links a{color:rgba(255,255,255,.7);text-decoration:none;font-size:12px;font-weight:600;padding:5px 9px;border-radius:var(--r);transition:.18s;letter-spacing:.2px}
  .nav-links a:hover,.nav-links a.active{color:var(--org)}
  .nav-right{display:flex;align-items:center;gap:7px}
  .btn-ln{background:transparent;border:1px solid rgba(255,255,255,.25);color:#fff;padding:5px 12px;border-radius:var(--r);font-size:11px;font-weight:600;cursor:pointer;transition:.18s;font-family:'Barlow',sans-serif}
  .btn-ln:hover{border-color:var(--org);color:var(--org)}
  .btn-rg{background:var(--org);border:none;color:#fff;padding:5px 12px;border-radius:var(--r);font-size:11px;font-weight:700;cursor:pointer;font-family:'Barlow',sans-serif;transition:.18s}
  .btn-rg:hover{background:var(--org-d)}

  /* TICKER */
  .ticker{background:var(--org);padding:5px 0;overflow:hidden;display:flex;align-items:center}
  .ticker-lbl{background:var(--navy);color:#fff;font-size:10px;font-weight:800;padding:2px 12px;white-space:nowrap;letter-spacing:.5px;text-transform:uppercase;flex-shrink:0}
  .ticker-wrap{overflow:hidden;flex:1}
  .ticker-inner{display:flex;animation:tick 30s linear infinite;width:max-content}
  .ticker-item{font-size:11px;font-weight:600;color:#fff;white-space:nowrap;padding:0 22px}
  @keyframes tick{0%{transform:translateX(0)}100%{transform:translateX(-50%)}}

  /* HERO */
  .hero{position:relative;min-height:420px;display:flex;align-items:center;overflow:hidden}
  .hero-img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
  .hero-overlay{position:absolute;inset:0;background:linear-gradient(90deg,rgba(10,20,55,.93) 0%,rgba(10,20,55,.75) 55%,rgba(10,20,55,.2) 100%)}
  .hero-content{position:relative;z-index:2;padding:40px 28px;max-width:500px}
  .hero-badge{display:inline-flex;align-items:center;gap:5px;background:rgba(249,115,22,.18);border:1px solid rgba(249,115,22,.35);color:var(--org);padding:3px 10px;border-radius:20px;font-size:10px;font-weight:700;letter-spacing:.5px;margin-bottom:14px;text-transform:uppercase}
  h1.hero-title{font-family:'Barlow Condensed',sans-serif;font-size:44px;font-weight:900;color:#fff;line-height:.95;margin-bottom:12px;letter-spacing:-1.5px;text-transform:uppercase}
  h1.hero-title span{color:var(--org)}
  .hero-sub{color:rgba(255,255,255,.6);font-size:12px;line-height:1.65;margin-bottom:22px;max-width:360px}
  .hero-btns{display:flex;gap:9px;flex-wrap:wrap}
  .btn-p{background:var(--org);color:#fff;border:none;padding:9px 18px;border-radius:var(--r);font-size:12px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:5px;transition:.18s;font-family:'Barlow',sans-serif}
  .btn-p:hover{background:var(--org-d)}
  .btn-s{background:transparent;color:#fff;border:1px solid rgba(255,255,255,.35);padding:9px 18px;border-radius:var(--r);font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:5px;transition:.18s;font-family:'Barlow',sans-serif}
  .btn-s:hover{border-color:rgba(255,255,255,.7)}
  .hero-stats{position:absolute;right:24px;bottom:24px;z-index:2;display:flex;gap:18px}
  .hst{text-align:center}
  .hst-n{font-family:'Barlow Condensed',sans-serif;font-size:28px;font-weight:900;color:#fff}
  .hst-n span{color:var(--org)}
  .hst-l{font-size:9px;color:rgba(255,255,255,.45);text-transform:uppercase;letter-spacing:.5px}

  /* SECTIONS */
  section{padding:32px 20px}
  .sec-hd{display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:16px}
  .sec-t{font-family:'Barlow Condensed',sans-serif;font-size:20px;font-weight:800;color:var(--text);letter-spacing:-.3px;text-transform:uppercase}
  .sec-t span{color:var(--org)}
  .sec-sub{font-size:11px;color:var(--text3);margin-top:1px}
  .sec-lnk{font-size:11px;color:var(--org);font-weight:700;cursor:pointer;white-space:nowrap}

  /* STATS ROW */
  .stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}
  .stat-card{background:#fff;border:1px solid var(--gray-l);border-radius:var(--rm);padding:12px 14px;display:flex;align-items:center;gap:10px}
  .stat-ic{width:36px;height:36px;border-radius:8px;background:var(--org-xl);display:flex;align-items:center;justify-content:center;flex-shrink:0}
  .stat-ic svg{width:18px;height:18px;fill:none;stroke:var(--org);stroke-width:2}
  .stat-v{font-family:'Barlow Condensed',sans-serif;font-size:22px;font-weight:800;color:var(--text)}
  .stat-n{font-size:10px;color:var(--text3)}

  /* MATCH CARDS */
  .match-card{background:var(--navy);border-radius:var(--rm);overflow:hidden;cursor:pointer;transition:.18s}
  .match-card:hover{transform:translateY(-2px)}
  .mc-top{background:rgba(255,255,255,.05);padding:8px 12px;display:flex;align-items:center;justify-content:space-between}
  .mc-lg{font-size:9px;font-weight:700;color:var(--org);text-transform:uppercase;letter-spacing:.5px}
  .mc-st{font-size:8px;font-weight:700;padding:2px 6px;border-radius:3px;text-transform:uppercase;letter-spacing:.3px}
  .live{background:rgba(34,197,94,.15);color:#4ade80;border:1px solid rgba(34,197,94,.2)}
  .upcoming{background:rgba(249,115,22,.15);color:var(--org);border:1px solid rgba(249,115,22,.2)}
  .finished{background:rgba(148,163,184,.1);color:rgba(255,255,255,.4);border:1px solid rgba(255,255,255,.08)}
  .mc-body{padding:12px}
  .mc-teams{display:flex;align-items:center;gap:6px}
  .mc-team{flex:1;display:flex;flex-direction:column;align-items:center;gap:5px}
  .t-logo{width:40px;height:40px;border-radius:50%;overflow:hidden;border:2px solid rgba(255,255,255,.12);flex-shrink:0}
  .t-logo img{width:100%;height:100%;object-fit:cover}
  .mc-tn{font-size:10px;font-weight:700;color:#fff;text-align:center;line-height:1.2}
  .mc-vs{flex-shrink:0;text-align:center}
  .mc-sc{font-family:'Barlow Condensed',sans-serif;font-size:24px;font-weight:900;color:#fff;line-height:1}
  .mc-vt{font-size:9px;color:rgba(255,255,255,.3);font-weight:600;display:block;text-transform:uppercase}
  .mc-tm{font-size:10px;color:var(--org);font-weight:600;text-align:center}
  .mc-foot{padding:6px 12px;border-top:1px solid rgba(255,255,255,.05);display:flex;align-items:center;justify-content:space-between}
  .mc-venue{font-size:9px;color:rgba(255,255,255,.3);display:flex;align-items:center;gap:3px}
  .mc-btn{font-size:9px;color:var(--org);font-weight:700;cursor:pointer}

  /* TEAMS */
  .teams-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:9px}
  .team-card{background:#fff;border:1px solid var(--gray-l);border-radius:var(--rm);overflow:hidden;cursor:pointer;transition:.18s;position:relative}
  .team-card:hover{border-color:var(--org);transform:translateY(-2px)}
  .tc-cover{height:50px;overflow:hidden;position:relative}
  .tc-cover img{width:100%;height:100%;object-fit:cover}
  .tc-cover-overlay{position:absolute;inset:0;background:rgba(15,31,75,.45)}
  .tc-body{padding:9px 8px;text-align:center}
  .tc-logo{width:38px;height:38px;border-radius:50%;border:2.5px solid #fff;overflow:hidden;margin:-20px auto 5px;position:relative;z-index:1;background:#fff}
  .tc-logo img{width:100%;height:100%;object-fit:cover}
  .tc-name{font-size:11px;font-weight:700;color:var(--text);line-height:1.2;margin-bottom:2px}
  .tc-meta{font-size:9px;color:var(--text3)}
  .tc-badge{display:inline-block;font-size:8px;font-weight:700;padding:1px 5px;border-radius:3px;margin-top:3px;text-transform:uppercase;letter-spacing:.3px}
  .ab{background:rgba(34,197,94,.1);color:#15803d}
  .ib{background:rgba(239,68,68,.1);color:#b91c1c}

  /* STANDINGS */
  .std-wrap{background:var(--navy);border-radius:var(--rl);overflow:hidden}
  .std-hd{padding:12px 16px;display:flex;align-items:center;justify-content:space-between;background:rgba(255,255,255,.04);border-bottom:1px solid rgba(255,255,255,.06)}
  .std-hd-t{font-family:'Barlow Condensed',sans-serif;font-size:15px;font-weight:800;color:#fff;text-transform:uppercase}
  .std-hd-t span{color:var(--org)}
  table.std-tbl{width:100%;border-collapse:collapse}
  .std-tbl th{font-size:9px;font-weight:700;color:rgba(255,255,255,.3);text-transform:uppercase;letter-spacing:.4px;padding:7px 10px;text-align:center;border-bottom:1px solid rgba(255,255,255,.05)}
  .std-tbl th:first-child,.std-tbl th:nth-child(2){text-align:left}
  .std-tbl td{padding:6px 10px;font-size:11px;color:rgba(255,255,255,.75);text-align:center;border-bottom:1px solid rgba(255,255,255,.04)}
  .std-tbl td:first-child{width:22px;font-weight:700;font-size:10px;color:rgba(255,255,255,.35)}
  .std-tbl td:nth-child(2){text-align:left}
  .std-tbl tr:hover td{background:rgba(255,255,255,.03)}
  .std-team{display:flex;align-items:center;gap:7px}
  .std-lg{width:20px;height:20px;border-radius:50%;overflow:hidden;border:1px solid rgba(255,255,255,.1);flex-shrink:0}
  .std-lg img{width:100%;height:100%;object-fit:cover}
  .std-nm{font-size:11px;font-weight:600;color:#fff}
  .pts{font-family:'Barlow Condensed',sans-serif;font-size:13px;font-weight:800;color:var(--org)}
  .r1 td:first-child{color:var(--org)}
  .r2 td:first-child{color:#C0C0C0}
  .r3 td:first-child{color:#CD7F32}
  .fw{display:inline-block;width:15px;height:15px;border-radius:2px;font-size:8px;font-weight:700;background:rgba(34,197,94,.2);color:#4ade80;text-align:center;line-height:15px;margin:1px}
  .fd{display:inline-block;width:15px;height:15px;border-radius:2px;font-size:8px;font-weight:700;background:rgba(234,179,8,.2);color:#facc15;text-align:center;line-height:15px;margin:1px}
  .fl{display:inline-block;width:15px;height:15px;border-radius:2px;font-size:8px;font-weight:700;background:rgba(239,68,68,.15);color:#f87171;text-align:center;line-height:15px;margin:1px}

  /* PITCH SECTION */
  .pitch-section{background:var(--navy);border-radius:var(--rl);overflow:hidden;border:1px solid rgba(255,255,255,.06)}
  .ps-hd{padding:14px 18px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(255,255,255,.07);background:rgba(255,255,255,.03)}
  .ps-match-info{display:flex;flex-direction:column;gap:2px}
  .ps-title{font-family:'Barlow Condensed',sans-serif;font-size:17px;font-weight:800;color:#fff;text-transform:uppercase;letter-spacing:-.2px}
  .ps-title span{color:var(--org)}
  .ps-meta{font-size:10px;color:rgba(255,255,255,.35);display:flex;align-items:center;gap:6px}
  .ps-meta-dot{width:4px;height:4px;border-radius:50%;background:var(--org);display:inline-block}
  .ps-tabs{display:flex;gap:4px;background:rgba(0,0,0,.25);border-radius:6px;padding:3px}
  .ps-tab{font-size:11px;font-weight:600;color:rgba(255,255,255,.45);padding:5px 13px;border-radius:4px;cursor:pointer;transition:.18s;letter-spacing:.2px;white-space:nowrap}
  .ps-tab.active{background:var(--org);color:#fff;box-shadow:0 2px 8px rgba(249,115,22,.35)}
  .ps-tab:hover:not(.active){color:rgba(255,255,255,.75);background:rgba(255,255,255,.07)}
  .ps-body{padding:16px 18px;display:flex;gap:16px;align-items:flex-start}
  .pitch-field{flex:1;min-width:0;position:relative}
  .pitch-field svg{display:block;border-radius:10px;box-shadow:0 8px 32px rgba(0,0,0,.45)}
  .pitch-panel{transition:opacity .22s,transform .22s}
  .pitch-panel.hidden{opacity:0;transform:scale(.98);pointer-events:none;position:absolute;top:0;left:0;width:100%}
  .pitch-info{width:172px;flex-shrink:0;display:flex;flex-direction:column;gap:0}
  .pi-team-hd{display:flex;align-items:center;gap:8px;padding:10px 12px;background:rgba(255,255,255,.04);border-radius:8px;margin-bottom:10px}
  .pi-team-logo{width:30px;height:30px;border-radius:50%;overflow:hidden;border:2px solid rgba(255,255,255,.15);flex-shrink:0}
  .pi-team-logo img{width:100%;height:100%;object-fit:cover}
  .pi-team-label{font-size:12px;font-weight:700;color:#fff;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .pi-formation-badge{font-family:'Barlow Condensed',sans-serif;font-size:11px;font-weight:700;color:var(--org);background:rgba(249,115,22,.12);padding:2px 7px;border-radius:4px;white-space:nowrap;flex-shrink:0}
  .pi-legend{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px;padding:0 2px}
  .pi-legend-item{display:flex;align-items:center;gap:3px;font-size:9px;color:rgba(255,255,255,.4);font-weight:600;letter-spacing:.2px;text-transform:uppercase}
  .pi-legend-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
  .pi-section-lbl{font-size:9px;color:rgba(255,255,255,.3);font-weight:700;letter-spacing:.5px;text-transform:uppercase;padding:0 6px;margin-bottom:4px}
  .pi-pl{display:flex;flex-direction:column;gap:2px}
  .pi-p{display:flex;align-items:center;gap:7px;padding:4px 7px;border-radius:5px;background:rgba(255,255,255,.04);cursor:pointer;transition:.15s}
  .pi-p:hover{background:rgba(255,255,255,.09)}
  .pi-n{width:18px;height:18px;border-radius:50%;font-size:8px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:#fff}
  .pi-pn{font-size:10px;color:rgba(255,255,255,.7);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1}
  .pi-ps{font-size:8px;color:rgba(255,255,255,.3);flex-shrink:0;font-weight:600}
  .bench-div{height:1px;background:rgba(255,255,255,.07);margin:7px 0}
  .bench-lbl{font-size:9px;color:rgba(255,255,255,.25);font-weight:700;letter-spacing:.4px;text-transform:uppercase;padding:0 6px;margin-bottom:4px}

  /* TOP PLAYERS */
  .players-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(148px,1fr));gap:9px}
  .player-card{background:#fff;border:1px solid var(--gray-l);border-radius:var(--rm);cursor:pointer;transition:.18s;text-align:center;position:relative;overflow:hidden}
  .player-card:hover{border-color:var(--org);transform:translateY(-2px)}
  .pc-img{height:80px;overflow:hidden;position:relative}
  .pc-img img{width:100%;height:100%;object-fit:cover;object-position:top}
  .pc-img-ov{position:absolute;inset:0}
  .pc-pos-bar{position:absolute;top:0;left:0;right:0;height:3px}
  .pc-body{padding:8px 10px 10px}
  .pc-avatar-wrap{width:44px;height:44px;border-radius:50%;border:3px solid #fff;overflow:hidden;margin:-22px auto 5px;position:relative;z-index:1}
  .pc-avatar-wrap img{width:100%;height:100%;object-fit:cover;object-position:top}
  .pc-name{font-size:11px;font-weight:700;color:var(--text);margin-bottom:1px}
  .pc-team{font-size:9px;color:var(--text3);margin-bottom:4px}
  .pc-rating{font-family:'Barlow Condensed',sans-serif;font-size:22px;font-weight:900;color:var(--text);margin:2px 0 1px}
  .pc-rating span{font-size:11px;color:var(--text3);font-family:'Barlow',sans-serif;font-weight:400}
  .pc-pos-badge{font-size:8px;font-weight:700;padding:2px 6px;border-radius:3px;text-transform:uppercase;letter-spacing:.3px}
  .gk-b{background:rgba(249,115,22,.1);color:var(--org-d)}
  .def-b{background:rgba(30,58,138,.08);color:var(--navy-m)}
  .mid-b{background:rgba(22,163,74,.1);color:#15803d}
  .fwd-b{background:rgba(220,38,38,.1);color:#b91c1c}
  .pc-bar-wrap{background:var(--gray-ll);border-radius:3px;height:3px;margin-top:5px;overflow:hidden}
  .pc-bar-fill{height:100%;border-radius:3px}

  /* NEWS */
  .news-grid{display:grid;grid-template-columns:2fr 1fr;gap:10px}
  .news-main{border-radius:var(--rm);overflow:hidden;cursor:pointer;transition:.18s;position:relative}
  .news-main:hover{transform:translateY(-2px)}
  .news-main-img{height:180px;position:relative;overflow:hidden}
  .news-main-img img{width:100%;height:100%;object-fit:cover;transition:.3s}
  .news-main:hover .news-main-img img{transform:scale(1.04)}
  .news-img-ov{position:absolute;inset:0;background:linear-gradient(to top,rgba(10,20,55,.92) 0%,rgba(10,20,55,.3) 60%,transparent 100%)}
  .news-img-content{position:absolute;bottom:0;left:0;right:0;padding:14px}
  .news-cat{font-size:8px;font-weight:700;background:var(--org);color:#fff;padding:2px 7px;border-radius:3px;text-transform:uppercase;letter-spacing:.5px;display:inline-block;margin-bottom:5px}
  .news-title{font-size:13px;font-weight:700;color:#fff;line-height:1.3}
  .news-body-pad{padding:9px 12px;background:#fff;border:1px solid var(--gray-l);border-top:none;border-radius:0 0 var(--rm) var(--rm)}
  .news-meta{font-size:9px;color:var(--text3)}
  .news-list{display:flex;flex-direction:column;gap:7px}
  .news-item{background:#fff;border:1px solid var(--gray-l);border-radius:var(--rm);overflow:hidden;cursor:pointer;transition:.18s;display:flex;gap:0}
  .news-item:hover{border-color:var(--org)}
  .ni-img{width:68px;height:70px;flex-shrink:0;overflow:hidden}
  .ni-img img{width:100%;height:100%;object-fit:cover}
  .ni-body{padding:8px 9px;flex:1;min-width:0}
  .ni-cat{font-size:8px;font-weight:700;color:var(--org);text-transform:uppercase;letter-spacing:.3px;margin-bottom:2px}
  .ni-title{font-size:11px;font-weight:700;color:var(--text);line-height:1.3;margin-bottom:2px}
  .ni-meta{font-size:9px;color:var(--text3)}

  /* SEARCH */
  .search-sec{background:var(--navy);border-radius:var(--rl);padding:26px 22px;text-align:center;position:relative;overflow:hidden}
  .ss-bg{position:absolute;inset:0}
  .ss-bg img{width:100%;height:100%;object-fit:cover;opacity:.12}
  .ss-ov{position:absolute;inset:0;background:rgba(15,31,75,.92)}
  .ss-content{position:relative;z-index:1}
  .ss-title{font-family:'Barlow Condensed',sans-serif;font-size:24px;font-weight:900;color:#fff;margin-bottom:5px;text-transform:uppercase}
  .ss-title span{color:var(--org)}
  .ss-sub{font-size:11px;color:rgba(255,255,255,.5);margin-bottom:16px}
  .search-box{display:flex;gap:7px;max-width:420px;margin:0 auto 12px}
  .search-input{flex:1;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);color:#fff;padding:8px 12px;border-radius:var(--r);font-size:12px;font-family:'Barlow',sans-serif;outline:none;transition:.18s}
  .search-input::placeholder{color:rgba(255,255,255,.35)}
  .search-input:focus{background:rgba(255,255,255,.15);border-color:var(--org)}
  .search-btn{background:var(--org);border:none;color:#fff;padding:8px 14px;border-radius:var(--r);font-size:12px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:4px;font-family:'Barlow',sans-serif;white-space:nowrap}
  .search-tags{display:flex;gap:5px;justify-content:center;flex-wrap:wrap}
  .s-tag{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);color:rgba(255,255,255,.55);font-size:10px;font-weight:600;padding:3px 9px;border-radius:20px;cursor:pointer;transition:.15s}
  .s-tag:hover{background:rgba(249,115,22,.2);border-color:rgba(249,115,22,.35);color:var(--org)}

  /* FEATURED MATCH BANNER */
  .feat-banner{position:relative;border-radius:var(--rl);overflow:hidden;height:160px}
  .feat-banner img{width:100%;height:100%;object-fit:cover}
  .feat-ov{position:absolute;inset:0;background:linear-gradient(90deg,rgba(10,20,55,.9) 0%,rgba(10,20,55,.5) 60%,rgba(10,20,55,.1) 100%)}
  .feat-content{position:absolute;inset:0;padding:18px 20px;display:flex;align-items:center;gap:20px}
  .feat-left{flex:1}
  .feat-badge{font-size:9px;font-weight:700;color:var(--org);text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px}
  .feat-title{font-family:'Barlow Condensed',sans-serif;font-size:20px;font-weight:900;color:#fff;margin-bottom:6px;text-transform:uppercase;line-height:1}
  .feat-sub{font-size:10px;color:rgba(255,255,255,.55)}
  .feat-right{display:flex;align-items:center;gap:12px}
  .feat-team{text-align:center}
  .feat-tm-img{width:52px;height:52px;border-radius:50%;border:2px solid rgba(255,255,255,.2);overflow:hidden;margin:0 auto 5px}
  .feat-tm-img img{width:100%;height:100%;object-fit:cover}
  .feat-tm-name{font-size:10px;font-weight:700;color:#fff}
  .feat-vs{font-family:'Barlow Condensed',sans-serif;font-size:18px;font-weight:900;color:rgba(255,255,255,.35)}

  /* GALLERY STRIP */
  .gallery-strip{display:grid;grid-template-columns:2fr 1fr 1fr;gap:8px;height:150px}
  .gallery-strip .g1{border-radius:var(--rm);overflow:hidden;position:relative}
  .gallery-strip .g2,.gallery-strip .g3{border-radius:var(--rm);overflow:hidden;position:relative}
  .gallery-strip img{width:100%;height:100%;object-fit:cover;transition:.3s}
  .gallery-strip .g1:hover img,.gallery-strip .g2:hover img,.gallery-strip .g3:hover img{transform:scale(1.05)}
  .g-ov{position:absolute;inset:0;background:rgba(10,20,55,.3)}
  .g-label{position:absolute;bottom:7px;left:9px;font-size:10px;font-weight:700;color:#fff;text-shadow:0 1px 3px rgba(0,0,0,.5)}

  /* FOOTER */
  footer{background:var(--navy);padding:28px 20px 14px;border-top:3px solid var(--org)}
  .footer-top{display:grid;grid-template-columns:1.4fr 1fr 1fr 1fr;gap:20px;margin-bottom:20px}
  .footer-brand .lbt{display:block;font-family:'Barlow Condensed',sans-serif;font-size:15px;font-weight:800;color:#fff;margin-bottom:5px}
  .footer-brand p{font-size:10px;color:rgba(255,255,255,.4);line-height:1.65;max-width:190px}
  .footer-col h4{font-size:10px;font-weight:700;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.5px;margin-bottom:9px}
  .footer-col a{display:block;font-size:11px;color:rgba(255,255,255,.4);text-decoration:none;margin-bottom:4px;transition:.15s}
  .footer-col a:hover{color:var(--org)}
  .footer-bottom{border-top:1px solid rgba(255,255,255,.07);padding-top:10px;display:flex;align-items:center;justify-content:space-between}
  .footer-copy{font-size:10px;color:rgba(255,255,255,.3)}
  .footer-socials{display:flex;gap:6px}
  .soc{width:26px;height:26px;border-radius:5px;background:rgba(255,255,255,.06);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:.15s}
  .soc:hover{background:var(--org)}
  .soc svg{width:13px;height:13px;fill:rgba(255,255,255,.5)}
  .soc:hover svg{fill:#fff}

  .two-col{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .divider{height:1px;background:var(--gray-ll)}
  </style>
</head>
<body>

<!-- NAV -->
<nav>
  <a class="nav-logo" href="index.php">
    <svg class="logo-ball" viewBox="0 0 30 30" fill="none">
      <circle cx="15" cy="15" r="13" fill="#F97316" stroke="#EA580C" stroke-width="1.2"/>
      <path d="M15 4 L18 9 L23 9 L19.5 12.5 L21 18 L15 14.5 L9 18 L10.5 12.5 L7 9 L12 9 Z" fill="#0F1F4B"/>
      <circle cx="15" cy="15" r="13" fill="none" stroke="rgba(255,255,255,0.25)" stroke-width=".7"/>
    </svg>
    <div class="logo-text">RWANDA<span>FUTBOL</span><br><span style="font-size:8px;opacity:.45;color:#fff;font-weight:500;letter-spacing:.8px">FEDERATION SYSTEM</span></div>
  </a>
  <div class="nav-links">
    <a href="index.php" class="<?= $currentPage === 'home' ? 'active' : ''; ?>">Home</a>
    <a href="matches.php" class="<?= $currentPage === 'matches' ? 'active' : ''; ?>">Matches</a>
    <a href="teams.php" class="<?= $currentPage === 'teams' ? 'active' : ''; ?>">Teams</a>
    <a href="players.php" class="<?= $currentPage === 'players' ? 'active' : ''; ?>">Players</a>
    <a href="standings.php" class="<?= $currentPage === 'standings' ? 'active' : ''; ?>">Standings</a>
    <a href="results.php" class="<?= $currentPage === 'results' ? 'active' : ''; ?>">Results</a>
    <a href="stats.php" class="<?= $currentPage === 'stats' ? 'active' : ''; ?>">Stats</a>
  </div>
  <div class="nav-right">
    <a href="login.php" class="btn-ln" style="display:inline-block; text-align:center; text-decoration:none;">Login</a>
    <a href="login.php" class="btn-rg" style="display:inline-block; text-align:center; text-decoration:none;">Register</a>
  </div>
</nav>

<!-- TICKER -->
<div class="ticker">
  <div class="ticker-lbl">LIVE</div>
  <div class="ticker-wrap">
    <div class="ticker-inner" id="ticker-inner">
      <?php if (empty($tickerMatches)): ?>
        <span class="ticker-item">🟢 No active matches today &nbsp;•&nbsp; Check schedule</span>
      <?php else: ?>
        <?php foreach ($tickerMatches as $tm): 
          $scoreStr = ($tm['status'] === 'in_progress' || $tm['status'] === 'completed') ? " {$tm['home_score']} – {$tm['away_score']}" : " vs";
          $statusStr = $tm['status'] === 'in_progress' ? "LIVE 67'" : ($tm['status'] === 'completed' ? 'FT' : date('d M H:i', strtotime($tm['match_date'] . ' ' . $tm['match_time'])));
        ?>
          <span class="ticker-item"><?= e($tm['home_name']); ?><?= $scoreStr; ?> <?= e($tm['away_name']); ?> &nbsp;•&nbsp; <?= $statusStr; ?></span>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- HERO -->
<?php if ($currentPage === 'home'): ?>
<div class="hero">
  <img class="hero-img" src="https://images.unsplash.com/photo-1522778119026-d647f0596c20?w=1400&q=80" alt="Stadium"/>
  <div class="hero-overlay"></div>
  <div class="hero-content">
    <div class="hero-badge">⚽ Official Federation Platform</div>
    <h1 class="hero-title">Rwanda<br><span>Football</span><br>Management</h1>
    <p class="hero-sub">The official platform for managing teams, players, lineups, match results and federation workflows across Rwandan professional football.</p>
    <div class="hero-btns">
      <a href="login.php" class="btn-p" style="text-decoration:none;">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 3l14 9-14 9V3z"/></svg>
        View Live Match
      </a>
      <a href="login.php" class="btn-s" style="text-decoration:none;">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        Explore Teams
      </a>
    </div>
  </div>
  <div class="hero-stats">
    <div class="hst"><div class="hst-n"><?= $totalTeams; ?><span>+</span></div><div class="hst-l">Teams</div></div>
    <div class="hst"><div class="hst-n"><?= $totalPlayers; ?><span>+</span></div><div class="hst-l">Players</div></div>
    <div class="hst"><div class="hst-n"><?= $totalMatches; ?></div><div class="hst-l">Matches</div></div>
    <div class="hst"><div class="hst-n"><?= $totalMatchdays; ?></div><div class="hst-l">Matchdays</div></div>
  </div>
</div>
<?php else: ?>
<div class="hero" style="min-height: 220px;">
  <img class="hero-img" src="https://images.unsplash.com/photo-1522778119026-d647f0596c20?w=1400&q=80" alt="Stadium"/>
  <div class="hero-overlay" style="background: linear-gradient(90deg, rgba(10,20,55,0.95) 0%, rgba(10,20,55,0.85) 100%);"></div>
  <div class="hero-content" style="padding: 30px 28px;">
    <div class="hero-badge">⚽ Rwanda Premier League</div>
    <h1 class="hero-title" style="font-size: 32px; line-height: 1.1;">
      <?php
        if ($currentPage === 'matches') echo 'Match <span>Fixtures</span>';
        elseif ($currentPage === 'teams') echo 'League <span>Clubs</span>';
        elseif ($currentPage === 'players') echo 'Roster <span>Directory</span>';
        elseif ($currentPage === 'results') echo 'Match <span>Results</span>';
        elseif ($currentPage === 'standings') echo 'League <span>Standings</span>';
        elseif ($currentPage === 'stats') echo 'Season <span>Statistics</span>';
      ?>
    </h1>
    <p class="hero-sub" style="margin-bottom: 0; max-width: 500px;">
      <?php
        if ($currentPage === 'matches') echo 'Explore all upcoming rounds, kickoff times, and stadiums of the season.';
        elseif ($currentPage === 'teams') echo 'View official registered professional clubs, team stadiums, and squads.';
        elseif ($currentPage === 'players') echo 'Search active registered players, jersey numbers, and ratings.';
        elseif ($currentPage === 'results') echo 'Review full-time scores, goal scorers, and match performance stats.';
        elseif ($currentPage === 'standings') echo 'Monitor points, goal difference, team form, and table rankings.';
        elseif ($currentPage === 'stats') echo 'Discover league leaders including top scorers, assists, and ratings.';
      ?>
    </p>
  </div>
</div>
<?php endif; ?>
