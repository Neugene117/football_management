<?php
if (isset($_GET['action']) && $_GET['action'] === 'get_lineup') {
    require_once __DIR__ . '/includes/functions.php';
    header('Content-Type: application/json');
    
    $matchId = isset($_GET['match_id']) ? (int)$_GET['match_id'] : 0;
    if ($matchId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid match ID']);
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
    
    $match = db_fetch_one("
        SELECT m.id, m.match_date, m.match_time, m.matchday,
               ht.id home_team_id, ht.name home_name, ht.logo home_logo,
               at.id away_team_id, at.name away_name, at.logo away_logo,
               s.name stadium_name, c.name competition_name
        FROM matches m
        INNER JOIN teams ht ON ht.id = m.home_team_id
        INNER JOIN teams at ON at.id = m.away_team_id
        LEFT JOIN stadiums s ON s.id = m.stadium_id
        LEFT JOIN competitions c ON c.id = m.competition_id
        WHERE m.id = ?
    ", 'i', [$matchId]);
    
    if (!$match) {
        echo json_encode(['success' => false, 'message' => 'Match not found']);
        exit;
    }
    
    $homeId = (int) $match['home_team_id'];
    $awayId = (int) $match['away_team_id'];
    
    // 1. Fetch Home Lineup
    $homeLineup = db_fetch_one("
        SELECT ml.id, f.name formation_name, f.display_name formation_display 
        FROM match_lineups ml 
        JOIN formations f ON f.id = ml.formation_id 
        WHERE ml.match_id = ? AND ml.team_id = ?
    ", 'ii', [$matchId, $homeId]);
    
    $homeFormation = '4-3-3';
    $homePlayers = [];
    $homeBench = [];
    
    if ($homeLineup) {
        $homeFormation = $homeLineup['formation_name'];
        $homeStarters = db_fetch_all("
            SELECT lp.*, p.first_name, p.last_name, p.jersey_number, p.position, p.photo_pl 
            FROM lineup_players lp 
            JOIN players p ON p.id = lp.player_id 
            WHERE lp.lineup_id = ? AND lp.is_starter = 1
            ORDER BY FIELD(lp.position_slot, 'GK', 'DEF_0', 'DEF_1', 'DEF_2', 'DEF_3', 'DEF_4', 'MID_0', 'MID_1', 'MID_2', 'MID_3', 'MID_4', 'FWD_0', 'FWD_1', 'FWD_2') ASC
        ", 'i', [(int) $homeLineup['id']]);
        
        $homePlayers = array_map(function($p) {
            return [
                'name' => $p['first_name'] . ' ' . $p['last_name'],
                'short' => strtoupper(substr($p['last_name'], 0, 3)),
                'num' => (int) $p['jersey_number'],
                'pos' => mapPositionAbbrev($p['position']),
                'posType' => mapPositionType($p['position']),
                'img' => $p['photo_pl'] ? app_url($p['photo_pl']) : '',
                'x' => (float) $p['field_x'],
                'y' => (float) $p['field_y']
            ];
        }, $homeStarters);
        
        $homeSubstitutes = db_fetch_all("
            SELECT p.first_name, p.last_name, p.jersey_number, p.position 
            FROM lineup_players lp 
            JOIN players p ON p.id = lp.player_id 
            WHERE lp.lineup_id = ? AND lp.is_starter = 0
        ", 'i', [(int) $homeLineup['id']]);
        
        $homeBench = array_map(function($p) {
            return [
                'name' => $p['first_name'] . ' ' . $p['last_name'],
                'num' => (int) $p['jersey_number'],
                'pos' => mapPositionAbbrev($p['position']),
                'posType' => mapPositionType($p['position'])
            ];
        }, $homeSubstitutes);
    } else {
        // Fallback: active players in default 4-3-3 coordinates
        $activePlayers = db_fetch_all("SELECT p.* FROM players p WHERE p.team_id = ? AND p.status = 'active' LIMIT 11", 'i', [$homeId]);
        $defaultCoords = [
            ['x' => 180, 'y' => 455],
            ['x' => 55, 'y' => 375], ['x' => 128, 'y' => 375], ['x' => 232, 'y' => 375], ['x' => 305, 'y' => 375],
            ['x' => 88, 'y' => 282], ['x' => 180, 'y' => 272], ['x' => 272, 'y' => 282],
            ['x' => 78, 'y' => 172], ['x' => 180, 'y' => 158], ['x' => 282, 'y' => 172]
        ];
        foreach ($activePlayers as $idx => $p) {
            $c = $defaultCoords[$idx] ?? ['x' => 180, 'y' => 250];
            $homePlayers[] = [
                'name' => $p['first_name'] . ' ' . $p['last_name'],
                'short' => strtoupper(substr($p['last_name'], 0, 3)),
                'num' => (int) $p['jersey_number'],
                'pos' => mapPositionAbbrev($p['position']),
                'posType' => mapPositionType($p['position']),
                'img' => $p['photo_pl'] ? app_url($p['photo_pl']) : '',
                'x' => $c['x'],
                'y' => $c['y']
            ];
        }
    }
    
    // 2. Fetch Away Lineup
    $awayLineup = db_fetch_one("
        SELECT ml.id, f.name formation_name, f.display_name formation_display 
        FROM match_lineups ml 
        JOIN formations f ON f.id = ml.formation_id 
        WHERE ml.match_id = ? AND ml.team_id = ?
    ", 'ii', [$matchId, $awayId]);
    
    $awayFormation = '4-4-2';
    $awayPlayers = [];
    $awayBench = [];
    
    if ($awayLineup) {
        $awayFormation = $awayLineup['formation_name'];
        $awayStarters = db_fetch_all("
            SELECT lp.*, p.first_name, p.last_name, p.jersey_number, p.position, p.photo_pl 
            FROM lineup_players lp 
            JOIN players p ON p.id = lp.player_id 
            WHERE lp.lineup_id = ? AND lp.is_starter = 1
            ORDER BY FIELD(lp.position_slot, 'GK', 'DEF_0', 'DEF_1', 'DEF_2', 'DEF_3', 'DEF_4', 'MID_0', 'MID_1', 'MID_2', 'MID_3', 'MID_4', 'FWD_0', 'FWD_1', 'FWD_2') ASC
        ", 'i', [(int) $awayLineup['id']]);
        
        $awayPlayers = array_map(function($p) {
            return [
                'name' => $p['first_name'] . ' ' . $p['last_name'],
                'short' => strtoupper(substr($p['last_name'], 0, 3)),
                'num' => (int) $p['jersey_number'],
                'pos' => mapPositionAbbrev($p['position']),
                'posType' => mapPositionType($p['position']),
                'img' => $p['photo_pl'] ? app_url($p['photo_pl']) : '',
                'x' => (float) $p['field_x'],
                'y' => (float) $p['field_y']
            ];
        }, $awayStarters);
        
        $awaySubstitutes = db_fetch_all("
            SELECT p.first_name, p.last_name, p.jersey_number, p.position 
            FROM lineup_players lp 
            JOIN players p ON p.id = lp.player_id 
            WHERE lp.lineup_id = ? AND lp.is_starter = 0
        ", 'i', [(int) $awayLineup['id']]);
        
        $awayBench = array_map(function($p) {
            return [
                'name' => $p['first_name'] . ' ' . $p['last_name'],
                'num' => (int) $p['jersey_number'],
                'pos' => mapPositionAbbrev($p['position']),
                'posType' => mapPositionType($p['position'])
            ];
        }, $awaySubstitutes);
    } else {
        // Fallback: active players in default 4-4-2 coordinates
        $activePlayers = db_fetch_all("SELECT p.* FROM players p WHERE p.team_id = ? AND p.status = 'active' LIMIT 11", 'i', [$awayId]);
        $defaultCoords = [
            ['x' => 180, 'y' => 455],
            ['x' => 55, 'y' => 370], ['x' => 125, 'y' => 370], ['x' => 220, 'y' => 370], ['x' => 290, 'y' => 370],
            ['x' => 62, 'y' => 275], ['x' => 135, 'y' => 265], ['x' => 210, 'y' => 265], ['x' => 285, 'y' => 275],
            ['x' => 130, 'y' => 158], ['x' => 215, 'y' => 158]
        ];
        foreach ($activePlayers as $idx => $p) {
            $c = $defaultCoords[$idx] ?? ['x' => 180, 'y' => 250];
            $awayPlayers[] = [
                'name' => $p['first_name'] . ' ' . $p['last_name'],
                'short' => strtoupper(substr($p['last_name'], 0, 3)),
                'num' => (int) $p['jersey_number'],
                'pos' => mapPositionAbbrev($p['position']),
                'posType' => mapPositionType($p['position']),
                'img' => $p['photo_pl'] ? app_url($p['photo_pl']) : '',
                'x' => $c['x'],
                'y' => $c['y']
            ];
        }
    }
    
    $mDate = date('D H:i', strtotime($match['match_date'] . ' ' . ($match['match_time'] ?: '15:00:00')));
    $matchdayText = 'Matchday ' . ($match['matchday'] ?: '1') . '  •  ' . $mDate;
    
    echo json_encode([
        'success' => true,
        'match' => [
            'id' => $match['id'],
            'home_id' => $homeId,
            'away_id' => $awayId,
            'home_name' => $match['home_name'],
            'away_name' => $match['away_name'],
            'home_logo' => $match['home_logo'] ? app_url($match['home_logo']) : '',
            'away_logo' => $match['away_logo'] ? app_url($match['away_logo']) : '',
            'stadium_name' => $match['stadium_name'] ?: 'National Stadium',
            'competition_name' => $match['competition_name'] ?: 'Rwanda Premier League',
            'matchday_text' => $matchdayText
        ],
        'home' => [
            'formation' => $homeFormation,
            'players' => $homePlayers,
            'bench' => $homeBench
        ],
        'away' => [
            'formation' => $awayFormation,
            'players' => $awayPlayers,
            'bench' => $awayBench
        ]
    ]);
    exit;
}

$currentPage = 'matches';
require_once __DIR__ . '/public_header.php';
include __DIR__ . '/pages/public_matches.php';
include __DIR__ . '/public_footer.php';
?>
