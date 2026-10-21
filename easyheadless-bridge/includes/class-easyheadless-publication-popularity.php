<?php
if (!defined('ABSPATH')) { exit; }

/** Timestamped, anonymous view events; lifetime totals cannot supply a rolling window. */
final class EasyHeadless_Publication_Popularity
{
    const SCHEMA_OPTION = 'easyheadless_view_events_schema';

    private static function ready()
    {
        global $wpdb;
        if ('1' === (string) get_option(self::SCHEMA_OPTION, '')) { return true; }
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$wpdb->prefix}eh_publication_view_events (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            viewed_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY viewed_at_post (viewed_at,post_id)
        ) {$charset};");
        $table = $wpdb->prefix . 'eh_publication_view_events';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) { return false; }
        update_option(self::SCHEMA_OPTION, '1', false);
        return true;
    }

    public static function record($post_id)
    {
        global $wpdb;
        if (!self::ready()) { return false; }
        $now = time();
        $result = $wpdb->insert($wpdb->prefix . 'eh_publication_view_events', array('post_id' => $post_id, 'viewed_at' => gmdate('Y-m-d H:i:s', $now)), array('%d', '%s'));
        if (false === $result) { return false; }
        // Keep a short buffer beyond the seven-day window. No reader identifiers are stored.
        if ((int) get_option('easyheadless_view_events_cleaned', 0) < $now - DAY_IN_SECONDS) {
            $deleted = $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}eh_publication_view_events WHERE viewed_at < %s", gmdate('Y-m-d H:i:s', $now - 8 * DAY_IN_SECONDS)));
            if (false !== $deleted) { update_option('easyheadless_view_events_cleaned', $now, false); }
        }
        return true;
    }

    public static function ranked_ids($count, $exclude = array(), $category_id = 0)
    {
        global $wpdb;
        if (!self::ready()) { return array(); }
        $now = time();
        $params = array(gmdate('Y-m-d H:i:s', $now - 7 * DAY_IN_SECONDS), gmdate('Y-m-d H:i:s', $now));
        $where = '';
        $exclude = array_values(array_filter(array_map('absint', $exclude)));
        if ($exclude) {
            $where .= ' AND p.ID NOT IN (' . implode(',', array_fill(0, count($exclude), '%d')) . ')';
            $params = array_merge($params, $exclude);
        }
        if ($category_id) {
            $children = get_term_children($category_id, 'category');
            $categories = array_merge(array(absint($category_id)), is_wp_error($children) ? array() : array_map('absint', $children));
            $where .= " AND EXISTS (SELECT 1 FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tr.object_id = p.ID AND tt.taxonomy = 'category' AND tt.term_id IN (" . implode(',', array_fill(0, count($categories), '%d')) . '))';
            $params = array_merge($params, $categories);
        }
        $params[] = min(12, max(1, absint($count)));
        // Publication date is only a tie-breaker: old articles remain eligible.
        $sql = "SELECT p.ID FROM {$wpdb->prefix}eh_publication_view_events v
            INNER JOIN {$wpdb->posts} p ON p.ID = v.post_id
            WHERE v.viewed_at >= %s AND v.viewed_at <= %s
            AND p.post_type = 'post' AND p.post_status = 'publish' {$where}
            GROUP BY p.ID, p.post_date_gmt
            ORDER BY COUNT(*) DESC, p.post_date_gmt DESC, p.ID DESC LIMIT %d";
        return array_map('absint', (array) $wpdb->get_col($wpdb->prepare($sql, $params)));
    }
}
