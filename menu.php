<?php
$menu = new \Tsugi\UI\MenuSet();
$menu->setHome('Quick Poll', 'index.php');

if ($USER->instructor) {
    if ('student-home.php' != basename($_SERVER['PHP_SELF'])) {
        $menu->addRight('<span class="fas fa-user-graduate" aria-hidden="true"></span> Student View', 'student-home.php');

        $menu->addRight('<span class="fas fa-edit" aria-hidden="true"></span> Manage', 'build.php');
    } else {
        $menu->addRight('Exit Student View <span class="fas fa-sign-out-alt" aria-hidden="true"></span>', 'build.php');
    }
}
