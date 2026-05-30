<!-- FOOTER -->
<footer>
  <div class="footer-top">
    <div class="footer-brand">
      <span class="lbt">RWANDA<span style="color:var(--org)">FUTBOL</span></span>
      <p>The official digital platform of the Rwanda Football Federation for managing professional football operations nationwide.</p>
    </div>
    <div class="footer-col"><h4>Quick Links</h4><a href="login.php">Live Matches</a><a href="login.php">Standings</a><a href="login.php">Match Results</a><a href="login.php">Player Stats</a></div>
    <div class="footer-col"><h4>Teams</h4><a href="login.php">All Teams</a><a href="login.php">Team Profiles</a><a href="login.php">Player Profiles</a><a href="login.php">Lineups</a></div>
    <div class="footer-col"><h4>Federation</h4><a href="login.php">About Us</a><a href="login.php">Regulations</a><a href="login.php">News</a><a href="login.php">Contact</a></div>
  </div>
  <div class="footer-bottom">
    <span class="footer-copy">© 2026 Rwanda Football Federation. All rights reserved.</span>
    <div class="footer-socials">
      <div class="soc"><svg viewBox="0 0 24 24"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg></div>
      <div class="soc"><svg viewBox="0 0 24 24"><path d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z"/></svg></div>
      <div class="soc"><svg viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/></svg></div>
      <div class="soc"><svg viewBox="0 0 24 24"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.95-1.95C18.88 4 12 4 12 4s-6.88 0-8.59.47a2.78 2.78 0 0 0-1.95 1.95C1 8.12 1 12 1 12s0 3.88.46 5.58a2.78 2.78 0 0 0 1.95 1.95C5.12 20 12 20 12 20s6.88 0 8.59-.47a2.78 2.78 0 0 0 1.95-1.95C23 15.88 23 12 23 12s0-3.88-.46-5.58z"/><polygon points="9.75 15.02 15.5 12 9.75 8.98 9.75 15.02"/></svg></div>
    </div>
  </div>
</footer>

<script>
let mins = 67;
setInterval(() => {
  mins = Math.min(mins+1,90);
  const s = mins<90 ? mins+"'" : "FT";
  const lm = document.getElementById('live-min');
  const mc = document.getElementById('mc-min');
  if(lm) lm.textContent = s;
  if(mc) mc.textContent = s;
}, 12000);

// Real-time client-side search filters for subviews
window.activeMatchStatusFilter = 'all';
function filterMatches() {
    const q = document.getElementById('match-search').value.toLowerCase();
    document.querySelectorAll('#matches-grid-container > .match-card').forEach(card => {
        const text = card.dataset.search || '';
        const status = card.dataset.status || '';
        const matchesQuery = text.includes(q);
        const matchesStatus = window.activeMatchStatusFilter === 'all' || 
                             (window.activeMatchStatusFilter === 'completed' && status === 'completed') ||
                             (window.activeMatchStatusFilter === 'scheduled' && status === 'scheduled');
        card.style.display = (matchesQuery && matchesStatus) ? '' : 'none';
    });
}
function setStatusFilter(status) {
    window.activeMatchStatusFilter = status;
    document.querySelectorAll('[onclick^="setStatusFilter"]').forEach(btn => {
        const isActive = btn.getAttribute('onclick').includes(status);
        btn.className = isActive ? 'btn-p' : 'btn-s';
        btn.style.cssText = isActive ? 'padding: 8px 16px; font-size: 11px; font-weight: 700;' : 'padding: 8px 16px; font-size: 11px; font-weight: 700; color: var(--text); border-color: var(--gray-l);';
    });
    filterMatches();
}

function filterTeams() {
    const q = document.getElementById('team-search').value.toLowerCase();
    document.querySelectorAll('#teams-grid-container > .team-card').forEach(card => {
        const text = card.dataset.search || '';
        card.style.display = text.includes(q) ? '' : 'none';
    });
}

window.activePlayerPosFilter = 'all';
function filterPlayers() {
    const q = document.getElementById('player-search').value.toLowerCase();
    document.querySelectorAll('#players-grid-container > .player-card').forEach(card => {
        const text = card.dataset.search || '';
        const pos = card.dataset.pos || '';
        const matchesQuery = text.includes(q);
        const matchesPos = window.activePlayerPosFilter === 'all' || pos === window.activePlayerPosFilter;
        card.style.display = (matchesQuery && matchesPos) ? '' : 'none';
    });
}
function setPlayerPosFilter(pos) {
    window.activePlayerPosFilter = pos;
    document.querySelectorAll('[onclick^="setPlayerPosFilter"]').forEach(btn => {
        const isActive = btn.getAttribute('onclick').includes(pos);
        btn.className = isActive ? 'btn-p' : 'btn-s';
        btn.style.cssText = isActive ? 'padding: 8px 14px; font-size: 11px; font-weight: 700;' : 'padding: 8px 14px; font-size: 11px; font-weight: 700; color: var(--text); border-color: var(--gray-l);';
    });
    filterPlayers();
}

function filterResults() {
    const q = document.getElementById('result-search').value.toLowerCase();
    document.querySelectorAll('#results-list-container > .result-row-card').forEach(card => {
        const text = card.dataset.search || '';
        card.style.display = text.includes(q) ? '' : 'none';
    });
}
</script>
</body>
</html>
