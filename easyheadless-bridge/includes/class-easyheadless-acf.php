<?php

if (!defined('ABSPATH')) {
    exit;
}

final class EasyHeadless_ACF
{
    public static function is_available()
    {
        return function_exists('acf_add_local_field_group');
    }

    public static function register_field_groups()
    {
        if (!self::is_available()) {
            return;
        }

        self::register_site_settings();

        acf_add_local_field_group(
            array(
                'key' => 'group_easyheadless_landing_sections',
                'title' => 'Landing Page Sections',
                'fields' => array(
                    self::repeater(
                        'field_eh_sections',
                        'sections',
                        'Sections',
                        array(
                            self::select('field_eh_section_type', 'type', 'Type', array(
                                'hero' => 'Hero',
                                'services' => 'Services',
                                'testimonials' => 'Testimonials',
                                'faqs' => 'FAQs',
                                'team' => 'Team',
                                'content' => 'Content',
                                'contact' => 'Contact',
                                'service_times' => 'Service Times',
                                'latest_sermons' => 'Latest Sermons',
                                'events' => 'Events',
                                'ministries' => 'Ministries',
                                'leaders' => 'Leaders / Pastor',
                                'location' => 'Location / Map',
                                'giving' => 'Giving',
                                'policy_callout' => 'Policy Callout',
                                'cta' => 'CTA Banner',
                            )),
                            self::text('field_eh_section_heading', 'heading', 'Heading'),
                            self::text('field_eh_section_subheading', 'subheading', 'Subheading'),
                            self::textarea('field_eh_section_copy', 'copy', 'Copy'),
                            self::text('field_eh_section_script_text', 'script_text', 'Script Text'),
                            self::image('field_eh_section_image_url', 'image_url', 'Image'),
                            self::text('field_eh_section_cta_label', 'cta_label', 'CTA Label'),
                            self::url('field_eh_section_cta_url', 'cta_url', 'CTA URL'),
                            self::text('field_eh_section_secondary_cta_label', 'secondary_cta_label', 'Secondary CTA Label'),
                            self::url('field_eh_section_secondary_cta_url', 'secondary_cta_url', 'Secondary CTA URL'),
                        )
                    ),
                ),
                'location' => array(
                    array(
                        array(
                            'param' => 'post_type',
                            'operator' => '==',
                            'value' => 'page',
                        ),
                    ),
                ),
                'show_in_rest' => 1,
            )
        );

        self::register_simple_meta_group('eh_service', 'Service Details', array(
            self::tab('field_eh_service_tab_content', 'Content'),
            self::text('field_eh_service_summary', 'summary', 'Summary'),
            self::tab('field_eh_service_tab_display', 'Display'),
            self::text('field_eh_service_price_hint', 'price_hint', 'Price Hint', self::width(50)),
        ));

        self::register_simple_meta_group('eh_testimonial', 'Testimonial Details', array(
            self::tab('field_eh_testimonial_tab_person', 'Person'),
            self::text('field_eh_testimonial_name', 'client_name', 'Client Name', self::width(50)),
            self::text('field_eh_testimonial_role', 'client_role', 'Client Role', self::width(50)),
            self::tab('field_eh_testimonial_tab_quote', 'Quote'),
            self::textarea('field_eh_testimonial_quote', 'quote', 'Quote'),
        ));

        self::register_simple_meta_group('eh_faq', 'FAQ Details', array(
            self::tab('field_eh_faq_tab_answer', 'Answer'),
            self::textarea('field_eh_faq_answer', 'answer', 'Answer'),
        ));

        self::register_simple_meta_group('eh_team_member', 'Team Member Details', array(
            self::tab('field_eh_team_tab_profile', 'Profile'),
            self::text('field_eh_team_role', 'role', 'Role', self::width(50)),
            self::textarea('field_eh_team_bio', 'bio', 'Bio'),
        ));

        self::register_church_field_groups();
    }

    private static function register_church_field_groups()
    {
        self::register_simple_meta_group('eh_church_settings', 'Church Settings', array(
            self::tab('field_eh_church_tab_identity', 'Identity'),
            self::text('field_eh_church_name', 'church_name', 'Church Name', self::width(50)),
            self::text('field_eh_church_tagline', 'tagline', 'Tagline', self::width(50)),
            self::text('field_eh_church_denomination', 'denomination', 'Denomination / Affiliation', self::width(50)),
            self::text('field_eh_church_footer_text', 'footer_text', 'Footer Text', self::width(50)),
            self::textarea('field_eh_church_mission_statement', 'mission_statement', 'Mission Statement', array(
                'instructions' => 'Short statement used across homepage, about, and SEO content.',
            )),
            self::textarea('field_eh_church_welcome_message', 'welcome_message', 'Welcome Message'),

            self::tab('field_eh_church_tab_contact', 'Location & Contact'),
            self::text('field_eh_church_venue_name', 'venue_name', 'Venue Name', self::width(50)),
            self::text('field_eh_church_phone', 'phone', 'Phone', self::width(50)),
            self::text('field_eh_church_email', 'email', 'Email', self::width(50)),
            self::url('field_eh_church_website', 'website', 'Website', self::width(50)),
            self::textarea('field_eh_church_address', 'address', 'Address'),
            self::url('field_eh_church_map_url', 'map_url', 'Map URL'),

            self::tab('field_eh_church_tab_social', 'Social'),
            self::repeater(
                'field_eh_church_social_links',
                'social_links',
                'Social Links',
                array(
                    self::text('field_eh_church_social_label', 'label', 'Label'),
                    self::url('field_eh_church_social_url', 'url', 'URL'),
                )
            ),

            self::tab('field_eh_church_tab_pastor', 'Pastor'),
            self::text('field_eh_church_pastor_name', 'primary_pastor_name', 'Primary Pastor Name', self::width(50)),
            self::text('field_eh_church_pastor_role', 'primary_pastor_role', 'Primary Pastor Role', self::width(50)),
            self::image('field_eh_church_pastor_image_url', 'primary_pastor_image_url', 'Primary Pastor Image'),
            self::textarea('field_eh_church_pastor_bio', 'primary_pastor_bio', 'Primary Pastor Bio'),
            self::textarea('field_eh_church_pastor_message', 'primary_pastor_message', 'Primary Pastor Message / Signature'),

            self::tab('field_eh_church_tab_giving', 'Giving'),
            self::url('field_eh_church_online_giving_url', 'online_giving_url', 'Online Giving URL'),
            self::text('field_eh_church_bank_account_name', 'bank_account_name', 'Bank Account Name', self::width(50)),
            self::text('field_eh_church_bank_sort_code', 'bank_sort_code', 'Bank Sort Code', self::width(50)),
            self::text('field_eh_church_bank_account_number', 'bank_account_number', 'Bank Account Number', self::width(50)),
            self::text('field_eh_church_bank_reference', 'bank_reference', 'Bank Reference'),
            self::textarea('field_eh_church_gift_aid_text', 'gift_aid_text', 'Gift Aid Text'),

            self::tab('field_eh_church_tab_online', 'Online Meetings'),
            self::text('field_eh_church_sunday_zoom_id', 'sunday_zoom_id', 'Sunday Zoom Meeting ID', self::width(50)),
            self::url('field_eh_church_sunday_zoom_url', 'sunday_zoom_url', 'Sunday Zoom URL', self::width(50)),
            self::text('field_eh_church_bible_study_zoom_id', 'bible_study_zoom_id', 'Bible Study Zoom Meeting ID', self::width(50)),
            self::url('field_eh_church_bible_study_zoom_url', 'bible_study_zoom_url', 'Bible Study Zoom URL', self::width(50)),

            self::tab('field_eh_church_tab_safeguarding', 'Safeguarding'),
            self::text('field_eh_church_safeguarding_contact', 'safeguarding_contact', 'Safeguarding Contact', self::width(50)),
            self::url('field_eh_church_safeguarding_policy_url', 'safeguarding_policy_url', 'Safeguarding Policy URL', self::width(50)),
        ));

        self::register_simple_meta_group('eh_sermon', 'Sermon Details', array(
            self::tab('field_eh_sermon_tab_details', 'Details'),
            self::text('field_eh_sermon_speaker', 'speaker', 'Speaker', self::width(50)),
            self::date('field_eh_sermon_date', 'sermon_date', 'Date Recorded', self::width(50)),
            self::textarea('field_eh_sermon_scripture', 'scripture', 'Scripture / Description'),
            self::text('field_eh_sermon_series', 'series', 'Series', self::width(50)),
            self::text('field_eh_sermon_topic', 'topic', 'Topic', self::width(50)),
            self::true_false('field_eh_sermon_featured', 'featured_sermon', 'Featured Sermon'),

            self::tab('field_eh_sermon_tab_media', 'Media'),
            self::file('field_eh_sermon_audio_url', 'audio_url', 'Audio File', array(
                'mime_types' => 'mp3,m4a,wav,ogg,aac',
                'instructions' => 'Choose an audio file from the WordPress Media Library. The API exposes its URL automatically.',
            )),
            self::file('field_eh_sermon_video_url', 'video_url', 'Video File', array(
                'mime_types' => 'mp4,m4v,mov,webm',
                'instructions' => 'Optional uploaded video file. For YouTube/Vimeo, use the related page field or page content.',
            )),
            self::file('field_eh_sermon_download_url', 'download_url', 'Download File', array(
                'mime_types' => 'mp3,m4a,wav,ogg,aac,pdf,doc,docx',
                'instructions' => 'Optional separate downloadable file. If empty, the API falls back to the audio file.',
            )),
            self::image('field_eh_sermon_thumbnail_url', 'thumbnail_url', 'Thumbnail Image'),
            self::text('field_eh_sermon_file_size', 'file_size', 'File Size', array(
                'instructions' => 'Optional override. If empty, the API uses the selected audio/download file size when available.',
            )),

            self::tab('field_eh_sermon_tab_links', 'Links'),
            self::url('field_eh_sermon_related_page', 'related_page', 'Related Page'),
        ));

        self::register_simple_meta_group('eh_event', 'Event Details', array(
            self::tab('field_eh_event_tab_schedule', 'Schedule'),
            self::text('field_eh_event_type', 'event_type', 'Event Type', self::width(50)),
            self::text('field_eh_event_frequency', 'frequency', 'Frequency', self::width(50)),
            self::date('field_eh_event_start_date', 'start_date', 'Start Date', self::width(50)),
            self::text('field_eh_event_start_time', 'start_time', 'Start Time', self::width(50)),
            self::text('field_eh_event_end_time', 'end_time', 'End Time', self::width(50)),
            self::text('field_eh_event_location', 'location', 'Location'),

            self::tab('field_eh_event_tab_online', 'Online / CTA'),
            self::true_false('field_eh_event_is_online', 'is_online', 'Online / Hybrid', self::width(50)),
            self::true_false('field_eh_event_featured', 'featured_event', 'Featured Event', self::width(50)),
            self::text('field_eh_event_zoom_id', 'zoom_id', 'Zoom Meeting ID', self::width(50)),
            self::url('field_eh_event_zoom_url', 'zoom_url', 'Zoom URL', self::width(50)),
            self::text('field_eh_event_cta_label', 'cta_label', 'CTA Label', self::width(50)),
        ));

        self::register_simple_meta_group('eh_ministry', 'Ministry Details', array(
            self::tab('field_eh_ministry_tab_content', 'Content'),
            self::textarea('field_eh_ministry_summary', 'summary', 'Summary'),
            self::text('field_eh_ministry_audience', 'audience', 'Audience / Age Group', self::width(50)),
            self::text('field_eh_ministry_meeting_time', 'meeting_time', 'Meeting Time', self::width(50)),
            self::text('field_eh_ministry_leader', 'leader', 'Leader', self::width(50)),

            self::tab('field_eh_ministry_tab_media', 'Media & CTA'),
            self::image('field_eh_ministry_image_url', 'image_url', 'Image'),
            self::text('field_eh_ministry_cta_label', 'cta_label', 'CTA Label', self::width(50)),
            self::url('field_eh_ministry_cta_url', 'cta_url', 'CTA URL', self::width(50)),
        ));

        self::register_simple_meta_group('eh_leader', 'Leader Details', array(
            self::tab('field_eh_leader_tab_profile', 'Profile'),
            self::text('field_eh_leader_role', 'role', 'Role', self::width(50)),
            self::image('field_eh_leader_image_url', 'image_url', 'Image'),
            self::textarea('field_eh_leader_bio', 'bio', 'Bio'),

            self::tab('field_eh_leader_tab_contact', 'Contact'),
            self::text('field_eh_leader_email', 'email', 'Email', self::width(50)),
            self::text('field_eh_leader_phone', 'phone', 'Phone', self::width(50)),
        ));

        self::register_simple_meta_group('eh_service_time', 'Service Time Details', array(
            self::tab('field_eh_service_time_tab_schedule', 'Schedule'),
            self::text('field_eh_service_time_day_frequency', 'day_frequency', 'Day / Frequency', self::width(50)),
            self::text('field_eh_service_time_time', 'time', 'Time', self::width(50)),
            self::text('field_eh_service_time_location', 'location', 'Location'),
            self::textarea('field_eh_service_time_details', 'details', 'Details'),

            self::tab('field_eh_service_time_tab_online', 'Online'),
            self::text('field_eh_service_time_zoom_id', 'zoom_id', 'Zoom Meeting ID', self::width(50)),
            self::url('field_eh_service_time_zoom_url', 'zoom_url', 'Zoom URL', self::width(50)),
        ));

        self::register_simple_meta_group('eh_policy', 'Policy Details', array(
            self::tab('field_eh_policy_tab_details', 'Details'),
            self::select('field_eh_policy_type', 'policy_type', 'Policy Type', array(
                'safeguarding' => 'Safeguarding',
                'privacy' => 'Privacy',
                'terms' => 'Terms',
                'other' => 'Other',
            ), self::width(50)),
            self::date('field_eh_policy_reviewed_on', 'reviewed_on', 'Last Reviewed Date', self::width(50)),
            self::textarea('field_eh_policy_summary', 'summary', 'Summary'),
            self::text('field_eh_policy_owner', 'owner', 'Owner / Signatory'),
        ));
    }

    private static function register_site_settings()
    {
        if (!function_exists('acf_add_options_page')) {
            return;
        }

        acf_add_options_page(
            array(
                'page_title' => 'Site Settings',
                'menu_title' => 'Site Settings',
                'menu_slug' => 'easyheadless-site-settings',
                'capability' => 'edit_posts',
                'redirect' => false,
                'show_in_graphql' => true,
            )
        );

        acf_add_local_field_group(
            array(
                'key' => 'group_easyheadless_site_settings',
                'title' => 'EasyHeadless Site Settings',
                'fields' => array(
                    self::text('field_eh_company_name', 'company_name', 'Company Name'),
                    self::text('field_eh_phone', 'phone', 'Phone'),
                    self::text('field_eh_email', 'email', 'Email'),
                    self::text('field_eh_address', 'address', 'Address'),
                    self::textarea('field_eh_short_description', 'short_description', 'Short Description'),
                    self::repeater(
                        'field_eh_social_links',
                        'social_links',
                        'Social Links',
                        array(
                            self::text('field_eh_social_label', 'label', 'Label'),
                            self::url('field_eh_social_url', 'url', 'URL'),
                        )
                    ),
                ),
                'location' => array(
                    array(
                        array(
                            'param' => 'options_page',
                            'operator' => '==',
                            'value' => 'easyheadless-site-settings',
                        ),
                    ),
                ),
                'show_in_rest' => 1,
            )
        );
    }

    private static function register_simple_meta_group($post_type, $title, $fields)
    {
        acf_add_local_field_group(
            array(
                'key' => 'group_easyheadless_' . $post_type,
                'title' => $title,
                'fields' => $fields,
                'location' => array(
                    array(
                        array(
                            'param' => 'post_type',
                            'operator' => '==',
                            'value' => $post_type,
                        ),
                    ),
                ),
                'show_in_rest' => 1,
            )
        );
    }

    public static function get_fields($post_id)
    {
        if (function_exists('get_fields')) {
            $fields = get_fields($post_id);
            return is_array($fields) ? $fields : new stdClass();
        }

        return new stdClass();
    }

    public static function get_option_fields()
    {
        if (function_exists('get_fields')) {
            $fields = get_fields('option');
            return is_array($fields) ? $fields : array();
        }

        return array();
    }

    private static function text($key, $name, $label, $args = array())
    {
        return array_merge(array('key' => $key, 'name' => $name, 'label' => $label, 'type' => 'text'), $args);
    }

    private static function textarea($key, $name, $label, $args = array())
    {
        return array_merge(array('key' => $key, 'name' => $name, 'label' => $label, 'type' => 'textarea', 'rows' => 4), $args);
    }

    private static function url($key, $name, $label, $args = array())
    {
        return array_merge(array('key' => $key, 'name' => $name, 'label' => $label, 'type' => 'url'), $args);
    }

    private static function image($key, $name, $label, $args = array())
    {
        return array_merge(array(
            'key' => $key,
            'name' => $name,
            'label' => $label,
            'type' => 'image',
            'return_format' => 'array',
            'preview_size' => 'medium',
            'library' => 'all',
        ), $args);
    }

    private static function file($key, $name, $label, $args = array())
    {
        return array_merge(array(
            'key' => $key,
            'name' => $name,
            'label' => $label,
            'type' => 'file',
            'return_format' => 'array',
            'library' => 'all',
        ), $args);
    }

    private static function select($key, $name, $label, $choices, $args = array())
    {
        return array_merge(array('key' => $key, 'name' => $name, 'label' => $label, 'type' => 'select', 'choices' => $choices), $args);
    }

    private static function date($key, $name, $label, $args = array())
    {
        return array_merge(array(
            'key' => $key,
            'name' => $name,
            'label' => $label,
            'type' => 'date_picker',
            'display_format' => 'd/m/Y',
            'return_format' => 'Y-m-d',
        ), $args);
    }

    private static function true_false($key, $name, $label, $args = array())
    {
        return array_merge(array(
            'key' => $key,
            'name' => $name,
            'label' => $label,
            'type' => 'true_false',
            'ui' => 1,
        ), $args);
    }

    private static function tab($key, $label)
    {
        return array(
            'key' => $key,
            'label' => $label,
            'type' => 'tab',
            'placement' => 'top',
        );
    }

    private static function width($width)
    {
        return array(
            'wrapper' => array(
                'width' => (string) $width,
            ),
        );
    }

    private static function repeater($key, $name, $label, $sub_fields)
    {
        return array(
            'key' => $key,
            'name' => $name,
            'label' => $label,
            'type' => 'repeater',
            'layout' => 'block',
            'button_label' => 'Add ' . $label,
            'sub_fields' => $sub_fields,
        );
    }
}
