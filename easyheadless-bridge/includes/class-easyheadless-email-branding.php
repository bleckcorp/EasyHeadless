<?php
if (!defined('ABSPATH')) { exit; }

/** Fixed email presentation with editor-controlled copy and Media Library artwork. */
class EasyHeadless_Email_Branding
{
    public static function custom()
    {
        return class_exists('EasyHeadless_Edition') && EasyHeadless_Edition::is_film_efiko();
    }

    public static function plain_message($message)
    {
        // Preserve paragraph breaks in messages saved by the previous rich-text editor.
        $message = preg_replace('~<(?:br\s*/?|/p|/div)>~i', "\n", (string) $message);
        return trim(wp_strip_all_tags($message));
    }

    public static function logo_field($settings, $option)
    {
        $url = isset($settings['auth_logo_url']) ? $settings['auth_logo_url'] : '';
        ?>
        <tr><th scope="row"><label for="eh-auth_logo_url">Email logo</label></th><td>
        <div data-eh-email-logo>
            <div class="eh-email-logo-preview"><img data-logo-preview src="<?php echo esc_url($url); ?>" alt="Selected email logo" <?php echo $url ? '' : 'hidden'; ?>></div>
            <input class="regular-text" id="eh-auth_logo_url" type="url" name="<?php echo esc_attr($option); ?>[auth_logo_url]" value="<?php echo esc_attr($url); ?>" aria-describedby="eh-email-logo-help">
            <p><button type="button" class="button" data-logo-select>Choose from Media Library</button> <button type="button" class="button" data-logo-remove>Remove logo</button></p>
            <p class="description" id="eh-email-logo-help">Choose an image from WordPress Media Library, or paste its public URL. A transparent PNG with a light logo works best on the black header. Without a logo, the publication name is shown. Save publication settings to apply changes.</p>
            <p role="status" aria-live="polite" data-logo-status></p>
        </div></td></tr>
        <?php
    }
}
