<header class="site-header">
    <nav>
        <a class="brand" href="index.php" aria-label="T_Software dashboard">
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
            <li><a href="admin_index.php" class="<?= $current_page == 'admin_index.php' ? 'active' : '' ?>">Dashboard</a></li>
            <!-- <li><a href="admin_player_lists.php" class="">Players</a></li> -->
            <li><a href="admin_tournaments.php" class="<?= in_array($current_page, ['admin_tournaments.php', 'admin_view_tournaments.php'], true) ? 'active' : '' ?>">Tournaments</a></li>
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
