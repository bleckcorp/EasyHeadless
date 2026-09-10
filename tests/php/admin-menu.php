<?php

define('ABSPATH', __DIR__ . '/');

$GLOBALS['eh_admin_menu'] = array();
$GLOBALS['eh_admin_submenus'] = array();
$GLOBALS['eh_admin_actions'] = array();
$GLOBALS['eh_test_modules'] = array(
    'portfolio' => true,
    'tutor' => false,
    'forms' => true,
);

class EasyHeadless_Plugin
{
    const ADMIN_MENU_SLUG = 'easyheadless';
}

class EasyHeadless_Modules
{
    public static function is_enabled($module)
    {
        return !empty($GLOBALS['eh_test_modules'][$module]);
    }
}

function add_menu_page($page_title, $menu_title, $capability, $slug, $callback, $icon = '', $position = null)
{
    $GLOBALS['eh_admin_menu'][] = compact('page_title', 'menu_title', 'capability', 'slug', 'callback', 'icon', 'position');
}

function add_submenu_page($parent, $page_title, $menu_title, $capability, $slug, $callback, $position = null)
{
    $entry = compact('parent', 'page_title', 'menu_title', 'capability', 'slug', 'callback');
    if (0 === $position) {
        array_unshift($GLOBALS['eh_admin_submenus'], $entry);
    } else {
        $GLOBALS['eh_admin_submenus'][] = $entry;
    }
}

function add_action($hook, $callback)
{
    $GLOBALS['eh_admin_actions'][] = compact('hook', 'callback');
}

require dirname(__DIR__, 2) . '/easyheadless-bridge/includes/class-easyheadless-admin.php';

$failures = array();
function admin_menu_assert($condition, $label)
{
    global $failures;
    if (!$condition) {
        $failures[] = $label;
    }
}

// WordPress registers custom post-type submenus before the plugin admin menu.
add_submenu_page('easyheadless', 'Issues', 'Issues', 'edit_posts', 'edit.php?post_type=eh_issue', null);
EasyHeadless_Admin::register_menu();
admin_menu_assert('easyheadless' === $GLOBALS['eh_admin_submenus'][0]['slug'], 'dashboard remains first when Issues already owns a submenu');
admin_menu_assert(array('EasyHeadless_Admin', 'render_overview') === $GLOBALS['eh_admin_submenus'][0]['callback'], 'dashboard menu invokes overview');

admin_menu_assert(1 === count($GLOBALS['eh_admin_menu']), 'one EasyHeadless top-level menu is registered');
admin_menu_assert('easyheadless' === $GLOBALS['eh_admin_menu'][0]['slug'], 'dashboard owns the EasyHeadless menu slug');

$slugs = array_column($GLOBALS['eh_admin_submenus'], 'slug');
$parents = array_unique(array_column($GLOBALS['eh_admin_submenus'], 'parent'));

admin_menu_assert(array('easyheadless') === $parents, 'working screens use the real parent so WordPress resolves page titles');
admin_menu_assert(in_array('easyheadless-content', $slugs, true), 'content library is registered');
admin_menu_assert(in_array('easyheadless-modules', $slugs, true), 'modules screen is registered');
admin_menu_assert(in_array('easyheadless-portfolio', $slugs, true), 'enabled portfolio screen is registered');
admin_menu_assert(in_array('easyheadless-forms', $slugs, true), 'enabled forms screen is registered');
admin_menu_assert(!in_array('easyheadless-courses', $slugs, true), 'disabled Tutor screen is hidden');
admin_menu_assert(in_array('admin_head', array_column($GLOBALS['eh_admin_actions'], 'hook'), true), 'duplicate native submenu is hidden without unregistering screens');

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "EasyHeadless admin menu tests passed." . PHP_EOL;
