<?php
$currentPage = 'players';
include __DIR__ . '/public_header.php';
if (isset($_GET['id'])) {
    include __DIR__ . '/pages/public_player_details.php';
} else {
    include __DIR__ . '/pages/public_players.php';
}
include __DIR__ . '/public_footer.php';
?>
