<?php
namespace FluentForm\App\Models {
    class Form {
        public static $forms = array();
        public static function find($id) { return self::$forms[$id] ?? null; }
        public static function select($fields) { return new self(); }
        public function get() { return array_values(self::$forms); }
    }
}
namespace {
    require __DIR__ . '/fluent-forms.php';
    function sanitize_textarea_field($value) { return trim(strip_tags((string) $value)); }
    function get_option($key, $default = false) { return $GLOBALS['contact_options'][$key] ?? $default; }
    function getFluentFormCountryList() { return array('NG' => 'Nigeria', 'GH' => 'Ghana'); }
    class EasyHeadless_Modules {
        public static $enabled = true;
        public static function is_enabled($key) { return self::$enabled; }
        public static function allowed_form_ids() { return get_option('allowed', array()); }
    }
    function is_wp_error($value) { return $value instanceof WP_Error; }
    require dirname(__DIR__, 2) . '/easyheadless-bridge/includes/class-easyheadless-contact-form.php';
    function contact_assert($condition, $message) { if (!$condition) { throw new \RuntimeException($message); } }
    function contact_field($element, $name, $settings = array(), $attributes = array()) { return array('element' => $element, 'attributes' => array_merge(array('name' => $name), $attributes), 'settings' => array_merge(array('label' => $name), $settings)); }
    $raw = array('fields' => array(
        array('element' => 'container', 'columns' => array(array('fields' => array(
            array('element' => 'input_name', 'attributes' => array('name' => 'names'), 'fields' => array('first_name' => contact_field('input_text', 'first_name'), 'last_name' => contact_field('input_text', 'last_name', array('visible' => false)))),
            contact_field('input_email', 'email', array('validation_rules' => array('required' => array('value' => true)))),
        )))),
        array('element' => 'address', 'attributes' => array('name' => 'address'), 'fields' => array(contact_field('input_text', 'city'))),
        contact_field('input_checkbox', 'topics', array('advanced_options' => array(array('label' => 'Cinema', 'value' => 'cinema'), array('label' => 'TV', 'value' => 'tv')))),
        contact_field('select', 'interests', array('advanced_options' => array(array('label' => 'Cinema', 'value' => 'cinema'))), array('multiple' => true)),
        contact_field('select_country', 'country', array('country_list' => array('active_list' => 'visible_list', 'visible_list' => array('NG')))),
        contact_field('input_hidden', 'source', array(), array('value' => 'contact')),
        contact_field('textarea', 'message'),
        contact_field('terms_and_condition', 'consent'),
        contact_field('input_number', 'number', array(), array('min' => '1', 'max' => '10')),
        contact_field('input_date', 'date'), contact_field('input_url', 'url'), contact_field('input_mask', 'phone'),
        contact_field('input_radio', 'reason', array('advanced_options' => array(array('label' => 'Help', 'value' => 'help')))),
        array('element' => 'custom_html', 'settings' => array('html_codes' => '<b>Editorial enquiries</b>')),
        array('element' => 'section_break', 'settings' => array('label' => 'About you')),
    ), 'submitButton' => array('settings' => array('button_ui' => array('text' => 'Contact the editors'))));
    $form = (object) array('id' => 1, 'title' => 'Contact', 'has_payment' => 0, 'form_fields' => json_encode($raw));
    \FluentForm\App\Models\Form::$forms[1] = $form;
    $GLOBALS['contact_options'] = array('easyheadless_contact_form' => 'fluentforms:1', 'allowed' => array('fluentforms:1'));
    $GLOBALS['eh_fluent_validation_errors'] = array();
    $schema = EasyHeadless_Contact_Form::schema();
    contact_assert(!is_wp_error($schema) && $schema['supported'], 'Standard form is supported.');
    $names = array_column($schema['fields'], 'name');
    contact_assert(in_array('names[first_name]', $names, true) && !in_array('names[last_name]', $names, true) && in_array('address[city]', $names, true), 'Names/address/columns retain nested data paths and visibility.');
    $country = array_values(array_filter($schema['fields'], function ($field) { return 'country' === $field['name']; }))[0];
    contact_assert(array('NG') === array_column($country['options'], 'value'), 'Country restrictions are preserved.');
    contact_assert('Contact the editors' === $schema['submitLabel'], 'Button text comes from Fluent Forms.');
    class Contact_Request { public $body; public function __construct($body) { $this->body = $body; } public function get_json_params() { return $this->body; } }
    $body = array('formId' => $schema['id'], 'version' => $schema['version'], 'fields' => array('names[first_name]' => 'Test', 'email' => 'contact-fixture@example.test', 'topics' => array('tv', 'cinema'), 'source' => 'tampered', 'address[city]' => 'Lagos'));
    $result = EasyHeadless_Contact_Form::submit(new Contact_Request($body));
    contact_assert(is_array($result) && $result['success'], 'Contact uses the actual Fluent Forms adapter.');
    contact_assert('Test' === $GLOBALS['eh_fluent_submission']['fields']['names']['first_name'] && array('tv', 'cinema') === $GLOBALS['eh_fluent_submission']['fields']['topics'], 'Nested names and multiple selections reach Fluent Forms.');
    contact_assert('contact' === $GLOBALS['eh_fluent_submission']['fields']['source'], 'Hidden defaults cannot be overwritten.');
    $GLOBALS['eh_fluent_validation_errors'] = array('names.first_name' => array('First name required.'));
    $result = EasyHeadless_Contact_Form::submit(new Contact_Request($body));
    contact_assert(isset($result->get_error_data()['fieldErrors']['names.first_name']), 'Nested Fluent validation errors remain addressable.');
    $GLOBALS['eh_fluent_validation_errors'] = array();
    $invalid = $body; $invalid['fields']['topics'] = array('invalid');
    contact_assert(422 === EasyHeadless_Contact_Form::submit(new Contact_Request($invalid))->get_error_data()['status'], 'Tampered choice rejected.');
    $invalid = $body; $invalid['fields']['__proto__'] = 'bad';
    contact_assert(400 === EasyHeadless_Contact_Form::submit(new Contact_Request($invalid))->get_error_data()['status'], 'Unknown fields rejected.');
    $invalid = $body; $invalid['formId'] = 'fluentforms:999';
    contact_assert(409 === EasyHeadless_Contact_Form::submit(new Contact_Request($invalid))->get_error_data()['status'], 'Client cannot choose another form.');
    $raw['fields'][] = contact_field('input_text', 'added_field');
    $form->form_fields = json_encode($raw);
    contact_assert(EasyHeadless_Contact_Form::schema()['version'] !== $schema['version'], 'Field edits are visible immediately with a new version.');
    contact_assert(409 === EasyHeadless_Contact_Form::submit(new Contact_Request($body))->get_error_data()['status'], 'Stale open forms cannot submit silently.');
    contact_assert(403 === (new EasyHeadless_Forms())->submit('1', array())->get_error_data()['status'], 'Generic public endpoint cannot bypass the Contact handler.');
    foreach (array('payment_method', 'input_file', 'input_password', 'recaptcha', 'form_step', 'unknown_widget') as $element) {
        $blocked = clone $form; $blocked->form_fields = json_encode(array('fields' => array(contact_field($element, 'blocked'))));
        contact_assert(!EasyHeadless_Form_Schema::read($blocked)['supported'], 'Unsupported element is explicit: ' . $element);
    }
    $blocked = clone $form; $blocked->has_payment = 1;
    contact_assert(!EasyHeadless_Form_Schema::read($blocked)['supported'], 'Payment forms are blocked even if a field is missing.');
    contact_assert(is_wp_error(EasyHeadless_Contact_Form::validate_selection('fluentforms:999')), 'Deleted forms cannot be selected.');
    $GLOBALS['contact_options']['allowed'] = array();
    contact_assert(is_wp_error(EasyHeadless_Contact_Form::schema()), 'Unapproved forms are not exposed.');
    echo "Contact form schema, selection, submission and safety tests passed.\n";
}
