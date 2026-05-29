<?php
$myTeamId = (int) (current_user()['entity_id'] ?? 0);
if ($myTeamId === 0) {
    $fullName = current_user()['full_name'] ?? '';
    if ($fullName !== '') {
        $matchedTeam = db_fetch_one("SELECT id FROM teams WHERE coach_name = ? LIMIT 1", 's', [$fullName]);
        if ($matchedTeam) {
            $myTeamId = (int) $matchedTeam['id'];
        }
    }
    
    if ($myTeamId === 0) {
        $firstTeam = db_fetch_one("SELECT id FROM teams LIMIT 1");
        if ($firstTeam) {
            $myTeamId = (int) $firstTeam['id'];
        }
    }

    if ($myTeamId > 0) {
        $userId = (int) (current_user()['id'] ?? 0);
        if ($userId > 0) {
            db_execute("UPDATE users SET entity_id = ? WHERE id = ?", 'ii', [$myTeamId, $userId]);
            if (isset($_SESSION['user'])) {
                $_SESSION['user']['entity_id'] = $myTeamId;
            }
        }
    }
}
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        set_flash('danger', 'Invalid security token.');
        redirect_to('index.php?page=players');
    }

    if (!current_user_can('players.create')) {
        set_flash('danger', 'You do not have permission to register new players.');
        redirect_to('index.php?page=players');
    }

    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $jerseyNumber = $_POST['jersey_number'] !== '' ? (int)$_POST['jersey_number'] : null;
    $dateOfBirth = $_POST['date_of_birth'] !== '' ? $_POST['date_of_birth'] : null;
    $nationality = trim($_POST['nationality'] ?? '');
    $position = $_POST['position'] ?? '';
    $heightCm = $_POST['height_cm'] !== '' ? (int)$_POST['height_cm'] : null;
    $weightKg = $_POST['weight_kg'] !== '' ? (int)$_POST['weight_kg'] : null;
    $preferredFoot = $_POST['preferred_foot'] ?? 'right';
    $biography = trim($_POST['biography'] ?? '');
    $contractStart = $_POST['contract_start'] !== '' ? $_POST['contract_start'] : null;
    $contract_end = $_POST['contract_end'] !== '' ? $_POST['contract_end'] : null;
    $marketValue = $_POST['market_value'] !== '' ? (float)$_POST['market_value'] : null;

    if ($firstName === '' || $lastName === '' || $position === '') {
        set_flash('danger', 'First name, last name, and position are required.');
        redirect_to('index.php?page=players');
    }

    $photoPath = null;
    if (!empty($_FILES['photo_pl']['name'])) {
        list($uploaded, $pathOrError) = upload_file('photo_pl', 'uploads/players');
        if ($uploaded) {
            $photoPath = $pathOrError;
        } else {
            set_flash('danger', 'Photo upload failed: ' . $pathOrError);
            redirect_to('index.php?page=players');
        }
    }

    $sql = "INSERT INTO players (
        team_id, first_name, last_name, photo_pl, jersey_number, 
        date_of_birth, nationality, position, height_cm, weight_kg, 
        preferred_foot, biography, contract_start, contract_end, market_value, status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'inactive')";

    $inserted = db_execute($sql, 'isssisssiissssd', [
        $myTeamId, $firstName, $lastName, $photoPath, $jerseyNumber,
        $dateOfBirth, $nationality, $position, $heightCm, $weightKg,
        $preferredFoot, $biography, $contractStart, $contract_end, $marketValue
    ]);

    if ($inserted) {
        $playerId = db_last_id();
        log_action('player_registered', 'players', 'players', $playerId, null, [
            'name' => $firstName . ' ' . $lastName,
            'team_id' => $myTeamId
        ]);

        $recipientIds = federation_role_user_ids();
        if (empty($recipientIds)) {
            $fedUsers = db_fetch_all("SELECT id FROM users WHERE user_type IN ('federation', 'admin')");
            $recipientIds = array_map(function($u) { return (int)$u['id']; }, $fedUsers);
        }

        $teamInfo = db_fetch_one("SELECT name FROM teams WHERE id = ?", 'i', [$myTeamId]);
        $teamName = $teamInfo['name'] ?? 'My Team';
        $playerName = trim($firstName . ' ' . $lastName);

        foreach ($recipientIds as $recipientId) {
            create_notification(
                $recipientId,
                'approval',
                'Player Registration Pending',
                "New player '{$playerName}' registered by '{$teamName}' is waiting for your approval."
            );
        }

        set_flash('success', "{$playerName} registered successfully! Pending federation approval.");
    } else {
        set_flash('danger', 'Failed to register player.');
    }

    redirect_to('index.php?page=players');
}

$players = $myTeamId > 0
  ? db_fetch_all('SELECT p.*, t.name team_name FROM players p LEFT JOIN teams t ON t.id=p.team_id WHERE p.team_id = ? ORDER BY p.created_at DESC', 'i', [$myTeamId])
  : db_fetch_all('SELECT p.*, t.name team_name FROM players p LEFT JOIN teams t ON t.id=p.team_id ORDER BY p.created_at DESC LIMIT 100');

$goalkeepersCount = 0;
$defendersCount = 0;
$midfieldersCount = 0;
$forwardsCount = 0;

foreach ($players as $p) {
    $pos = strtolower($p['position']);
    if ($pos === 'goalkeeper') $goalkeepersCount++;
    elseif ($pos === 'defender') $defendersCount++;
    elseif ($pos === 'midfielder') $midfieldersCount++;
    elseif ($pos === 'forward') $forwardsCount++;
}
?>

<style>
/* ── Premium Position Cards Styles ── */
.position-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 28px;
}

@media (max-width: 1024px) {
    .position-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 580px) {
    .position-grid {
        grid-template-columns: 1fr;
    }
}

.position-card {
    background: #ffffff;
    border-radius: 16px;
    border: 1px solid rgba(220, 228, 240, 0.8);
    padding: 24px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 180px;
}

.position-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    transition: all 0.3s ease;
}

/* Color schemes for premium feel */
.pos-goalkeeper::before { background: linear-gradient(90deg, #ff9f43, #ff7a00); }
.pos-defender::before { background: linear-gradient(90deg, #00d2fc, #004e92); }
.pos-midfielder::before { background: linear-gradient(90deg, #4ea8de, #5e60ce); }
.pos-forward::before { background: linear-gradient(90deg, #ff4d6d, #c9184a); }

.position-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 30px rgba(11, 31, 58, 0.08);
    border-color: rgba(255, 122, 0, 0.2);
}

.pos-icon-wrap {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    margin-bottom: 12px;
    transition: all 0.3s ease;
}

.pos-goalkeeper .pos-icon-wrap { background: rgba(255, 122, 0, 0.1); color: #ff7a00; }
.pos-defender .pos-icon-wrap { background: rgba(0, 78, 146, 0.1); color: #004e92; }
.pos-midfielder .pos-icon-wrap { background: rgba(94, 96, 206, 0.1); color: #5e60ce; }
.pos-forward .pos-icon-wrap { background: rgba(201, 24, 74, 0.1); color: #c9184a; }

.pos-info h4 {
    font-size: 17px;
    font-weight: 700;
    color: var(--navy-800);
    margin-bottom: 4px;
    letter-spacing: -0.01em;
}

.pos-info p {
    font-size: 28px;
    font-weight: 800;
    color: var(--navy-900);
    line-height: 1;
    margin-bottom: 16px;
    display: flex;
    align-items: baseline;
    gap: 6px;
}

.pos-info p span {
    font-size: 13px;
    font-weight: 500;
    color: var(--muted);
}

.pos-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.btn-card-action {
    height: 36px;
    border-radius: 9px;
    font-size: 12.5px;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    cursor: pointer;
    transition: all 0.22s ease;
    border: 1px solid var(--line);
    background: #fff;
    color: #4a5568;
}

.btn-card-action:hover {
    background: #f7fafc;
    border-color: #cbd5e0;
}

.btn-card-primary {
    background: var(--orange-500);
    color: #fff;
    border-color: var(--orange-500);
}

.btn-card-primary:hover {
    background: var(--orange-600);
    border-color: var(--orange-600);
    color: #fff;
}

/* Glow effects on hover icon */
.position-card:hover .pos-icon-wrap {
    transform: scale(1.08) rotate(5deg);
}

.filter-badge-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 14px;
}

.status-highlight-box {
    background: rgba(255, 122, 0, 0.05);
    border: 1px dashed rgba(255, 122, 0, 0.3);
    border-radius: 10px;
    padding: 12px;
    margin-bottom: 18px;
    font-size: 13px;
    color: var(--navy-800);
    display: none;
    align-items: center;
    gap: 8px;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Dynamic display and close button rules */
#players-sections-wrapper.single-active {
    grid-template-columns: 1fr;
}
.btn-close-section {
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    width: 32px;
    height: 32px;
    background: #f1f5f9 !important;
    color: #64748b !important;
    transition: all 0.2s ease-in-out;
}
.btn-close-section:hover {
    background: #e2e8f0 !important;
    color: #0f172a !important;
    transform: scale(1.08);
}

/* Card border removal & premium shadow adjustment */
.position-card {
    border: none !important;
    box-shadow: 0 6px 20px rgba(11, 31, 58, 0.05) !important;
}
.card {
    border: none !important;
    box-shadow: 0 6px 20px rgba(11, 31, 58, 0.05) !important;
}

/* Premium Form Layout & Styling */
.form-grid-custom {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 18px;
    margin-top: 8px;
}
.form-group-custom {
    display: flex;
    flex-direction: column;
}
.form-group-custom.col-span-3 { grid-column: span 3; }
.form-group-custom.col-span-2 { grid-column: span 2; }
.form-group-custom.col-span-6 { grid-column: span 6; }

.form-group-custom label {
    display: block;
    font-size: 13.5px;
    font-weight: 600;
    color: var(--navy-800);
    margin-bottom: 6px;
}

.form-control-custom {
    width: 100%;
    height: 42px;
    padding: 0 14px;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    background-color: #f8fafc;
    color: #0f172a;
    font-size: 14.5px;
    transition: all 0.22s ease-in-out;
}

.form-control-custom:focus {
    border-color: var(--orange-500);
    background-color: #ffffff;
    box-shadow: 0 0 0 4px rgba(255, 122, 0, 0.12);
    outline: none;
}

textarea.form-control-custom {
    height: auto;
    padding: 12px 14px;
    resize: vertical;
}

@media (max-width: 768px) {
    .form-grid-custom {
        grid-template-columns: 1fr;
    }
    .form-group-custom.col-span-3,
    .form-group-custom.col-span-2,
    .form-group-custom.col-span-6 {
        grid-column: span 1;
    }
}
</style>

<!-- 4 Position Dashboard Cards -->
<div class="position-grid">
    <!-- Goalkeeper Card -->
    <div class="position-card pos-goalkeeper">
        <div>
            <div class="pos-icon-wrap">
                <i class="fa-solid fa-hands-holding"></i>
            </div>
            <div class="pos-info">
                <h4>Goalkeepers</h4>
                <p><?= $goalkeepersCount; ?> <span>players</span></p>
            </div>
        </div>
        <div class="pos-actions">
            <button class="btn-card-action" onclick="filterByPosition('goalkeeper')">
                <i class="fa-solid fa-eye"></i> View
            </button>
            <?php if (current_user_can('players.create')): ?>
                <button class="btn-card-action btn-card-primary" onclick="openAddForm('goalkeeper')">
                    <i class="fa-solid fa-plus"></i> Add
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Defender Card -->
    <div class="position-card pos-defender">
        <div>
            <div class="pos-icon-wrap">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <div class="pos-info">
                <h4>Defenders</h4>
                <p><?= $defendersCount; ?> <span>players</span></p>
            </div>
        </div>
        <div class="pos-actions">
            <button class="btn-card-action" onclick="filterByPosition('defender')">
                <i class="fa-solid fa-eye"></i> View
            </button>
            <?php if (current_user_can('players.create')): ?>
                <button class="btn-card-action btn-card-primary" onclick="openAddForm('defender')">
                    <i class="fa-solid fa-plus"></i> Add
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Midfielder Card -->
    <div class="position-card pos-midfielder">
        <div>
            <div class="pos-icon-wrap">
                <i class="fa-solid fa-arrows-spin"></i>
            </div>
            <div class="pos-info">
                <h4>Midfielders</h4>
                <p><?= $midfieldersCount; ?> <span>players</span></p>
            </div>
        </div>
        <div class="pos-actions">
            <button class="btn-card-action" onclick="filterByPosition('midfielder')">
                <i class="fa-solid fa-eye"></i> View
            </button>
            <?php if (current_user_can('players.create')): ?>
                <button class="btn-card-action btn-card-primary" onclick="openAddForm('midfielder')">
                    <i class="fa-solid fa-plus"></i> Add
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Forward Card -->
    <div class="position-card pos-forward">
        <div>
            <div class="pos-icon-wrap">
                <i class="fa-solid fa-crosshairs"></i>
            </div>
            <div class="pos-info">
                <h4>Forwards</h4>
                <p><?= $forwardsCount; ?> <span>players</span></p>
            </div>
        </div>
        <div class="pos-actions">
            <button class="btn-card-action" onclick="filterByPosition('forward')">
                <i class="fa-solid fa-eye"></i> View
            </button>
            <?php if (current_user_can('players.create')): ?>
                <button class="btn-card-action btn-card-primary" onclick="openAddForm('forward')">
                    <i class="fa-solid fa-plus"></i> Add
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="two-col" id="players-sections-wrapper" style="display: none;">
    <!-- Left Column: Players List -->
    <div class="card" id="players-list-section" style="display: none;">
        <div class="card-head">
            <div class="filter-badge-row" style="width: 100%; display: flex; justify-content: space-between; align-items: center;">
                <h3 id="list-title">All Registered Players</h3>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <button class="btn btn-secondary btn-sm" id="btn-show-all" onclick="filterByPosition('all')" style="display: none;">Show All</button>
                    <button type="button" class="btn-close-section" onclick="closeSection('players-list-section')" style="background: none; border: none; font-size: 16px; cursor: pointer; padding: 0;" title="Close Player List"><i class="fa-solid fa-xmark"></i></button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-wrap">
                <table class="data-table" id="players-table">
                    <thead>
                        <tr>
                            <th>Photo</th>
                            <th>Name</th>
                            <th>Position</th>
                            <th>Jersey #</th>
                            <th>Nationality</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($players)): ?>
                            <tr><td colspan="6"><div class="empty-state">No players registered yet.</div></td></tr>
                        <?php else: ?>
                            <?php foreach ($players as $p): ?>
                                <tr class="player-row" data-position="<?= e(strtolower($p['position'])); ?>">
                                    <td>
                                        <div class="avatar avatar-sm">
                                            <?php if ($p['photo_pl']): ?>
                                                <img src="<?= e(app_url($p['photo_pl'])); ?>" alt="Photo" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">
                                            <?php else: ?>
                                                <div style="width: 32px; height: 32px; border-radius: 50%; background: #edf2f8; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; color: #7b93b0;">
                                                    <?= strtoupper(substr($p['first_name'], 0, 1) . substr($p['last_name'], 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: var(--navy-800);">
                                            <?= e($p['first_name'] . ' ' . $p['last_name']); ?>
                                        </div>
                                    </td>
                                    <td><span class="badge" style="background: rgba(11, 31, 58, 0.05); color: var(--navy-800); text-transform: capitalize;"><?= e($p['position']); ?></span></td>
                                    <td><?= e($p['jersey_number'] ?? '-'); ?></td>
                                    <td><?= e($p['nationality'] ?: '-'); ?></td>
                                    <td>
                                        <?php if ($p['status'] === 'inactive'): ?>
                                            <span class="badge badge-warning">Pending Approval</span>
                                        <?php else: ?>
                                            <?= status_badge($p['status']); ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Right Column: Registration Form -->
    <div class="card" id="add-player-section" style="display: none;">
        <div class="card-head" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
            <h3 id="form-title">Register New Player</h3>
            <button type="button" class="btn-close-section" onclick="closeSection('add-player-section')" style="background: none; border: none; font-size: 16px; cursor: pointer; padding: 0;" title="Close Form"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="card-body">
            <div id="position-notice" class="status-highlight-box">
                <i class="fa-solid fa-circle-info"></i>
                <span>Pre-selected position: <strong id="selected-pos-text" style="text-transform: capitalize;"></strong></span>
            </div>

            <form method="post" enctype="multipart/form-data" class="form-grid-custom">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">

                <div class="form-group-custom col-span-3">
                    <label>First Name *</label>
                    <input type="text" id="input-first-name" name="first_name" required placeholder="e.g. John" class="form-control-custom">
                </div>

                <div class="form-group-custom col-span-3">
                    <label>Last Name *</label>
                    <input type="text" name="last_name" required placeholder="e.g. Smith" class="form-control-custom">
                </div>

                <div class="form-group-custom col-span-3">
                    <label>Position *</label>
                    <select name="position" id="select-position" required class="form-control-custom">
                        <option value="">Select Position</option>
                        <option value="goalkeeper">Goalkeeper</option>
                        <option value="defender">Defender</option>
                        <option value="midfielder">Midfielder</option>
                        <option value="forward">Forward</option>
                    </select>
                </div>

                <div class="form-group-custom col-span-3">
                    <label>Jersey Number</label>
                    <input type="number" name="jersey_number" min="1" max="99" placeholder="e.g. 10" class="form-control-custom">
                </div>

                <div class="form-group-custom col-span-3">
                    <label>Date of Birth</label>
                    <input type="date" name="date_of_birth" class="form-control-custom">
                </div>

                <div class="form-group-custom col-span-3">
                    <label>Nationality</label>
                    <input type="text" name="nationality" placeholder="e.g. Spanish" class="form-control-custom">
                </div>

                <div class="form-group-custom col-span-2">
                    <label>Height (cm)</label>
                    <input type="number" name="height_cm" placeholder="e.g. 182" class="form-control-custom">
                </div>

                <div class="form-group-custom col-span-2">
                    <label>Weight (kg)</label>
                    <input type="number" name="weight_kg" placeholder="e.g. 75" class="form-control-custom">
                </div>

                <div class="form-group-custom col-span-2">
                    <label>Preferred Foot</label>
                    <select name="preferred_foot" class="form-control-custom">
                        <option value="right">Right</option>
                        <option value="left">Left</option>
                        <option value="both">Both</option>
                    </select>
                </div>

                <div class="form-group-custom col-span-2">
                    <label>Estimated Market Value ($)</label>
                    <input type="number" name="market_value" step="0.01" placeholder="e.g. 500000" class="form-control-custom">
                </div>

                <div class="form-group-custom col-span-2">
                    <label>Contract Start Date</label>
                    <input type="date" name="contract_start" class="form-control-custom">
                </div>

                <div class="form-group-custom col-span-2">
                    <label>Contract End Date</label>
                    <input type="date" name="contract_end" class="form-control-custom">
                </div>

                <div class="form-group-custom col-span-6">
                    <label>Biography</label>
                    <textarea name="biography" placeholder="Short description of the player..." rows="3" class="form-control-custom"></textarea>
                </div>

                <div class="form-group-custom col-span-6">
                    <label>Player Photo</label>
                    <input type="file" name="photo_pl" accept="image/*" class="form-control-custom" style="padding: 8px;">
                </div>

                <div class="col-span-6" style="margin-top: 10px;">
                    <button class="btn btn-primary btn-full" type="submit" style="height: 48px; font-size: 15px; font-weight: 700;">Submit Registration</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updateWrapperLayout() {
    const listSection = document.getElementById('players-list-section');
    const addSection = document.getElementById('add-player-section');
    const wrapper = document.getElementById('players-sections-wrapper');
    
    if (!listSection || !addSection || !wrapper) return;
    
    const isListVisible = listSection.style.display !== 'none';
    const isAddVisible = addSection.style.display !== 'none';
    
    if (isListVisible && isAddVisible) {
        wrapper.classList.remove('single-active');
        wrapper.style.display = 'grid';
        addSection.style.maxWidth = 'none';
        addSection.style.margin = '0';
    } else if (isListVisible || isAddVisible) {
        wrapper.classList.add('single-active');
        wrapper.style.display = 'grid';
        
        if (isAddVisible) {
            // Increased form width beautifully
            addSection.style.maxWidth = '960px';
            addSection.style.margin = '0 auto';
        } else {
            listSection.style.maxWidth = 'none';
            listSection.style.margin = '0';
        }
    } else {
        wrapper.style.display = 'none';
    }
}

function closeSection(sectionId) {
    const section = document.getElementById(sectionId);
    if (section) {
        section.style.display = 'none';
        updateWrapperLayout();
    }
}

function filterByPosition(position) {
    const listSection = document.getElementById('players-list-section');
    const addSection = document.getElementById('add-player-section');
    if (!listSection) return;
    
    // Explicitly HIDE form when viewing details
    if (addSection) {
        addSection.style.display = 'none';
    }
    
    // Make player display visible
    listSection.style.display = 'block';
    updateWrapperLayout();
    
    const rows = document.querySelectorAll('.player-row');
    const title = document.getElementById('list-title');
    const showAllBtn = document.getElementById('btn-show-all');
    
    let count = 0;
    
    rows.forEach(row => {
        const rowPos = row.getAttribute('data-position');
        if (position === 'all' || rowPos === position) {
            row.style.display = '';
            count++;
        } else {
            row.style.display = 'none';
        }
    });

    if (position === 'all') {
        title.textContent = 'All Registered Players';
        showAllBtn.style.display = 'none';
    } else {
        const readablePosition = position.charAt(0).toUpperCase() + position.slice(1) + 's';
        title.textContent = `${readablePosition} (${count} found)`;
        showAllBtn.style.display = '';
    }

    // Smooth scroll to list section
    setTimeout(() => {
        listSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 50);
}

function openAddForm(position) {
    const addSection = document.getElementById('add-player-section');
    const listSection = document.getElementById('players-list-section');
    if (!addSection) return;
    
    // Explicitly HIDE list when adding a player
    if (listSection) {
        listSection.style.display = 'none';
    }
    
    // Make registration form visible
    addSection.style.display = 'block';
    updateWrapperLayout();
    
    const select = document.getElementById('select-position');
    const noticeBox = document.getElementById('position-notice');
    const selectedText = document.getElementById('selected-pos-text');
    const firstNameInput = document.getElementById('input-first-name');
    
    // Set position select option
    if (select) {
        select.value = position;
    }
    
    // Display dynamic green notice
    if (noticeBox && selectedText) {
        noticeBox.style.display = 'flex';
        selectedText.textContent = position;
    }
    
    // Smooth scroll to form section
    setTimeout(() => {
        addSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 50);
    
    // Focus first input field
    if (firstNameInput) {
        setTimeout(() => {
            firstNameInput.focus();
        }, 450);
    }
}
</script>
