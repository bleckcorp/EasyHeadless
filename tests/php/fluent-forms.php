<?php

namespace FluentForm\Framework\Validator {
    class ValidationException extends \Exception
    {
        private $validation_errors;

        public function __construct($errors, $code = 422)
        {
            parent::__construct('', $code);
            $this->validation_errors = $errors;
        }

        public function errors()
        {
            return $this->validation_errors;
        }
    }
}

namespace FluentForm\App\Services\Form {
    class SubmissionHandlerService
    {
        public function handleSubmission($fields, $form_id)
        {
            $GLOBALS['eh_fluent_submission'] = array(
                'fields' => $fields,
                'form_id' => $form_id,
            );

            if (!empty($GLOBALS['eh_fluent_validation_errors'])) {
                throw new \FluentForm\Framework\Validator\ValidationException(
                    array('errors' => $GLOBALS['eh_fluent_validation_errors']),
                    isset($GLOBALS['eh_fluent_validation_status']) ? $GLOBALS['eh_fluent_validation_status'] : 422
                );
            }

            return array(
                'insert_id' => 91,
                'result' => array(
                    'message' => '<strong>Message received.</strong><script>alert(1)</script>',
                    'action' => 'hide_form',
                ),
            );
        }
    }
}

namespace {
    define('ABSPATH', __DIR__ . '/');
    define('FLUENTFORM', true);

    $GLOBALS['eh_fluent_validation_errors'] = array();
    $GLOBALS['eh_fluent_validation_status'] = 422;
    $GLOBALS['eh_fluent_request_data'] = array();

    class WP_Error
    {
        private $code;
        private $message;
        private $data;

        public function __construct($code, $message, $data = array())
        {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }

        public function get_error_code() { return $this->code; }
        public function get_error_message() { return $this->message; }
        public function get_error_data() { return $this->data; }
    }

    class EasyHeadless_Fluent_Request_Test_Double
    {
        public function merge($data)
        {
            $GLOBALS['eh_fluent_request_data'] = $data;
        }
    }

    class EasyHeadless_Fluent_App_Test_Double
    {
        public $request;

        public function __construct()
        {
            $this->request = new EasyHeadless_Fluent_Request_Test_Double();
        }
    }

    function wpFluentForm() { return new EasyHeadless_Fluent_App_Test_Double(); }
    function absint($value) { return abs((int) $value); }
    function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $value)); }
    function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
    function wp_kses_post($value) { return preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', (string) $value); }

    require dirname(__DIR__, 2) . '/easyheadless-bridge/includes/class-easyheadless-forms.php';

    $failures = array();
    function fluent_assert($condition, $label)
    {
        global $failures;
        if (!$condition) {
            $failures[] = $label;
        }
    }

    $adapter = new EasyHeadless_FluentForms_Adapter();
    $success = $adapter->submit('1', array('email' => 'person@example.com'));

    fluent_assert(is_array($success) && true === $success['success'], 'supported service returns success');
    fluent_assert('fluentforms' === $success['provider'], 'provider is normalized');
    fluent_assert('<strong>Message received.</strong>' === $success['confirmation']['message'], 'confirmation is normalized and sanitized');
    fluent_assert(1 === $GLOBALS['eh_fluent_submission']['form_id'], 'numeric form ID is submitted');
    fluent_assert('/' === $GLOBALS['eh_fluent_submission']['fields']['_wp_http_referer'], 'safe referer fallback is submitted');
    fluent_assert(1 === $GLOBALS['eh_fluent_request_data']['form_id'], 'Fluent request context receives form ID');

    $GLOBALS['eh_fluent_validation_errors'] = array(
        'email' => array('<b>Please enter a valid email.</b>'),
    );
    $validation = $adapter->submit('1', array('email' => 'invalid'));

    fluent_assert($validation instanceof WP_Error, 'validation failure returns WP_Error');
    fluent_assert('form_validation_failed' === $validation->get_error_code(), 'validation error code is normalized');
    fluent_assert(422 === $validation->get_error_data()['status'], 'validation status is preserved');
    fluent_assert(
        array('Please enter a valid email.') === $validation->get_error_data()['fieldErrors']['email'],
        'field validation messages are normalized and sanitized'
    );

    $GLOBALS['eh_fluent_validation_status'] = 429;
    $GLOBALS['eh_fluent_validation_errors'] = array('restricted' => array('Too many requests.'));
    $rate_limited = $adapter->submit('1', array());
    fluent_assert(429 === $rate_limited->get_error_data()['status'], 'anti-spam rate-limit status is preserved');

    if ($failures) {
        fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
        exit(1);
    }

    echo "EasyHeadless Fluent Forms adapter tests passed." . PHP_EOL;
}
