<header class="site-header">
    <nav>
        <a class="brand" href="admin_dashboard.php" aria-label="T_Software dashboard">
            <span class="brand-mark">T</span>
            <span>T_Software</span>
        </a>

        <div class="nav-datetime" aria-live="polite">
            <span id="current-date"></span>
            <span class="datetime-divider" aria-hidden="true"></span>
            <span id="current-time"></span>
        </div>
    </nav>

    <aside>
        <ul>
            <?php $current_page = basename($_SERVER['PHP_SELF']); ?>
            <?php
            // Pages that count as "Tournaments" in the menu
            $tournament_pages = [
                'admin_index.php', 'admin_tournaments.php', 'admin_create_tournament.php',
                'admin_view_tournaments.php', 'admin_each_tournament.php', 'admin_seeding.php',
                'admin_draw.php', 'admin_player_tournament_registration.php'
            ];
            ?>
            <li><a href="admin_dashboard.php" class="<?= $current_page == 'admin_dashboard.php' ? 'active' : '' ?>">Dashboard</a></li>
            <!-- <li><a href="admin_player_lists.php" class="">Players</a></li> -->
            <li><a href="admin_index.php" class="<?= in_array($current_page, $tournament_pages, true) ? 'active' : '' ?>">Tournaments</a></li>
            <li><a href="admin_results.php" class="<?= $current_page == 'admin_results.php' ? 'active' : '' ?>">Results</a></li>
            <li><a href="admin_rankings.php" class="<?= $current_page == 'admin_rankings.php' ? 'active' : '' ?>">Rankings</a></li>
        </ul>

        <div class="logout-section">
            <a href="../logout.php" class="logout-btn">Logout</a>
        </div>
    </aside>
</header>

<script>
    function updateDateTime() {
        const now = new Date();
        document.getElementById('current-date').textContent = now.toLocaleDateString(undefined, {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        document.getElementById('current-time').textContent = now.toLocaleTimeString(undefined, {
            hour: '2-digit', minute: '2-digit', second: '2-digit'
        });
    }

    updateDateTime();
    setInterval(updateDateTime, 1000);
</script>
