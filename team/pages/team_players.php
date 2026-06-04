<?php
if (!function_exists('pd_format_date')) {
    function pd_format_date($dateStr) {
        if (!$dateStr) return 'N/A';
        return date('d M Y', strtotime($dateStr));
    }
}
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

$userId = (int) (current_user()['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        set_flash('danger', 'Invalid security token.');
        redirect_to('index.php?page=players');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'register_player') {
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
    } elseif ($action === 'edit_player') {
        if (!current_user_can('players.edit')) {
            set_flash('danger', 'You do not have permission to edit player details.');
            redirect_to('index.php?page=players');
        }

        $playerId = (int)($_POST['player_id'] ?? 0);
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
        $status = $_POST['status'] ?? 'active';

        if ($playerId <= 0 || $firstName === '' || $lastName === '' || $position === '') {
            set_flash('danger', 'Player ID, name, and position are required.');
            redirect_to("index.php?page=players&id={$playerId}");
        }

        // Verify player team
        $existing = db_fetch_one("SELECT photo_pl, team_id FROM players WHERE id = ?", 'i', [$playerId]);
        if (!$existing || ($myTeamId > 0 && (int)$existing['team_id'] !== $myTeamId)) {
            set_flash('danger', 'Unauthorized access.');
            redirect_to('index.php?page=players');
        }

        $photoPath = $existing['photo_pl'];
        if (!empty($_FILES['photo_pl']['name'])) {
            list($uploaded, $pathOrError) = upload_file('photo_pl', 'uploads/players');
            if ($uploaded) {
                $photoPath = $pathOrError;
            } else {
                set_flash('danger', 'Photo upload failed: ' . $pathOrError);
                redirect_to("index.php?page=players&id={$playerId}");
            }
        }

        $sql = "UPDATE players SET 
            first_name = ?, last_name = ?, jersey_number = ?, date_of_birth = ?, 
            nationality = ?, position = ?, height_cm = ?, weight_kg = ?, 
            preferred_foot = ?, biography = ?, contract_start = ?, contract_end = ?, 
            market_value = ?, status = ?, photo_pl = ?, updated_at = NOW() 
            WHERE id = ?";

        $updated = db_execute($sql, 'ssisssiissssdssi', [
            $firstName, $lastName, $jerseyNumber, $dateOfBirth,
            $nationality, $position, $heightCm, $weightKg,
            $preferredFoot, $biography, $contractStart, $contract_end,
            $marketValue, $status, $photoPath, $playerId
        ]);

        if ($updated) {
            log_action('player_updated', 'players', 'players', $playerId, $existing, [
                'name' => $firstName . ' ' . $lastName,
                'team_id' => $myTeamId
            ]);
            set_flash('success', 'Player profile updated successfully.');
        } else {
            set_flash('danger', 'Failed to update player.');
        }

        redirect_to("index.php?page=players&id={$playerId}");
    } elseif ($action === 'add_record') {
        if (!current_user_can('player_ratings.rate')) {
            set_flash('danger', 'You do not have permission to rate players.');
            redirect_to('index.php?page=players');
        }

        $playerId = (int)($_POST['player_id'] ?? 0);
        $matchId = (int)($_POST['match_id'] ?? 0);
        $rating = (int)($_POST['rating'] ?? 0);
        $goals = (int)($_POST['goals'] ?? 0);
        $assists = (int)($_POST['assists'] ?? 0);
        $yellowCards = (int)($_POST['yellow_cards'] ?? 0);
        $redCards = (int)($_POST['red_cards'] ?? 0);
        $performanceSummary = trim($_POST['performance_summary'] ?? '');
        $coachComment = trim($_POST['coach_comment'] ?? '');

        if ($playerId <= 0 || $matchId <= 0 || $rating < 0 || $rating > 100) {
            set_flash('danger', 'Invalid record details.');
            redirect_to("index.php?page=players&id={$playerId}");
        }

        // Verify player team
        $existing = db_fetch_one("SELECT team_id, first_name, last_name FROM players WHERE id = ?", 'i', [$playerId]);
        if (!$existing || ($myTeamId > 0 && (int)$existing['team_id'] !== $myTeamId)) {
            set_flash('danger', 'Unauthorized access.');
            redirect_to('index.php?page=players');
        }

        // Highlight Video Upload Handler
        $videoId = null;
        if (isset($_FILES['highlight_video']) && $_FILES['highlight_video']['error'] === UPLOAD_ERR_OK) {
            $videoFile = $_FILES['highlight_video'];
            $ext = strtolower(pathinfo($videoFile['name'], PATHINFO_EXTENSION));
            $allowedExts = ['mp4', 'webm', 'ogg', 'mov', 'avi'];
            if (!in_array($ext, $allowedExts, true)) {
                set_flash('danger', 'Invalid video format. Allowed: MP4, WebM, OGG, MOV, AVI.');
                redirect_to("index.php?page=players&id={$playerId}");
            }

            $newName = uniqid('vid_', true) . '.' . $ext;
            $uploadDir = __DIR__ . '/../../uploads/videos';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $dest = $uploadDir . '/' . $newName;
            if (move_uploaded_file($videoFile['tmp_name'], $dest)) {
                $filePath = 'uploads/videos/' . $newName;
                $mimeType = $videoFile['type'];
                $fileSize = $videoFile['size'];
                db_execute(
                    "INSERT INTO media_files (uploaded_by, entity_type, entity_id, file_type, original_name, stored_name, file_path, mime_type, file_size_bytes)
                     VALUES (?, 'player_highlight', ?, 'video', ?, ?, ?, ?, ?)",
                    'iisssssi',
                    [$userId, $playerId, $videoFile['name'], $newName, $filePath, $mimeType, $fileSize]
                );
                $videoId = db_last_id();
            } else {
                set_flash('danger', 'Highlight video upload failed.');
                redirect_to("index.php?page=players&id={$playerId}");
            }
        }

        $sql = "INSERT INTO player_ratings (
            player_id, match_id, rating, coach_comment, performance_summary, 
            highlight_video_id, rated_by, ststuss, goals, assists, yellow_cards, red_cards
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?)";

        $inserted = db_execute($sql, 'iiissiiiiii', [
            $playerId, $matchId, $rating, $coachComment, $performanceSummary,
            $videoId, $userId, $goals, $assists, $yellowCards, $redCards
        ]);

        if ($inserted) {
            $ratingId = db_last_id();
            log_action('player_rating_added', 'player_ratings', 'player_ratings', $ratingId, null, [
                'player_id' => $playerId,
                'rating' => $rating
            ]);

            // Notify Federation Role
            $recipientIds = federation_role_user_ids();
            if (empty($recipientIds)) {
                $fedUsers = db_fetch_all("SELECT id FROM users WHERE user_type IN ('federation', 'admin')");
                $recipientIds = array_map(function($u) { return (int)$u['id']; }, $fedUsers);
            }

            $playerName = trim($existing['first_name'] . ' ' . $existing['last_name']);
            foreach ($recipientIds as $recipientId) {
                create_notification(
                    $recipientId,
                    'approval',
                    'Player Match Rating Pending',
                    "Coach added match performance record for player '{$playerName}' and is waiting for your approval."
                );
            }

            set_flash('success', 'Match performance record submitted successfully! Pending federation approval.');
        } else {
            set_flash('danger', 'Failed to save match record.');
        }

        redirect_to("index.php?page=players&id={$playerId}");
    } elseif ($action === 'delete_record') {
        if (!current_user_can('player_ratings.manage')) {
            set_flash('danger', 'You do not have permission to delete performance records.');
            redirect_to('index.php?page=players');
        }

        $recordId = (int)($_POST['record_id'] ?? 0);
        $playerId = (int)($_POST['player_id'] ?? 0);

        if ($recordId <= 0 || $playerId <= 0) {
            set_flash('danger', 'Invalid deletion parameters.');
            redirect_to('index.php?page=players');
        }

        // Verify record ownership
        $rating = db_fetch_one("SELECT highlight_video_id FROM player_ratings WHERE id = ? AND player_id = ?", 'ii', [$recordId, $playerId]);
        if (!$rating) {
            set_flash('danger', 'Performance record not found.');
            redirect_to("index.php?page=players&id={$playerId}");
        }

        if ($rating['highlight_video_id']) {
            $media = db_fetch_one("SELECT file_path FROM media_files WHERE id = ?", 'i', [$rating['highlight_video_id']]);
            if ($media) {
                $diskPath = __DIR__ . '/../../' . $media['file_path'];
                if (file_exists($diskPath)) {
                    unlink($diskPath);
                }
                db_execute("DELETE FROM media_files WHERE id = ?", 'i', [$rating['highlight_video_id']]);
            }
        }

        $deleted = db_execute("DELETE FROM player_ratings WHERE id = ?", 'i', [$recordId]);
        if ($deleted) {
            log_action('player_rating_deleted', 'player_ratings', 'player_ratings', $recordId);
            set_flash('success', 'Performance record deleted successfully.');
        } else {
            set_flash('danger', 'Failed to delete record.');
        }

        redirect_to("index.php?page=players&id={$playerId}");
    }
}

$playerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($playerId > 0) {
    // ═════════════════════════════════════════════════════════════════════════
    // PLAYER DETAILS DASHBOARD SUBPAGE
    // ═════════════════════════════════════════════════════════════════════════
    $player = db_fetch_one("
        SELECT p.*, t.name AS team_name 
        FROM players p 
        LEFT JOIN teams t ON t.id = p.team_id 
        WHERE p.id = ?
    ", 'i', [$playerId]);

    if (!$player || ($myTeamId > 0 && (int)$player['team_id'] !== $myTeamId)) {
        set_flash('danger', 'Player not found or unauthorized access.');
        redirect_to('index.php?page=players');
    }

    // Cumulative stats calculations from approved ratings
    $cumulative = db_fetch_one("
        SELECT 
            COUNT(pr.id) AS matches_played,
            SUM(pr.goals) AS goals,
            SUM(pr.assists) AS assists,
            SUM(pr.yellow_cards) AS yellow_cards,
            SUM(pr.red_cards) AS red_cards,
            AVG(pr.rating) AS average_rating
        FROM player_ratings pr
        WHERE pr.player_id = ? AND pr.ststuss = 'approved'
    ", 'i', [$playerId]);

    // Average rating conic progress values
    $avgRating = $cumulative['average_rating'] ? round((float)$cumulative['average_rating'], 1) : 0;
    
    // Fetch individual match ratings for timeline display
    $ratingsList = db_fetch_all("
        SELECT pr.*, m.match_date, m.round, ht.name AS home_name, at.name AS away_name, mf.file_path AS video_path, mf.original_name AS video_name
        FROM player_ratings pr
        INNER JOIN matches m ON m.id = pr.match_id
        INNER JOIN teams ht ON ht.id = m.home_team_id
        INNER JOIN teams at ON at.id = m.away_team_id
        LEFT JOIN media_files mf ON mf.id = pr.highlight_video_id
        WHERE pr.player_id = ?
        ORDER BY m.match_date DESC
    ", 'i', [$playerId]);

    // Filter trend line values
    $trendValues = [];
    $approvedRatings = db_fetch_all("
        SELECT pr.rating 
        FROM player_ratings pr 
        INNER JOIN matches m ON m.id = pr.match_id 
        WHERE pr.player_id = ? AND pr.ststuss = 'approved' 
        ORDER BY m.match_date ASC
    ", 'i', [$playerId]);
    foreach ($approvedRatings as $ar) {
        $trendValues[] = (float)$ar['rating'];
    }

    // Retrieve fixtures list to rate (team specific)
    $teamMatches = db_fetch_all("
        SELECT m.id, m.match_date, m.round, ht.name home_team, at.name away_team 
        FROM matches m 
        LEFT JOIN teams ht ON ht.id = m.home_team_id 
        LEFT JOIN teams at ON at.id = m.away_team_id 
        WHERE m.home_team_id = ? OR m.away_team_id = ? 
        ORDER BY m.match_date DESC
    ", 'ii', [$myTeamId, $myTeamId]);

    // Age calc
    $ageStr = 'N/A';
    if ($player['date_of_birth']) {
        $diff = date_diff(date_create($player['date_of_birth']), date_create('today'));
        $ageStr = $diff->y . ' yrs';
    }

    $posColors = [
        'goalkeeper' => '#ff9f43',
        'defender' => '#004e92',
        'midfielder' => '#5e60ce',
        'forward' => '#c9184a'
    ];
    $posColor = $posColors[strtolower($player['position'])] ?? '#004e92';
    ?>
    <style>
    .back-btn-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    .profile-grid {
        display: grid;
        grid-template-columns: 350px 1fr;
        gap: 24px;
    }
    @media (max-width: 900px) {
        .profile-grid {
            grid-template-columns: 1fr;
        }
    }
    .profile-sidebar-col {
        display: flex;
        flex-direction: column;
        gap: 24px;
    }
    .profile-card-clean {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 24px;
        text-align: center;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    }
    .profile-pic-wrap {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        overflow: hidden;
        margin: 0 auto 16px;
        border: 3px solid #e2e8f0;
        background: #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .profile-pic-wrap img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .profile-pic-placeholder {
        font-size: 36px;
        font-weight: 800;
        color: #94a3b8;
    }
    .player-title {
        font-size: 20px;
        font-weight: 700;
        color: var(--navy-800);
        margin-bottom: 4px;
    }
    .player-subtitle {
        font-size: 13.5px;
        font-weight: 600;
        color: var(--muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 16px;
    }
    .badge-pos {
        background: rgba(11, 31, 58, 0.05);
        color: var(--navy-800);
        font-size: 11px;
        font-weight: 700;
        padding: 4px 10px;
        border-radius: 20px;
        text-transform: uppercase;
    }
    .gauge-wrapper {
        margin: 20px auto;
        width: 140px;
        height: 140px;
        border-radius: 50%;
        background: conic-gradient(<?= $posColor; ?> <?= $avgRating * 3.6; ?>deg, #f1f5f9 0deg);
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
    }
    .gauge-inner {
        width: 110px;
        height: 110px;
        border-radius: 50%;
        background: #ffffff;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        box-shadow: inset 0 2px 5px rgba(0,0,0,0.03);
    }
    .gauge-score {
        font-size: 32px;
        font-weight: 800;
        color: var(--navy-900);
        line-height: 1;
    }
    .gauge-lbl {
        font-size: 9px;
        color: var(--muted);
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-top: 4px;
    }
    .profile-details-list {
        margin-top: 20px;
        text-align: left;
    }
    .profile-detail-item {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px solid #f1f5f9;
        font-size: 13.5px;
    }
    .profile-detail-item:last-child {
        border-bottom: none;
    }
    .profile-detail-item span {
        color: var(--muted);
        font-weight: 500;
    }
    .profile-detail-item strong {
        color: var(--navy-800);
        font-weight: 600;
    }
    .stats-summary-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }
    @media (max-width: 600px) {
        .stats-summary-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    .stat-card-clean {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    }
    .stat-num-clean {
        font-size: 28px;
        font-weight: 800;
        color: var(--navy-900);
        line-height: 1;
    }
    .stat-label-clean {
        font-size: 11px;
        color: var(--muted);
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-top: 6px;
    }
    .timeline-list {
        display: flex;
        flex-direction: column;
        gap: 16px;
        margin-top: 16px;
    }
    .timeline-item-clean {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    }
    .timeline-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 8px;
    }
    .timeline-match {
        font-weight: 700;
        color: var(--navy-800);
        font-size: 14.5px;
    }
    .timeline-rating-badge {
        font-size: 12px;
        font-weight: 700;
        background: rgba(11, 31, 58, 0.05);
        color: var(--navy-800);
        padding: 4px 10px;
        border-radius: 8px;
        border: 1px solid rgba(11, 31, 58, 0.1);
    }
    .timeline-meta {
        font-size: 11px;
        color: var(--muted);
        margin-bottom: 12px;
        display: flex;
        gap: 12px;
        font-weight: 500;
    }
    .timeline-summary {
        font-size: 13.5px;
        line-height: 1.5;
        color: #334155;
        margin-bottom: 8px;
    }
    .timeline-comment {
        font-size: 13px;
        background: #f8fafc;
        border-left: 3px solid #cbd5e1;
        padding: 10px 12px;
        border-radius: 0 8px 8px 0;
        margin-top: 8px;
        font-style: italic;
        color: #475569;
    }
    .delete-btn-wrap {
        display: flex;
        justify-content: flex-end;
        margin-top: 12px;
        border-top: 1px solid #f1f5f9;
        padding-top: 10px;
    }
    .btn-outline-danger {
        background: none;
        border: 1px solid #f87171;
        color: #ef4444;
        font-size: 12px;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .btn-outline-danger:hover {
        background: #fef2f2;
    }
    .chart-container-clean {
        height: 200px;
        width: 100%;
        margin-top: 10px;
        position: relative;
    }
    .toggle-form-btn {
        background: #fff;
        border: 1px solid #cbd5e1;
        color: #475569;
        padding: 8px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 13.5px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s;
    }
    .toggle-form-btn:hover {
        background: #f8fafc;
        border-color: #94a3b8;
    }
    .video-highlight-player {
        width: 100%;
        border-radius: 8px;
        margin-top: 10px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 3px rgba(0,0,0,0.01);
    }
    </style>

    <div class="back-btn-row">
        <a href="index.php?page=players" class="btn btn-secondary" style="display:inline-flex; align-items:center; gap:6px;">
            <i class="fa-solid fa-arrow-left-long"></i> Back to Roster
        </a>
        <div>
            <button type="button" onclick="toggleEditForm()" class="toggle-form-btn">
                <i class="fa-solid fa-user-pen"></i> Edit Profile
            </button>
        </div>
    </div>

    <div class="profile-grid">
        <!-- Sidebar: Personal Profile -->
        <div class="profile-sidebar-col">
            <div class="profile-card-clean">
                <div class="profile-pic-wrap">
                    <?php if ($player['photo_pl']): ?>
                        <img src="<?= e(app_url($player['photo_pl'])); ?>" alt="Profile">
                    <?php else: ?>
                        <div class="profile-pic-placeholder">
                            <?= strtoupper(substr($player['first_name'], 0, 1) . substr($player['last_name'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="player-title"><?= e($player['first_name'] . ' ' . $player['last_name']); ?></div>
                <div class="player-subtitle">
                    <span class="badge-pos" style="background-color: <?= $posColor; ?>15; color: <?= $posColor; ?>;"><?= e($player['position']); ?></span>
                    <strong style="margin-left: 6px; color: var(--navy-800);">#<?= e($player['jersey_number'] ?? '-'); ?></strong>
                </div>

                <div class="gauge-wrapper">
                    <div class="gauge-inner">
                        <div class="gauge-score"><?= $avgRating; ?></div>
                        <div class="gauge-lbl">AVG RATING</div>
                    </div>
                </div>

                <div class="profile-details-list">
                    <div class="profile-detail-item">
                        <span>Nationality</span>
                        <strong><?= e($player['nationality'] ?: '-'); ?></strong>
                    </div>
                    <div class="profile-detail-item">
                        <span>Age</span>
                        <strong><?= $ageStr; ?> (<?= pd_format_date($player['date_of_birth']); ?>)</strong>
                    </div>
                    <div class="profile-detail-item">
                        <span>Height</span>
                        <strong><?= $player['height_cm'] ? $player['height_cm'] . ' cm' : '-'; ?></strong>
                    </div>
                    <div class="profile-detail-item">
                        <span>Weight</span>
                        <strong><?= $player['weight_kg'] ? $player['weight_kg'] . ' kg' : '-'; ?></strong>
                    </div>
                    <div class="profile-detail-item">
                        <span>Foot</span>
                        <strong style="text-transform: capitalize;"><?= e($player['preferred_foot']); ?></strong>
                    </div>
                    <div class="profile-detail-item">
                        <span>Value</span>
                        <strong style="color:var(--navy-700);"><?= $player['market_value'] ? '$' . number_format((float) $player['market_value']) : '-'; ?></strong>
                    </div>
                    <div class="profile-detail-item">
                        <span>Status</span>
                        <strong><?= status_badge($player['status']); ?></strong>
                    </div>
                    <div class="profile-detail-item">
                        <span>Contract</span>
                        <strong style="font-size:11px;"><?= pd_format_date($player['contract_start']); ?> - <?= pd_format_date($player['contract_end']); ?></strong>
                    </div>
                </div>
            </div>

            <!-- Biography Card -->
            <div class="card">
                <div class="card-head"><h3>Biography</h3></div>
                <div class="card-body">
                    <p style="font-size:13.5px; line-height:1.6; color:#475569; font-style: italic; white-space: pre-line;">
                        <?= e($player['biography'] ?: 'No biography registered for this player.'); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Main Panel: Stats & Records -->
        <div class="profile-main-col" style="display: flex; flex-direction: column; gap: 24px;">
            
            <!-- EDIT FORM (Hidden by default) -->
            <div class="card" id="edit-profile-card" style="display: none;">
                <div class="card-head"><h3>Edit Player Information</h3></div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data" class="form-grid-custom">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                        <input type="hidden" name="action" value="edit_player">
                        <input type="hidden" name="player_id" value="<?= (int) $playerId; ?>">

                        <div class="form-group-custom col-span-3">
                            <label>First Name *</label>
                            <input type="text" name="first_name" required value="<?= e($player['first_name']); ?>" class="form-control-custom">
                        </div>

                        <div class="form-group-custom col-span-3">
                            <label>Last Name *</label>
                            <input type="text" name="last_name" required value="<?= e($player['last_name']); ?>" class="form-control-custom">
                        </div>

                        <div class="form-group-custom col-span-3">
                            <label>Position *</label>
                            <select name="position" required class="form-control-custom">
                                <option value="goalkeeper" <?= $player['position'] === 'goalkeeper' ? 'selected' : ''; ?>>Goalkeeper</option>
                                <option value="defender" <?= $player['position'] === 'defender' ? 'selected' : ''; ?>>Defender</option>
                                <option value="midfielder" <?= $player['position'] === 'midfielder' ? 'selected' : ''; ?>>Midfielder</option>
                                <option value="forward" <?= $player['position'] === 'forward' ? 'selected' : ''; ?>>Forward</option>
                            </select>
                        </div>

                        <div class="form-group-custom col-span-3">
                            <label>Jersey Number</label>
                            <input type="number" name="jersey_number" min="1" max="99" value="<?= e($player['jersey_number']); ?>" class="form-control-custom">
                        </div>

                        <div class="form-group-custom col-span-3">
                            <label>Date of Birth</label>
                            <input type="date" name="date_of_birth" value="<?= e($player['date_of_birth']); ?>" class="form-control-custom">
                        </div>

                        <div class="form-group-custom col-span-3">
                            <label>Nationality</label>
                            <input type="text" name="nationality" value="<?= e($player['nationality']); ?>" class="form-control-custom">
                        </div>

                        <div class="form-group-custom col-span-2">
                            <label>Height (cm)</label>
                            <input type="number" name="height_cm" value="<?= e($player['height_cm']); ?>" class="form-control-custom">
                        </div>

                        <div class="form-group-custom col-span-2">
                            <label>Weight (kg)</label>
                            <input type="number" name="weight_kg" value="<?= e($player['weight_kg']); ?>" class="form-control-custom">
                        </div>

                        <div class="form-group-custom col-span-2">
                            <label>Preferred Foot</label>
                            <select name="preferred_foot" class="form-control-custom">
                                <option value="right" <?= $player['preferred_foot'] === 'right' ? 'selected' : ''; ?>>Right</option>
                                <option value="left" <?= $player['preferred_foot'] === 'left' ? 'selected' : ''; ?>>Left</option>
                                <option value="both" <?= $player['preferred_foot'] === 'both' ? 'selected' : ''; ?>>Both</option>
                            </select>
                        </div>

                        <div class="form-group-custom col-span-2">
                            <label>Market Value ($)</label>
                            <input type="number" name="market_value" step="0.01" value="<?= e($player['market_value']); ?>" class="form-control-custom">
                        </div>

                        <div class="form-group-custom col-span-2">
                            <label>Contract Start</label>
                            <input type="date" name="contract_start" value="<?= e($player['contract_start']); ?>" class="form-control-custom">
                        </div>

                        <div class="form-group-custom col-span-2">
                            <label>Contract End</label>
                            <input type="date" name="contract_end" value="<?= e($player['contract_end']); ?>" class="form-control-custom">
                        </div>

                        <div class="form-group-custom col-span-3">
                            <label>Status</label>
                            <select name="status" class="form-control-custom">
                                <option value="active" <?= $player['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?= $player['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="injured" <?= $player['status'] === 'injured' ? 'selected' : ''; ?>>Injured</option>
                                <option value="suspended" <?= $player['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                <option value="transferred" <?= $player['status'] === 'transferred' ? 'selected' : ''; ?>>Transferred</option>
                            </select>
                        </div>

                        <div class="form-group-custom col-span-3">
                            <label>Change Photo</label>
                            <input type="file" name="photo_pl" accept="image/*" class="form-control-custom" style="padding: 8px;">
                        </div>

                        <div class="form-group-custom col-span-6">
                            <label>Biography</label>
                            <textarea name="biography" rows="3" class="form-control-custom"><?= e($player['biography']); ?></textarea>
                        </div>

                        <div class="col-span-6" style="margin-top: 10px; display: flex; gap: 10px;">
                            <button class="btn btn-primary" type="submit">Save Updates</button>
                            <button class="btn btn-secondary" type="button" onclick="toggleEditForm()">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-summary-grid">
                <div class="stat-card-clean">
                    <div class="stat-num-clean"><?= (int)$cumulative['matches_played']; ?></div>
                    <div class="stat-label-clean">Matches Rated</div>
                </div>
                <div class="stat-card-clean">
                    <div class="stat-num-clean"><?= (int)$cumulative['goals']; ?></div>
                    <div class="stat-label-clean">Goals logged</div>
                </div>
                <div class="stat-card-clean">
                    <div class="stat-num-clean"><?= (int)$cumulative['assists']; ?></div>
                    <div class="stat-label-clean">Assists logged</div>
                </div>
                <div class="stat-card-clean">
                    <div class="stat-num-clean">
                        <span style="color: #f59e0b;"><?= (int)$cumulative['yellow_cards']; ?></span>
                        <span style="color: #cbd5e1; font-weight: 500; font-size: 20px; margin: 0 4px;">/</span>
                        <span style="color: #ef4444;"><?= (int)$cumulative['red_cards']; ?></span>
                    </div>
                    <div class="stat-label-clean">Yellow / Red</div>
                </div>
            </div>

            <!-- Trend Chart -->
            <div class="card">
                <div class="card-head"><h3>Rating Trend Graph</h3></div>
                <div class="card-body">
                    <?php if (count($trendValues) > 0): ?>
                        <div class="chart-container-clean">
                            <canvas data-line-chart data-values="<?= e(implode(',', $trendValues)); ?>" data-color="<?= $posColor; ?>" data-fill="<?= $posColor; ?>15" style="width: 100%; height: 100%;"></canvas>
                        </div>
                    <?php else: ?>
                        <div class="empty-state" style="padding: 40px; text-align: center; color: var(--muted);">No rating history available to plot trend graph. Submit performance records to see the trend.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Add Performance Record -->
            <div class="card">
                <div class="card-head"><h3>Add Player Performance Record</h3></div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data" class="form-grid-custom">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                        <input type="hidden" name="action" value="add_record">
                        <input type="hidden" name="player_id" value="<?= (int) $playerId; ?>">

                        <div class="form-group-custom col-span-3">
                            <label>Select Match Fixture *</label>
                            <select name="match_id" required class="form-control-custom">
                                <option value="">Select Match</option>
                                <?php foreach ($teamMatches as $m): ?>
                                    <option value="<?= (int) $m['id']; ?>"><?= e(date('d M Y', strtotime($m['match_date']))); ?>: <?= e($m['home_team']); ?> vs <?= e($m['away_team']); ?> (Round <?= e($m['round']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group-custom col-span-3">
                            <label>Match Performance Rating (0 - 100) *</label>
                            <input type="number" name="rating" min="0" max="100" required placeholder="e.g. 85" class="form-control-custom">
                        </div>

                        <div class="form-group-custom col-span-3">
                            <label>Goals Scored</label>
                            <input type="number" name="goals" min="0" value="0" class="form-control-custom">
                        </div>

                        <div class="form-group-custom col-span-3">
                            <label>Assists Made</label>
                            <input type="number" name="assists" min="0" value="0" class="form-control-custom">
                        </div>

                        <div class="form-group-custom col-span-3">
                            <label>Yellow Cards</label>
                            <input type="number" name="yellow_cards" min="0" max="2" value="0" class="form-control-custom">
                        </div>

                        <div class="form-group-custom col-span-3">
                            <label>Red Cards</label>
                            <input type="number" name="red_cards" min="0" max="1" value="0" class="form-control-custom">
                        </div>

                        <div class="form-group-custom col-span-6">
                            <label>Highlight Video clip (MP4, WebM)</label>
                            <input type="file" name="highlight_video" accept="video/*" class="form-control-custom" style="padding: 8px;">
                        </div>

                        <div class="form-group-custom col-span-6">
                            <label>Performance Summary *</label>
                            <input type="text" name="performance_summary" required placeholder="Brief description of key performance events (e.g., Scored winning volley in 87th minute)" class="form-control-custom">
                        </div>

                        <div class="form-group-custom col-span-6">
                            <label>Coach Notes / Comments</label>
                            <textarea name="coach_comment" rows="2" placeholder="Tactical notes, areas of improvement, or general comments..." class="form-control-custom"></textarea>
                        </div>

                        <div class="col-span-6" style="margin-top: 10px;">
                            <button class="btn btn-primary" type="submit">Submit Performance Record</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Historical Match Ratings Timeline -->
            <div class="card">
                <div class="card-head"><h3>Performance Log & Video Highlights</h3></div>
                <div class="card-body">
                    <?php if (empty($ratingsList)): ?>
                        <div class="empty-state" style="padding: 30px; text-align: center; color: var(--muted);">No performance records logged yet.</div>
                    <?php else: ?>
                        <div class="timeline-list">
                            <?php foreach ($ratingsList as $r): ?>
                                <div class="timeline-item-clean">
                                    <div class="timeline-header">
                                        <div class="timeline-match">
                                            <?= e($r['home_name']); ?> vs <?= e($r['away_name']); ?>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <span class="timeline-rating-badge">
                                                <i class="fa-solid fa-star" style="color: #eab308; margin-right: 4px;"></i> <?= (int)$r['rating']; ?>/100
                                            </span>
                                            <?= status_badge($r['ststuss']); ?>
                                        </div>
                                    </div>

                                    <div class="timeline-meta">
                                        <span><i class="fa-regular fa-calendar"></i> <?= pd_format_date($r['match_date']); ?></span>
                                        <span>•</span>
                                        <span>Round <?= e($r['round']); ?></span>
                                        <span>•</span>
                                        <span>⚽ <?= (int)$r['goals']; ?> G &nbsp;•&nbsp; 🅰️ <?= (int)$r['assists']; ?> A</span>
                                        <?php if ($r['yellow_cards'] > 0 || $r['red_cards'] > 0): ?>
                                            <span>•</span>
                                            <span style="color:#ef4444;"><i class="fa-regular fa-square"></i> Y:<?= (int)$r['yellow_cards']; ?> R:<?= (int)$r['red_cards']; ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="timeline-summary">
                                        <strong>Summary:</strong> <?= e($r['performance_summary']); ?>
                                    </div>

                                    <?php if ($r['coach_comment']): ?>
                                        <div class="timeline-comment">
                                            <strong>Coach Note:</strong> <?= e($r['coach_comment']); ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- HTML5 Video clip -->
                                    <?php if ($r['video_path']): ?>
                                        <div style="margin-top: 12px;">
                                            <span style="font-size: 12px; font-weight: 700; color: var(--navy-800);"><i class="fa-solid fa-circle-play"></i> Match Highlight Video:</span>
                                            <video class="video-highlight-player" src="../<?= e($r['video_path']); ?>" controls preload="metadata"></video>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Deletion handler -->
                                    <div class="delete-btn-wrap">
                                        <form method="post" onsubmit="return confirm('Are you sure you want to delete this performance record?');">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="delete_record">
                                            <input type="hidden" name="record_id" value="<?= (int) $r['id']; ?>">
                                            <input type="hidden" name="player_id" value="<?= (int) $playerId; ?>">
                                            <button type="submit" class="btn-outline-danger"><i class="fa-solid fa-trash-can"></i> Delete Record</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <script>
    function toggleEditForm() {
        const card = document.getElementById('edit-profile-card');
        if (card.style.display === 'none') {
            card.style.display = 'block';
            card.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } else {
            card.style.display = 'none';
        }
    }
    </script>
    <?php
} else {
    // ═════════════════════════════════════════════════════════════════════════
    // GENERAL ROSTER LIST VIEW
    // ═════════════════════════════════════════════════════════════════════════
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
        border-radius: 12px;
        border: 1px solid #e2e8f0 !important;
        padding: 24px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02) !important;
        transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        min-height: 180px;
    }

    .position-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 24px rgba(11, 31, 58, 0.05) !important;
        border-color: #cbd5e0 !important;
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
        background: #f1f5f9 !important;
        color: var(--navy-800) !important;
    }

    .position-card:hover .pos-icon-wrap {
        transform: scale(1.08) rotate(5deg);
        background: var(--navy-800) !important;
        color: #ffffff !important;
    }

    .pos-info h4 {
        font-size: 16px;
        font-weight: 700;
        color: var(--navy-800);
        margin-bottom: 4px;
        letter-spacing: -0.01em;
    }

    .pos-info p {
        font-size: 26px;
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
        border-radius: 8px;
        font-size: 12.5px;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        cursor: pointer;
        transition: all 0.2s ease;
        border: 1px solid #ff9f43;
        background: #ff9f43;
        color: #fff;
    }

    .btn-card-action:hover {
        background: #ff8b1f;
        border-color: #ff8b1f;
        color: #fff;
    }

    .btn-card-primary {
        background: #ff7a00;
        color: #fff;
        border-color: #ff7a00;
    }

    .btn-card-primary:hover {
        background: #e06900;
        border-color: #e06900;
        color: #fff;
    }

    .filter-badge-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 14px;
    }

    .status-highlight-box {
        background: rgba(11, 31, 58, 0.03);
        border: 1px dashed rgba(11, 31, 58, 0.2);
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

    .card {
        border: 1px solid #e2e8f0 !important;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02) !important;
        border-radius: 12px !important;
    }

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
        border-color: var(--navy-600);
        background-color: #ffffff;
        box-shadow: 0 0 0 4px rgba(11, 31, 58, 0.08);
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
    
    .player-link-clean {
        color: var(--navy-800);
        text-decoration: none;
        font-weight: 700;
        transition: all 0.15s;
    }
    .player-link-clean:hover {
        color: var(--navy-950);
        text-decoration: underline;
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

    <!-- Layout Wrapper: Roster list is displayed by default, side-by-side or alone -->
    <div class="two-col" id="players-sections-wrapper" style="display: none;">
        <!-- Left Column: Players List -->
        <div class="card" id="players-list-section" style="display: none;">
            <div class="card-head">
                <div class="filter-badge-row" style="width: 100%; display: flex; justify-content: space-between; align-items: center;">
                    <h3 id="list-title">All Registered Players</h3>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <button class="btn btn-secondary btn-sm" id="btn-show-all" onclick="filterByPosition('all')" style="display: none;">Show All</button>
                        <?php if (current_user_can('players.create')): ?>
                            <button class="btn btn-primary btn-sm" onclick="openAddForm('all')"><i class="fa-solid fa-user-plus"></i> Register Player</button>
                        <?php endif; ?>
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
                                <th>Appearances</th>
                                <th>Status</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($players)): ?>
                                <tr><td colspan="8"><div class="empty-state">No players registered yet.</div></td></tr>
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
                                            <a href="index.php?page=players&id=<?= (int) $p['id']; ?>" class="player-link-clean">
                                                <?= e($p['first_name'] . ' ' . $p['last_name']); ?>
                                            </a>
                                        </td>
                                        <td><span class="badge" style="background: rgba(11, 31, 58, 0.05); color: var(--navy-800); text-transform: capitalize;"><?= e($p['position']); ?></span></td>
                                        <td><?= e($p['jersey_number'] ?? '-'); ?></td>
                                        <td><?= e($p['nationality'] ?: '-'); ?></td>
                                        <td>
                                            <?php
                                            $appCount = (int) (db_fetch_one("
                                                SELECT COUNT(DISTINCT m.id) total 
                                                FROM lineup_players lp 
                                                JOIN match_lineups ml ON ml.id = lp.lineup_id 
                                                JOIN matches m ON m.id = ml.match_id 
                                                WHERE lp.player_id = ? 
                                                  AND ml.status = 'approved' 
                                                  AND m.status IN ('completed', 'in_progress', 'lineup_approved')
                                            ", 'i', [$p['id']])['total'] ?? 0);
                                            ?>
                                            <span class="badge" style="font-weight: 700; background: rgba(21, 128, 61, 0.08); color: #15803d; border: 1px solid rgba(21, 128, 61, 0.15); font-size: 11px; border-radius: 6px; padding: 4px 8px;"><?= $appCount; ?> matches</span>
                                        </td>
                                        <td>
                                            <?php if ($p['status'] === 'inactive'): ?>
                                                <span class="badge badge-warning">Pending Approval</span>
                                            <?php else: ?>
                                                <?= status_badge($p['status']); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: right;">
                                            <a href="index.php?page=players&id=<?= (int) $p['id']; ?>" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 8px;">
                                                <i class="fa-solid fa-user-tie"></i> Profile
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right Column: Registration Form (Hidden by default unless add clicked) -->
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
                    <input type="hidden" name="action" value="register_player">

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
            wrapper.style.gridTemplateColumns = '1.3fr 1fr';
            addSection.style.maxWidth = 'none';
            addSection.style.margin = '0';
        } else if (isListVisible || isAddVisible) {
            wrapper.classList.add('single-active');
            wrapper.style.display = 'grid';
            wrapper.style.gridTemplateColumns = '1fr';
            
            if (isAddVisible) {
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
        if (!listSection) return;
        
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

        setTimeout(() => {
            listSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 50);
    }

    function openAddForm(position) {
        const addSection = document.getElementById('add-player-section');
        const listSection = document.getElementById('players-list-section');
        if (!addSection) return;
        
        addSection.style.display = 'block';
        updateWrapperLayout();
        
        const select = document.getElementById('select-position');
        const noticeBox = document.getElementById('position-notice');
        const selectedText = document.getElementById('selected-pos-text');
        const firstNameInput = document.getElementById('input-first-name');
        
        if (select) {
            select.value = position === 'all' ? '' : position;
        }
        
        if (noticeBox && selectedText) {
            if (position === 'all') {
                noticeBox.style.display = 'none';
            } else {
                noticeBox.style.display = 'flex';
                selectedText.textContent = position;
            }
        }
        
        setTimeout(() => {
            addSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 50);
        
        if (firstNameInput) {
            setTimeout(() => {
                firstNameInput.focus();
            }, 450);
        }
    }
    
    updateWrapperLayout();
    </script>
    <?php
}
?>
