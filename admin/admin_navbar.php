<header class="site-header">
    <nav>
        <a class="brand" href="<?= isPlatformAdmin() ? 'admin_dashboard.php' : 'admin_index.php' ?>" aria-label="T_Software home">
            <span class="brand-mark">T</span>
            <span>T_Software</span>
        </a>

        <div class="nav-right">

            <div class="nav-datetime" aria-live="polite">
                <span id="current-date"></span>
                <span class="datetime-divider" aria-hidden="true"></span>
                <span id="current-time"></span>
            </div>

            <?php
            // Logged-in admin: photo (if the file exists) or initials, name and role
            $navName = $currentAdmin['admin_name'] ?? '';
            $navInitials = strtoupper(implode('', array_map(function ($word) {
                return substr($word, 0, 1);
            }, array_slice(preg_split('/\s+/', trim($navName)), 0, 2))));

            $navPhoto = null;
            $navPhotoPath = trim((string)($currentAdmin['admin_profile'] ?? ''));

            if ($navPhotoPath !== '' && is_file(__DIR__ . '/' . $navPhotoPath)) {
                $navPhoto = $navPhotoPath;
            }
            ?>

            <a href="admin_my_profile.php" class="nav-user" title="My Profile">
                <span class="nav-avatar">
                    <?php if ($navPhoto): ?>
                        <img src="<?= htmlspecialchars($navPhoto) ?>" alt="">
                    <?php else: ?>
                        <?= htmlspecialchars($navInitials) ?>
                    <?php endif; ?>
                </span>
                <span class="nav-user-text">
                    <strong><?= htmlspecialchars($navName) ?></strong>
                    <small><?= isPlatformAdmin() ? 'Platform Admin' : 'Tournament Organizer' ?></small>
                </span>
            </a>

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
                'admin_draw.php', 'admin_player_tournament_registration.php', 'admin_ranking_check.php'
            ];
            ?>
            <?php if (isPlatformAdmin()): ?>
                <li><a href="admin_dashboard.php" class="<?= $current_page == 'admin_dashboard.php' ? 'active' : '' ?>">Dashboard</a></li>
            <?php endif; ?>
            <!-- <li><a href="admin_player_lists.php" class="">Players</a></li> -->
            <li><a href="admin_index.php" class="<?= in_array($current_page, $tournament_pages, true) ? 'active' : '' ?>"><?= isPlatformAdmin() ? 'Tournaments' : 'My Tournaments' ?></a></li>
            <?php if (isPlatformAdmin()): ?>
                <li><a href="admin_admins.php" class="<?= $current_page == 'admin_admins.php' ? 'active' : '' ?>">Admins</a></li>
                <li><a href="admin_tournament_fees.php" class="<?= $current_page == 'admin_tournament_fees.php' ? 'active' : '' ?>">Tournament Fees</a></li>
            <?php endif; ?>
            <li class="menu-divider"><a href="admin_my_profile.php" class="<?= $current_page == 'admin_my_profile.php' ? 'active' : '' ?>">My Profile</a></li>
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
