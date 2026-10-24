<?php
if (!defined('ABSPATH')) { exit; }

/** Public, presentation-neutral standard fields. Never expose the raw form/settings. */
final class EasyHeadless_Form_Schema
{
    public static function read($form)
    {
        $raw = json_decode($form->form_fields, true);
        $fields = array();
        $unsupported = array();
        if (!empty($form->has_payment)) { $unsupported[] = 'payments'; }
        if (!is_array($raw) || !isset($raw['fields']) || !is_array($raw['fields'])) {
            $unsupported[] = 'invalid_schema';
        } else {
            self::walk($raw['fields'], array(), $fields, $unsupported);
        }
        $label = isset($raw['submitButton']['settings']['button_ui']['text']) ? $raw['submitButton']['settings']['button_ui']['text'] : 'Send message';
        $result = array('fields' => $fields, 'submitLabel' => sanitize_text_field($label), 'unsupported' => array_values(array_unique($unsupported)));
        if (count(array_column($fields, 'name')) !== count(array_unique(array_column($fields, 'name')))) { $result['unsupported'][] = 'duplicate_field_names'; }
        $result['supported'] = !$result['unsupported'] && (bool) array_filter($fields, function ($field) { return !in_array($field['type'], array('section', 'content'), true); });
        $result['version'] = hash('sha256', json_encode($result));
        return $result;
    }

    private static function walk($items, $prefix, &$fields, &$unsupported, $depth = 0)
    {
        if ($depth > 8 || count($fields) > 150) { $unsupported[] = 'form_too_complex'; return; }
        foreach ($items as $field) {
            if (!is_array($field)) { continue; }
            $element = isset($field['element']) ? $field['element'] : '';
            $settings = isset($field['settings']) ? $field['settings'] : array();
            $attributes = isset($field['attributes']) ? $field['attributes'] : array();
            if (isset($settings['visible']) && !$settings['visible']) { continue; }
            if (!empty($settings['conditional_logics']['status'])) { $unsupported[] = 'conditional_logic'; }
            if ('container' === $element) {
                foreach (isset($field['columns']) ? $field['columns'] : array() as $column) {
                    self::walk(isset($column['fields']) ? $column['fields'] : array(), $prefix, $fields, $unsupported, $depth + 1);
                }
                continue;
            }
            $name = isset($attributes['name']) ? $attributes['name'] : '';
            if (in_array($element, array('input_name', 'address'), true)) {
                if (!self::safe_name($name)) { $unsupported[] = 'invalid_field_name'; continue; }
                self::walk(isset($field['fields']) ? $field['fields'] : array(), array_merge($prefix, array($name)), $fields, $unsupported, $depth + 1);
                continue;
            }
            if (in_array($element, array('custom_html', 'section_break'), true)) {
                $fields[] = array('name' => '__display_' . count($fields), 'path' => array(), 'type' => 'section_break' === $element ? 'section' : 'content', 'label' => sanitize_text_field(isset($settings['label']) ? $settings['label'] : ''), 'description' => sanitize_textarea_field(isset($settings['html_codes']) ? $settings['html_codes'] : (isset($settings['description']) ? $settings['description'] : '')));
                continue;
            }
            $map = array('input_text' => 'text', 'input_email' => 'email', 'input_url' => 'url', 'input_number' => 'number', 'input_date' => 'date', 'input_mask' => 'tel', 'phone' => 'tel', 'textarea' => 'textarea', 'select' => 'select', 'select_country' => 'select', 'input_radio' => 'radio', 'input_checkbox' => 'checkbox', 'terms_and_condition' => 'consent', 'gdpr_agreement' => 'consent', 'input_hidden' => 'hidden');
            $type = isset($map[$element]) ? $map[$element] : null;
            if ('input' === $element && in_array(isset($attributes['type']) ? $attributes['type'] : '', array('text', 'email', 'url', 'tel', 'number', 'date'), true)) { $type = $attributes['type']; }
            if (!$type) { $unsupported[] = $element ?: 'unknown_field'; continue; }
            if (!self::safe_name($name)) { $unsupported[] = 'invalid_field_name'; continue; }
            $path = array_merge($prefix, array($name));
            $options = array();
            foreach (isset($settings['advanced_options']) ? $settings['advanced_options'] : array() as $option) {
                if (!is_array($option)) { continue; }
                $options[] = array('label' => sanitize_text_field(isset($option['label']) ? $option['label'] : ''), 'value' => (string) (isset($option['value']) ? $option['value'] : (isset($option['label']) ? $option['label'] : '')));
            }
            if ('select_country' === $element) {
                $countries = function_exists('getFluentFormCountryList') ? getFluentFormCountryList() : array();
                if (!$countries) { $unsupported[] = 'country_list_unavailable'; }
                $rules = isset($settings['country_list']) ? $settings['country_list'] : array();
                foreach ($countries as $code => $label) {
                    if ('visible_list' === ($rules['active_list'] ?? '') && !in_array($code, $rules['visible_list'] ?? array(), true)) { continue; }
                    if ('hidden_list' === ($rules['active_list'] ?? '') && in_array($code, $rules['hidden_list'] ?? array(), true)) { continue; }
                    $options[] = array('label' => sanitize_text_field($label), 'value' => (string) $code);
                }
            }
            $label = $settings['label'] ?? ($settings['tnc_html'] ?? ($settings['gdpr_consent'] ?? $name));
            if (in_array($type, array('select', 'radio', 'checkbox'), true) && !$options) { $unsupported[] = 'empty_choices'; }
            $value = $attributes['value'] ?? '';
            if (is_string($value) && preg_match('/\{[^}]+\}/', $value)) { $unsupported[] = 'dynamic_default_value'; }
            if (!empty($settings['dynamic_default_value'])) { $unsupported[] = 'dynamic_default_value'; }
            $item = array('name' => array_shift($path), 'path' => array_merge($prefix, array($name)), 'type' => $type, 'label' => sanitize_text_field($label), 'required' => !empty($settings['validation_rules']['required']['value']), 'placeholder' => sanitize_text_field($attributes['placeholder'] ?? ($settings['placeholder'] ?? '')), 'description' => sanitize_textarea_field($settings['help_message'] ?? ''), 'options' => $options, 'multiple' => 'checkbox' === $type || !empty($attributes['multiple']), 'value' => is_array($value) ? array_values(array_filter($value, 'is_scalar')) : (is_scalar($value) ? (string) $value : ''));
            foreach ($path as $part) { $item['name'] .= '[' . $part . ']'; }
            foreach (array('min', 'max', 'step', 'maxlength') as $constraint) {
                if (isset($attributes[$constraint]) && is_numeric($attributes[$constraint])) { $item[$constraint] = (float) $attributes[$constraint]; }
            }
            $fields[] = $item;
        }
    }

    private static function safe_name($name)
    {
        return is_string($name) && preg_match('/^[a-zA-Z_][a-zA-Z0-9_-]*$/D', $name) && !in_array($name, array('__proto__', 'constructor', 'prototype'), true);
    }
}
