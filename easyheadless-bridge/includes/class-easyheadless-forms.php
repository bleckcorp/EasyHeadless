<?php

if (!defined('ABSPATH')) {
    exit;
}

interface EasyHeadless_Form_Adapter
{
    public function id();

    public function is_available();

    public function list_forms();

    public function get_form($id);

    public function submit($id, $fields);
}

final class EasyHeadless_Forms
{
    private $adapters;

    public function __construct()
    {
        $this->adapters = array(
            new EasyHeadless_FluentForms_Adapter(),
        );
    }

    public function health()
    {
        $health = array();

        foreach ($this->adapters as $adapter) {
            $status = array(
                'available' => $adapter->is_available(),
            );

            if (method_exists($adapter, 'diagnostics')) {
                $status = array_merge($status, $adapter->diagnostics());
            }

            $health[$adapter->id()] = $status;
        }

        return $health;
    }

    public function list_forms()
    {
        $forms = array();
        $allowed = EasyHeadless_Modules::allowed_form_ids();

        foreach ($this->adapters as $adapter) {
            if (!$adapter->is_available()) {
                continue;
            }

            foreach ($adapter->list_forms() as $form) {
                if ($this->is_allowed(isset($form['id']) ? $form['id'] : '', $allowed)) {
                    $forms[] = $form;
                }
            }
        }

        return $forms;
    }

    public function get_form($id)
    {
        if (!$this->is_allowed($id, EasyHeadless_Modules::allowed_form_ids())) {
            return new WP_Error('form_not_found', 'This form is not publicly available.', array('status' => 404));
        }

        $adapter = $this->adapter_for_form($id);

        if (!$adapter) {
            return new WP_Error('form_not_found', 'No supported form adapter found for this form.', array('status' => 404));
        }

        return $adapter->get_form($this->strip_provider($id));
    }

    public function submit($id, $fields)
    {
        if (!$this->is_allowed($id, EasyHeadless_Modules::allowed_form_ids())) {
            return new WP_Error('form_not_found', 'This form is not publicly available.', array('status' => 404));
        }

        $adapter = $this->adapter_for_form($id);

        if (!$adapter) {
            return new WP_Error('form_not_found', 'No supported form adapter found for this form.', array('status' => 404));
        }

        return $adapter->submit($this->strip_provider($id), $fields);
    }

    private function adapter_for_form($id)
    {
        $provider = null;

        if (false !== strpos((string) $id, ':')) {
            list($provider) = explode(':', (string) $id, 2);
        }

        foreach ($this->adapters as $adapter) {
            if ($provider && $adapter->id() !== $provider) {
                continue;
            }

            if ($adapter->is_available()) {
                return $adapter;
            }
        }

        return null;
    }

    private function strip_provider($id)
    {
        if (false === strpos((string) $id, ':')) {
            return $id;
        }

        $parts = explode(':', (string) $id, 2);
        return $parts[1];
    }

    private function is_allowed($id, $allowed)
    {
        $id = (string) $id;
        $numeric = $this->strip_provider($id);

        return in_array($id, $allowed, true)
            || in_array((string) $numeric, $allowed, true)
            || in_array('fluentforms:' . $numeric, $allowed, true);
    }
}

final class EasyHeadless_FluentForms_Adapter implements EasyHeadless_Form_Adapter
{
    public function id()
    {
        return 'fluentforms';
    }

    public function is_available()
    {
        return defined('FLUENTFORM') || function_exists('wpFluentForm') || class_exists('\FluentForm\App\Models\Form');
    }

    public function diagnostics()
    {
        $plugin_file = 'fluentform/fluentform.php';
        $active_plugins = get_option('active_plugins', array());
        $network_plugins = function_exists('get_site_option')
            ? get_site_option('active_sitewide_plugins', array())
            : array();
        $loaded = $this->is_available();
        $active = $loaded
            || in_array($plugin_file, is_array($active_plugins) ? $active_plugins : array(), true)
            || (is_array($network_plugins) && isset($network_plugins[$plugin_file]));
        $installed = $active
            || (defined('WP_PLUGIN_DIR') && file_exists(WP_PLUGIN_DIR . '/' . $plugin_file));
        $message = '';

        if (!$loaded && $installed && !$active) {
            $message = 'Fluent Forms is installed but inactive.';
        } elseif (!$loaded && $active) {
            $message = 'Fluent Forms is marked active, but its runtime API did not load.';
        } elseif (!$installed) {
            $message = 'Fluent Forms is not installed on this WordPress site.';
        }

        return array(
            'installed' => $installed,
            'active' => $active,
            'loaded' => $loaded,
            'version' => defined('FLUENTFORM_VERSION') ? FLUENTFORM_VERSION : null,
            'message' => $message,
        );
    }

    public function list_forms()
    {
        if (!$this->is_available() || !class_exists('\FluentForm\App\Models\Form')) {
            return array();
        }

        $forms = \FluentForm\App\Models\Form::select(array('id', 'title'))->get();
        $normalized = array();

        foreach ($forms as $form) {
            $normalized[] = array(
                'id' => $this->id() . ':' . $form->id,
                'provider' => $this->id(),
                'title' => $form->title,
            );
        }

        return $normalized;
    }

    public function get_form($id)
    {
        if (!$this->is_available() || !class_exists('\FluentForm\App\Models\Form')) {
            return new WP_Error('adapter_unavailable', 'Fluent Forms is not available.', array('status' => 503));
        }

        $form = \FluentForm\App\Models\Form::find(absint($id));

        if (!$form) {
            return new WP_Error('form_not_found', 'Fluent Form not found.', array('status' => 404));
        }

        $fields = $this->extract_fields($form);

        return array(
            'id' => $this->id() . ':' . $form->id,
            'provider' => $this->id(),
            'title' => $form->title,
            'fields' => $fields,
        );
    }

    public function submit($id, $fields)
    {
        if (!$this->is_available()) {
            return new WP_Error('adapter_unavailable', 'Fluent Forms is not available.', array('status' => 503));
        }

        $form_id = absint($id);
        $fields = is_array($fields) ? $fields : array();

        if (class_exists('\\FluentForm\\App\\Services\\Form\\SubmissionHandlerService')) {
            try {
                if (!isset($fields['_wp_http_referer'])) {
                    $fields['_wp_http_referer'] = '/';
                }

                if (function_exists('wpFluentForm')) {
                    $app = wpFluentForm();
                    $request = $app->request;
                    if (is_object($request) && method_exists($request, 'merge')) {
                        $request->merge(array(
                            'data' => $fields,
                            'form_id' => $form_id,
                        ));
                    }
                }

                $handler = new \FluentForm\App\Services\Form\SubmissionHandlerService();
                $submission = $handler->handleSubmission($fields, $form_id);
                $result = isset($submission['result']) && is_array($submission['result'])
                    ? $submission['result']
                    : array();

                return array(
                    'success' => true,
                    'provider' => $this->id(),
                    'confirmation' => array(
                        'type' => 'message',
                        'message' => !empty($result['message'])
                            ? wp_kses_post($result['message'])
                            : 'Thanks for contacting us.',
                    ),
                );
            } catch (\FluentForm\Framework\Validator\ValidationException $error) {
                $payload = method_exists($error, 'errors') ? $error->errors() : array();
                $errors = $this->normalize_errors(
                    isset($payload['errors']) ? $payload['errors'] : $payload
                );
                $status = (int) $error->getCode();
                $status = $status >= 400 && $status <= 499 ? $status : 422;

                return new WP_Error(
                    'form_validation_failed',
                    'Please correct the highlighted fields.',
                    array('status' => $status, 'fieldErrors' => $errors)
                );
            } catch (Throwable $error) {
                return new WP_Error(
                    'form_submission_failed',
                    'The form could not be submitted. Please review your entries and try again.',
                    array('status' => 422)
                );
            }
        }

        if (function_exists('wpFluentForm')) {
            try {
                $app = wpFluentForm();
                if (isset($app['form-submission'])) {
                    $submission = $app['form-submission']->submit($form_id, $fields);
                    $errors = $this->normalize_errors(
                        isset($submission['errors']) ? $submission['errors'] : array()
                    );

                    if ($errors) {
                        return new WP_Error(
                            'form_validation_failed',
                            'Please correct the highlighted fields.',
                            array('status' => 422, 'fieldErrors' => $errors)
                        );
                    }

                    return array(
                        'success' => true,
                        'provider' => $this->id(),
                        'confirmation' => array(
                            'type' => 'message',
                            'message' => isset($submission['confirmation']['message']) ? wp_kses_post($submission['confirmation']['message']) : 'Thanks for contacting us.',
                        ),
                    );
                }
            } catch (Throwable $error) {
                return new WP_Error(
                    'form_submission_failed',
                    'The form could not be submitted. Please review your entries and try again.',
                    array('status' => 422)
                );
            }
        }

        return new WP_Error(
            'adapter_unavailable',
            'This Fluent Forms version does not expose a safe public submission service. Add a project-specific adapter or use an embed fallback.',
            array('status' => 501)
        );
    }

    private function extract_fields($form)
    {
        $schema = json_decode($form->form_fields, true);
        $fields = array();

        if (!is_array($schema) || empty($schema['fields'])) {
            return $fields;
        }

        foreach ($schema['fields'] as $field) {
            $attributes = isset($field['attributes']) && is_array($field['attributes']) ? $field['attributes'] : array();
            $settings = isset($field['settings']) && is_array($field['settings']) ? $field['settings'] : array();

            $type = $this->normalize_field_type(
                isset($field['element']) ? $field['element'] : 'input',
                isset($attributes['type']) ? $attributes['type'] : ''
            );

            if (!$type) {
                continue;
            }

            $fields[] = array(
                'name' => isset($attributes['name']) ? $attributes['name'] : '',
                'label' => isset($settings['label']) ? $settings['label'] : '',
                'type' => $type,
                'required' => !empty($settings['validation_rules']['required']['value']),
                'placeholder' => isset($attributes['placeholder']) ? $attributes['placeholder'] : '',
                'options' => $this->normalize_options(isset($settings['advanced_options']) ? $settings['advanced_options'] : array()),
            );
        }

        return array_values(array_filter($fields, function ($field) {
            return !empty($field['name']);
        }));
    }

    private function normalize_field_type($element, $attribute_type)
    {
        $element = sanitize_key($element);
        $attribute_type = sanitize_key($attribute_type);
        $map = array(
            'input_text' => 'text',
            'input_email' => 'email',
            'input_url' => 'url',
            'input_number' => 'number',
            'input_date' => 'date',
            'input_mask' => 'tel',
            'phone' => 'tel',
            'textarea' => 'textarea',
            'select' => 'select',
            'select_country' => 'select',
            'input_radio' => 'radio',
            'input_checkbox' => 'checkbox',
            'terms_and_condition' => 'consent',
            'gdpr_agreement' => 'consent',
        );

        if (isset($map[$element])) {
            return $map[$element];
        }

        if ('input' === $element && in_array($attribute_type, array('text', 'email', 'tel', 'number', 'date'), true)) {
            return $attribute_type;
        }

        return null;
    }

    private function normalize_options($options)
    {
        $normalized = array();
        foreach (is_array($options) ? $options : array() as $option) {
            if (!is_array($option)) {
                continue;
            }

            $normalized[] = array(
                'label' => isset($option['label']) ? sanitize_text_field($option['label']) : '',
                'value' => isset($option['value']) ? sanitize_text_field($option['value']) : '',
            );
        }

        return $normalized;
    }

    private function normalize_errors($errors)
    {
        $normalized = array();
        foreach (is_array($errors) ? $errors : array() as $field => $messages) {
            $messages = is_array($messages) ? $messages : array($messages);
            $normalized[sanitize_key($field)] = array_values(array_filter(array_map('sanitize_text_field', $messages)));
        }

        return $normalized;
    }
}
