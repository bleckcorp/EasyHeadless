<?php

/** @param array<string, mixed> $field_group */
function acf_add_local_field_group(array $field_group): void
{
}

final class EasyHeadless_PhpStan_Yoast_Presentation
{
    public string $title = '';
    public string $meta_description = '';
    public string $description = '';
    public string $canonical = '';
}

final class EasyHeadless_PhpStan_Yoast_Meta
{
    public function for_post(int $post_id): EasyHeadless_PhpStan_Yoast_Presentation
    {
        return new EasyHeadless_PhpStan_Yoast_Presentation();
    }
}

final class EasyHeadless_PhpStan_Yoast
{
    public EasyHeadless_PhpStan_Yoast_Meta $meta;
}

function YoastSEO(): EasyHeadless_PhpStan_Yoast
{
    return new EasyHeadless_PhpStan_Yoast();
}
