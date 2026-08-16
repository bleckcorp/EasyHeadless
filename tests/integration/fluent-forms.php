<?php

if (!defined('ABSPATH')) {
    exit(1);
}

if (!class_exists('FluentForm\\App\\Models\\Form')) {
    fwrite(STDERR, "Fluent Forms is not active.\n");
    exit(1);
}

$template = \FluentForm\App\Models\Form::resolvePredefinedForm(array(
    'predefined' => 'basic_contact_form',
));
$form_data = \FluentForm\App\Models\Form::prepare($template);
$form_data['title'] = 'EasyHeadless integration test';
$form_id = wpFluent()->table('fluentform_forms')->insertGetId($form_data);

if (!$form_id) {
    fwrite(STDERR, "Unable to create the Fluent Forms integration fixture.\n");
    exit(1);
}

foreach (array('formSettings', 'notifications') as $meta_key) {
    if (!isset($template[$meta_key])) {
        continue;
    }

    wpFluent()->table('fluentform_form_meta')->insert(array(
        'form_id' => $form_id,
        'meta_key' => $meta_key,
        'value' => wp_json_encode($template[$meta_key]),
    ));
}

$failures = array();
$adapter = new EasyHeadless_FluentForms_Adapter();

$invalid = $adapter->submit($form_id, array(
    'email' => 'not-an-email',
    'message' => '',
));

if (!is_wp_error($invalid) || 'form_validation_failed' !== $invalid->get_error_code()) {
    $failures[] = 'Invalid data did not return a normalized validation error.';
}

$before_count = wpFluent()->table('fluentform_submissions')->where('form_id', $form_id)->count();
$valid = $adapter->submit($form_id, array(
    'first_name' => 'Integration',
    'last_name' => 'Test',
    'email' => 'integration@example.test',
    'subject' => 'EasyHeadless adapter verification',
    'message' => 'This submission is created inside a disposable test site.',
));
$after_count = wpFluent()->table('fluentform_submissions')->where('form_id', $form_id)->count();

if (!is_array($valid) || empty($valid['success'])) {
    $failures[] = 'Valid data did not return a successful normalized response.';
}

if ($after_count !== $before_count + 1) {
    $failures[] = 'Fluent Forms did not persist the valid submission.';
}

wpFluent()->table('fluentform_entry_details')->where('form_id', $form_id)->delete();
wpFluent()->table('fluentform_submissions')->where('form_id', $form_id)->delete();
wpFluent()->table('fluentform_form_meta')->where('form_id', $form_id)->delete();
wpFluent()->table('fluentform_forms')->where('id', $form_id)->delete();

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "EasyHeadless submitted successfully through Fluent Forms 6.2.\n";

