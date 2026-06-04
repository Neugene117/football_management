<?php
if (isset($_GET['action']) && $_GET['action'] === 'get_players') {
    require_once __DIR__ . '/includes/functions.php';
    header('Content-Type: application/json');
    
    $teamId = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;
    if ($teamId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid team ID']);
        exit;
    }
    
    if (!function_exists('mapPositionAbbrev')) {
        function mapPositionAbbrev($pos) {
            $map = [
                'goalkeeper' => 'GK',
                'defender' => 'CB',
                'midfielder' => 'CM',
                'forward' => 'ST'
            ];
            return $map[strtolower($pos)] ?? 'CM';
        }
    }
    
    if (!function_exists('mapPositionType')) {
        function mapPositionType($pos) {
            $map = [
                'goalkeeper' => 'gk',
                'defender' => 'def',
                'midfielder' => 'mid',
                'forward' => 'fwd'
            ];
            return $map[strtolower($pos)] ?? 'mid';
        }
    }
    
    $team = db_fetch_one("
        SELECT t.*, s.name AS stadium_name, s.city AS stadium_city
        FROM teams t
        LEFT JOIN stadiums s ON s.id = t.home_stadium_id
        WHERE t.id = ?
    ", 'i', [$teamId]);
    
    if (!$team) {
        echo json_encode(['success' => false, 'message' => 'Team not found']);
        exit;
    }
    
    $players = db_fetch_all("
        SELECT p.*, ps.goals, ps.assists, ps.average_rating
        FROM players p
        LEFT JOIN player_statistics ps ON ps.player_id = p.id
        WHERE p.team_id = ? AND p.status = 'active'
        ORDER BY FIELD(p.position, 'goalkeeper', 'defender', 'midfielder', 'forward') ASC, p.jersey_number ASC, p.first_name ASC
    ", 'i', [$teamId]);
    
    $playersList = array_map(function($p) {
        return [
            'id' => $p['id'],
            'first_name' => $p['first_name'],
            'last_name' => $p['last_name'],
            'jersey_number' => (int) $p['jersey_number'],
            'position' => $p['position'],
            'pos_abbrev' => mapPositionAbbrev($p['position']),
            'pos_type' => mapPositionType($p['position']),
            'photo' => $p['photo_pl'] ? app_url($p['photo_pl']) : '',
            'nationality' => $p['nationality'] ?: 'Rwanda',
            'goals' => (int) ($p['goals'] ?? 0),
            'assists' => (int) ($p['assists'] ?? 0),
            'rating' => (int) ($p['average_rating'] ?: 75)
        ];
    }, $players);
    
    echo json_encode([
        'success' => true,
        'team' => [
            'id' => $team['id'],
            'name' => $team['name'],
            'logo' => $team['logo'] ? app_url($team['logo']) : '',
            'city' => $team['city'] ?: 'Kigali',
            'stadium_name' => $team['stadium_name'] ?: 'Home Arena',
            'stadium_city' => $team['stadium_city'] ?: 'Kigali',
            'is_active' => (bool) $team['is_active'],
            'player_count' => count($playersList)
        ],
        'players' => $playersList
    ]);
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'get_player_profile') {
    require_once __DIR__ . '/includes/functions.php';
    header('Content-Type: application/json');
    
    $playerId = isset($_GET['player_id']) ? (int)$_GET['player_id'] : 0;
    if ($playerId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid player ID']);
        exit;
    }
    
    // Fetch player details
    $player = db_fetch_one("
        SELECT p.*, t.name AS team_name, t.logo AS team_logo, t.city AS team_city
        FROM players p
        INNER JOIN teams t ON t.id = p.team_id
        WHERE p.id = ?
    ", 'i', [$playerId]);
    
    if (!$player) {
        echo json_encode(['success' => false, 'message' => 'Player not found']);
        exit;
    }
    
    // Fetch statistics per competition
    $stats = db_fetch_all("
        SELECT ps.*, c.name AS competition_name
        FROM player_statistics ps
        LEFT JOIN competitions c ON c.id = ps.competition_id
        WHERE ps.player_id = ? AND ps.statuss = 'approved'
    ", 'i', [$playerId]);
    
    // Fetch cumulative statistics
    $cumulative = db_fetch_one("
        SELECT 
            SUM(matches_played) AS matches_played,
            SUM(matches_started) AS matches_started,
            SUM(minutes_played) AS minutes_played,
            SUM(goals) AS goals,
            SUM(assists) AS assists,
            SUM(yellow_cards) AS yellow_cards,
            SUM(red_cards) AS red_cards,
            SUM(clean_sheets) AS clean_sheets,
            SUM(saves) AS saves,
            AVG(average_rating) AS average_rating
        FROM player_statistics
        WHERE player_id = ? AND statuss = 'approved'
    ", 'i', [$playerId]);
    
    // Fetch career/played teams
    $teamsPlayed = db_fetch_all("
        SELECT DISTINCT t.id, t.name, t.logo, t.city
        FROM (
            SELECT team_id FROM players WHERE id = ?
            UNION
            SELECT ml.team_id 
            FROM lineup_players lp 
            INNER JOIN match_lineups ml ON ml.id = lp.lineup_id 
            WHERE lp.player_id = ?
            UNION
            SELECT team_id FROM match_events WHERE player_id = ?
        ) temp
        INNER JOIN teams t ON t.id = temp.team_id
    ", 'iii', [$playerId, $playerId, $playerId]);
    
    // Fetch ratings & highlights
    $ratings = db_fetch_all("
        SELECT pr.*, m.match_date, m.round, ht.name AS home_name, at.name AS away_name, ht.logo AS home_logo, at.logo AS away_logo, mf.file_path AS video_path, mf.original_name AS video_name
        FROM player_ratings pr
        INNER JOIN matches m ON m.id = pr.match_id
        INNER JOIN teams ht ON ht.id = m.home_team_id
        INNER JOIN teams at ON at.id = m.away_team_id
        LEFT JOIN media_files mf ON mf.id = pr.highlight_video_id
        WHERE pr.player_id = ? AND pr.ststuss = 'approved'
        ORDER BY m.match_date DESC
    ", 'i', [$playerId]);

    // Format output
    echo json_encode([
        'success' => true,
        'player' => [
            'id' => $player['id'],
            'first_name' => $player['first_name'],
            'last_name' => $player['last_name'],
            'jersey_number' => (int) $player['jersey_number'],
            'position' => $player['position'],
            'photo' => $player['photo_pl'] ? app_url($player['photo_pl']) : '',
            'nationality' => $player['nationality'] ?: 'Rwanda',
            'date_of_birth' => $player['date_of_birth'],
            'height_cm' => $player['height_cm'] ? (int) $player['height_cm'] : null,
            'weight_kg' => $player['weight_kg'] ? (int) $player['weight_kg'] : null,
            'preferred_foot' => $player['preferred_foot'],
            'biography' => $player['biography'],
            'contract_start' => $player['contract_start'],
            'contract_end' => $player['contract_end'],
            'market_value' => $player['market_value'] ? (float) $player['market_value'] : null,
            'status' => $player['status'],
            'team_id' => $player['team_id'],
            'team_name' => $player['team_name'],
            'team_logo' => $player['team_logo'] ? app_url($player['team_logo']) : '',
            'team_city' => $player['team_city']
        ],
        'stats' => [
            'cumulative' => [
                'matches_played' => (int) ($cumulative['matches_played'] ?? 0),
                'matches_started' => (int) ($cumulative['matches_started'] ?? 0),
                'minutes_played' => (int) ($cumulative['minutes_played'] ?? 0),
                'goals' => (int) ($cumulative['goals'] ?? 0),
                'assists' => (int) ($cumulative['assists'] ?? 0),
                'yellow_cards' => (int) ($cumulative['yellow_cards'] ?? 0),
                'red_cards' => (int) ($cumulative['red_cards'] ?? 0),
                'clean_sheets' => (int) ($cumulative['clean_sheets'] ?? 0),
                'saves' => (int) ($cumulative['saves'] ?? 0),
                'average_rating' => $cumulative['average_rating'] ? round((float)$cumulative['average_rating'], 1) : 75
            ],
            'by_competition' => array_map(function($s) {
                return [
                    'competition_name' => $s['competition_name'] ?: 'Rwanda Premier League',
                    'matches_played' => (int) $s['matches_played'],
                    'goals' => (int) $s['goals'],
                    'assists' => (int) $s['assists'],
                    'average_rating' => (float) $s['average_rating']
                ];
            }, $stats)
        ],
        'teams_played' => array_map(function($t) {
            return [
                'id' => $t['id'],
                'name' => $t['name'],
                'logo' => $t['logo'] ? app_url($t['logo']) : '',
                'city' => $t['city']
            ];
        }, $teamsPlayed),
        'ratings' => array_map(function($r) {
            return [
                'id' => $r['id'],
                'match_id' => $r['match_id'],
                'rating' => (int) $r['rating'],
                'coach_comment' => $r['coach_comment'],
                'performance_summary' => $r['performance_summary'],
                'match_date' => $r['match_date'],
                'round' => $r['round'],
                'home_name' => $r['home_name'],
                'away_name' => $r['away_name'],
                'home_logo' => $r['home_logo'] ? app_url($r['home_logo']) : '',
                'away_logo' => $r['away_logo'] ? app_url($r['away_logo']) : '',
                'video_path' => $r['video_path'] ? app_url($r['video_path']) : '',
                'video_name' => $r['video_name']
            ];
        }, $ratings)
    ]);
    exit;
}

$currentPage = 'teams';
require_once __DIR__ . '/public_header.php';
if (isset($_GET['id'])) {
    include __DIR__ . '/pages/public_team_details.php';
} else {
    include __DIR__ . '/pages/public_teams.php';
}
include __DIR__ . '/public_footer.php';
?>
