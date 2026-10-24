<?php
if (!defined('ABSPATH')) { exit; }

final class EasyHeadless_Contact_Form
{
    const OPTION = 'easyheadless_contact_form';

    public static function register_routes()
    {
        register_rest_route('easyheadless/v1', '/contact-form', array('methods' => 'GET', 'callback' => array(__CLASS__, 'schema_response'), 'permission_callback' => '__return_true'));
    }

    public static function schema_response()
    {
        $schema = self::schema();
        if (is_wp_error($schema)) { return $schema; }
        $response = rest_ensure_response($schema);
        $response->header('Cache-Control', 'no-store, max-age=0');
        return $response;
    }

    public static function schema()
    {
        if (!EasyHeadless_Modules::is_enabled('forms')) { return new WP_Error('contact_form_unavailable', 'Contact form is unavailable.', array('status' => 503)); }
        $id = get_option(self::OPTION, '');
        if (!$id) { return new WP_Error('contact_form_not_configured', 'Choose a Contact page form in EasyHeadless Forms.', array('status' => 503)); }
        $forms = new EasyHeadless_Forms();
        $schema = $forms->get_form($id);
        if (is_wp_error($schema)) { return $schema; }
        if (empty($schema['supported'])) { return new WP_Error('contact_form_unsupported', 'This form contains unsupported fields or conditions. Review it in Fluent Forms.', array('status' => 503)); }
        return $schema;
    }

    public static function validate_selection($id)
    {
        if ('' === $id) { return ''; }
        if (!is_string($id) || !preg_match('/^fluentforms:[1-9][0-9]*$/D', $id)) { return new WP_Error('contact_form_invalid', 'Select an available Fluent Form.'); }
        $schema = (new EasyHeadless_FluentForms_Adapter())->get_form(substr($id, strlen('fluentforms:')));
        if (is_wp_error($schema)) { return $schema; }
        if (empty($schema['supported'])) { return new WP_Error('contact_form_unsupported', 'Remove unsupported fields, payment/CAPTCHA elements or conditional logic before selecting this form.'); }
        return $id;
    }

    public static function submit($request)
    {
        $schema = self::schema();
        if (is_wp_error($schema)) { return $schema; }
        $body = $request->get_json_params();
        if (!is_array($body) || ($body['formId'] ?? '') !== $schema['id'] || ($body['version'] ?? '') !== $schema['version']) {
            return new WP_Error('contact_form_changed', 'The form has changed. Please reload this page and try again.', array('status' => 409));
        }
        $submitted = $body['fields'] ?? null;
        if (!is_array($submitted) || strlen(json_encode($submitted)) > 100000) { return new WP_Error('contact_fields_invalid', 'Invalid form data.', array('status' => 400)); }
        $allowed = array_column($schema['fields'], 'name');
        if (array_diff(array_keys($submitted), $allowed)) { return new WP_Error('contact_fields_invalid', 'Unknown form fields.', array('status' => 400)); }
        $fields = array();
        foreach ($schema['fields'] as $field) {
            if (!$field['path']) { continue; }
            $value = 'hidden' === $field['type'] ? $field['value'] : ($submitted[$field['name']] ?? (!empty($field['multiple']) ? array() : ''));
            if (is_array($value)) {
                if (empty($field['multiple']) || count($value) > 150 || count(array_filter($value, 'is_scalar')) !== count($value)) { return new WP_Error('contact_fields_invalid', 'Invalid field value.', array('status' => 400)); }
                $value = array_map('strval', $value);
            } elseif (!is_scalar($value)) { return new WP_Error('contact_fields_invalid', 'Invalid field value.', array('status' => 400)); }
            else { $value = (string) $value; }
            if (!empty($field['options'])) {
                $choices = array_column($field['options'], 'value');
                foreach (is_array($value) ? $value : array($value) as $choice) {
                    if ('' !== $choice && !in_array((string) $choice, $choices, true)) { return new WP_Error('contact_fields_invalid', 'Choose a valid option.', array('status' => 422, 'fieldErrors' => array($field['name'] => array('Choose a valid option.')))); }
                }
            }
            $cursor =& $fields;
            foreach ($field['path'] as $index => $part) {
                if ($index === count($field['path']) - 1) { $cursor[$part] = $value; } else { if (!isset($cursor[$part])) { $cursor[$part] = array(); } $cursor =& $cursor[$part]; }
            }
            unset($cursor);
        }
        return (new EasyHeadless_Forms())->submit($schema['id'], $fields, true);
    }
}
