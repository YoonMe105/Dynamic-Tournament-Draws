<?php

/*
|--------------------------------------------------------------------------
| Player Navbar
|--------------------------------------------------------------------------
| Stays at the top of the page. The link for the current page is
| highlighted; the tournament pages (details, register, payment) count as
| "Tournaments".
*/

$currentPage = basename($_SERVER['PHP_SELF']);

$navLinks = [
    'player_index.php' => [
        'label' => 'Tournaments',
        'icon' => 'fa-trophy',
        'pages' => ['player_index.php', 'tournament_details.php', 'tournament_register.php', 'tournament_payment.php']
    ],
    'my_tournaments.php' => [
        'label' => 'My Tournaments',
        'icon' => 'fa-list-check',
        'pages' => ['my_tournaments.php']
    ],
    'my_profile.php' => [
        'label' => 'My Profile',
        'icon' => 'fa-user',
        'pages' => ['my_profile.php']
    ]
];

$navName = trim(($_SESSION['firstname'] ?? '') . ' ' . ($_SESSION['lastname'] ?? ''));

$navInitials = strtoupper(
    substr($_SESSION['firstname'] ?? '', 0, 1) . substr($_SESSION['lastname'] ?? '', 0, 1)
);

?>
    <header class="player-header">

        <div class="header-inner">


            <!-- Brand -->

            <a href="./player_index.php" class="header-logo">
                <span class="logo-mark"><i class="fa-solid fa-trophy"></i></span>
                <span class="logo-text">Players<small>Portal</small></span>
            </a>


            <!-- Menu button (phones) -->

            <button
                type="button"
                class="nav-toggle"
                id="navToggle"
                aria-label="Open menu"
                aria-expanded="false"
                aria-controls="headerNav"
            >
                <i class="fa-solid fa-bars"></i>
            </button>


            <!-- Links -->

            <nav class="header-nav" id="headerNav">

                <?php foreach ($navLinks as $href => $link): ?>

                    <a
                        href="./<?= $href ?>"
                        class="nav-link <?= in_array($currentPage, $link['pages'], true) ? 'active' : '' ?>"
                        <?= in_array($currentPage, $link['pages'], true) ? 'aria-current="page"' : '' ?>
                    >
                        <i class="fa-solid <?= $link['icon'] ?>"></i>
                        <?= $link['label'] ?>
                    </a>

                <?php endforeach; ?>


                <div class="nav-user">

                    <?php if ($navName !== ''): ?>

                        <a href="./my_profile.php" class="nav-avatar" title="<?= htmlspecialchars($navName) ?>">
                            <span class="avatar-initials"><?= htmlspecialchars($navInitials) ?></span>
                            <span class="avatar-name"><?= htmlspecialchars($navName) ?></span>
                        </a>

                    <?php endif; ?>

                    <a href="../logout.php" class="logout-btn">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i>
                        Logout
                    </a>

                </div>

            </nav>

        </div>

    </header>


    <script>
        // Phones: open / close the menu
        (function () {

            const toggle = document.getElementById('navToggle');
            const nav = document.getElementById('headerNav');

            toggle.addEventListener('click', function () {

                const open = nav.classList.toggle('open');

                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
                toggle.innerHTML = open
                    ? '<i class="fa-solid fa-xmark"></i>'
                    : '<i class="fa-solid fa-bars"></i>';
            });

        })();
    </script>
