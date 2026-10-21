<?php
if (!defined('ABSPATH')) { exit; }

/** Client-specific behavior is opt-in at package build time; option names remain stable. */
final class EasyHeadless_Edition
{
    public static function is_film_efiko()
    {
        return defined('EASYHEADLESS_EDITION') && 'film-efiko' === EASYHEADLESS_EDITION;
    }

    public static function name()
    {
        return self::is_film_efiko() ? 'Film Efiko Custom' : 'EasyHeadless';
    }

    public static function is_admin_screen($screen = null)
    {
        $screen = $screen ?: (function_exists('get_current_screen') ? get_current_screen() : null);
        return $screen && (false !== strpos((string) $screen->id, 'easyheadless') || 'eh_issue' === $screen->post_type);
    }

    public static function quiet_notices($screen)
    {
        if (!self::is_film_efiko() || !self::is_admin_screen($screen)) { return; }
        // Screen-local suppression only: do not dismiss, delete or disable site-wide notices.
        remove_all_actions('admin_notices');
        remove_all_actions('all_admin_notices');
        remove_all_actions('user_admin_notices');
        if (function_exists('settings_errors')) { add_action('admin_notices', 'settings_errors'); }
        if (class_exists('EasyHeadless_Plugin')) {
            add_action('admin_notices', array(EasyHeadless_Plugin::instance(), 'render_admin_notices'));
        }
    }

    public static function toolbar($bar)
    {
        if (!self::is_film_efiko() || !is_admin() || !current_user_can('edit_posts')) { return; }
        $bar->add_node(array('id' => 'film-efiko-custom', 'title' => self::name(), 'href' => admin_url('admin.php?page=easyheadless')));
        $links = array('Dashboard' => 'admin.php?page=easyheadless');
        if (class_exists('EasyHeadless_Publication') && EasyHeadless_Publication::enabled()) {
            $links['Homepage sections'] = 'admin.php?page=easyheadless-publication-curation';
            $links['Issues'] = 'edit.php?post_type=eh_issue';
        }
        if (current_user_can('manage_options')) {
            $links['Publication'] = 'admin.php?page=easyheadless-publication';
            if (class_exists('EasyHeadless_Publication') && EasyHeadless_Publication::enabled()) {
                $links['Readers & contacts'] = 'admin.php?page=easyheadless-publication-contacts';
            }
        }
        foreach ($links as $label => $path) {
            $bar->add_node(array('id' => 'film-efiko-' . sanitize_title($label), 'parent' => 'film-efiko-custom', 'title' => $label, 'href' => admin_url($path)));
        }
    }

    public static function order_submenus()
    {
        global $submenu;
        if (!self::is_film_efiko() || empty($submenu['easyheadless'])) { return; }
        $order = array_flip(array('easyheadless', 'easyheadless-publication', 'easyheadless-publication-curation', 'edit.php?post_type=eh_issue', 'easyheadless-publication-contacts', 'easyheadless-site', 'easyheadless-navigation', 'easyheadless-content', 'easyheadless-portfolio', 'easyheadless-forms', 'easyheadless-modules', 'easyheadless-integrations'));
        $rows = array();
        foreach (array_values($submenu['easyheadless']) as $index => $row) {
            $rows[] = array('row' => $row, 'rank' => isset($order[$row[2]]) ? $order[$row[2]] : count($order) + $index);
        }
        usort($rows, function ($first, $second) { return $first['rank'] <=> $second['rank']; });
        $submenu['easyheadless'] = array_column($rows, 'row');
    }
}
