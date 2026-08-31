<?php

/**
 * SB-Tech — Website CMS section definitions.
 * Each section maps to a table + typed fields. Field types:
 *   text, textarea, longtext, date, number, select, checkbox,
 *   image(name_col, loc_col), file(name_col, loc_col).
 * Used by the CRUD renderer and the operation handler (single source of
 * truth, so field whitelisting is automatic).
 */

function cmsSections(): array
{
    return [
        'hero' => [
            'table' => 'tbl_cms_hero',
            'label' => 'Hero slide',
            'fa'    => 'fa-star',
            'fields' => [
                'title'       => ['type' => 'text'],
                'subtitle'    => ['type' => 'text'],
                'description' => ['type' => 'textarea'],
                'photo'       => ['type' => 'image', 'name' => 'photo_name', 'loc' => 'photo_location'],
                'button_text' => ['type' => 'text'],
                'button_link' => ['type' => 'text'],
                'position'    => ['type' => 'number'],
                'is_active'   => ['type' => 'checkbox'],
            ],
            'list' => ['title', 'subtitle', 'position', 'is_active'],
        ],
        'about' => [
            'table' => 'tbl_cms_abouts',
            'label' => 'About',
            'fa'    => 'fa-info-circle',
            'fields' => [
                'title'       => ['type' => 'text'],
                'description' => ['type' => 'longtext'],
                'mission'     => ['type' => 'textarea'],
                'vision'      => ['type' => 'textarea'],
                'video_url'   => ['type' => 'text'],
                'image'       => ['type' => 'image', 'name' => 'image_name', 'loc' => 'image_location'],
                'is_active'   => ['type' => 'checkbox'],
            ],
            'list' => ['title', 'is_active'],
        ],
        'testimonial' => [
            'table' => 'tbl_cms_testimonials',
            'label' => 'Testimonial',
            'fa'    => 'fa-quote-left',
            'fields' => [
                'client_name'    => ['type' => 'text'],
                'client_position'=> ['type' => 'text'],
                'client_company' => ['type' => 'text'],
                'testimonial'    => ['type' => 'textarea'],
                'rating'         => ['type' => 'number'],
                'position'       => ['type' => 'number'],
                'is_active'      => ['type' => 'checkbox'],
            ],
            'list' => ['client_name', 'client_company', 'rating', 'is_active'],
        ],
        'service' => [
            'table' => 'tbl_cms_services',
            'label' => 'Service',
            'fa'    => 'fa-cogs',
            'fields' => [
                'title'             => ['type' => 'text'],
                'short_description' => ['type' => 'textarea'],
                'description'       => ['type' => 'longtext'],
                'icon'              => ['type' => 'text', 'hint' => 'FontAwesome class, e.g. fas fa-code'],
                'image'             => ['type' => 'image', 'name' => 'image_name', 'loc' => 'image_location'],
                'position'          => ['type' => 'number'],
                'is_active'         => ['type' => 'checkbox'],
            ],
            'list' => ['title', 'position', 'is_active'],
        ],
        'project' => [
            'table' => 'tbl_cms_projects',
            'label' => 'Project',
            'fa'    => 'fa-project-diagram',
            'fields' => [
                'title'             => ['type' => 'text'],
                'client_name'       => ['type' => 'text'],
                'category'          => ['type' => 'text'],
                'short_description' => ['type' => 'textarea'],
                'description'       => ['type' => 'longtext'],
                'technologies'      => ['type' => 'textarea'],
                'project_url'       => ['type' => 'text'],
                'start_date'        => ['type' => 'date'],
                'end_date'          => ['type' => 'date'],
                'image'             => ['type' => 'image', 'name' => 'image_name', 'loc' => 'image_location'],
                'position'          => ['type' => 'number'],
                'is_active'         => ['type' => 'checkbox'],
            ],
            'list' => ['title', 'client_name', 'category', 'position', 'is_active'],
        ],
        'staff' => [
            'table' => 'tbl_cms_staffs',
            'label' => 'Team member',
            'fa'    => 'fa-user-tie',
            'fields' => [
                'name'       => ['type' => 'text'],
                'designation'=> ['type' => 'text'],
                'short_bio'  => ['type' => 'longtext'],
                'image'      => ['type' => 'image', 'name' => 'image_name', 'loc' => 'image_location'],
                'position'   => ['type' => 'number'],
                'is_active'  => ['type' => 'checkbox'],
            ],
            'list' => ['name', 'designation', 'position', 'is_active'],
        ],
        'news' => [
            'table' => 'tbl_cms_news',
            'label' => 'News item',
            'fa'    => 'fa-newspaper',
            'fields' => [
                'title'     => ['type' => 'text'],
                'slug'      => ['type' => 'text', 'hint' => 'Leave blank to auto-generate'],
                'description'=> ['type' => 'longtext'],
                'image'     => ['type' => 'image', 'name' => 'image_name', 'loc' => 'image_location'],
                'news_date' => ['type' => 'date'],
                'is_active' => ['type' => 'checkbox'],
            ],
            'list' => ['title', 'news_date', 'is_active'],
            'slug_from' => 'title',
        ],
        'notice' => [
            'table' => 'tbl_cms_notices',
            'label' => 'Notice',
            'fa'    => 'fa-bullhorn',
            'fields' => [
                'title'       => ['type' => 'text'],
                'description' => ['type' => 'longtext'],
                'file'        => ['type' => 'file', 'name' => 'file_name', 'loc' => 'file_location'],
                'notice_date' => ['type' => 'date'],
                'is_active'   => ['type' => 'checkbox'],
            ],
            'list' => ['title', 'notice_date', 'is_active'],
        ],
        'career' => [
            'table' => 'tbl_cms_careers',
            'label' => 'Job posting',
            'fa'    => 'fa-briefcase',
            'fields' => [
                'title'        => ['type' => 'text'],
                'slug'         => ['type' => 'text', 'hint' => 'Leave blank to auto-generate'],
                'department_id'=> ['type' => 'department'],
                'designation'  => ['type' => 'text'],
                'location'     => ['type' => 'text'],
                'job_type'     => ['type' => 'select', 'options' => ['Full-time', 'Part-time', 'Contract', 'Internship']],
                'salary'       => ['type' => 'text'],
                'description'  => ['type' => 'longtext'],
                'requirements' => ['type' => 'textarea'],
                'deadline'     => ['type' => 'date'],
                'status'       => ['type' => 'select', 'options' => ['Open', 'Closed']],
            ],
            'list' => ['title', 'designation', 'location', 'job_type', 'status'],
            'slug_from' => 'title',
        ],
    ];
}

/** Whitelist of valid CMS section keys. */
function cmsSectionKeys(): array
{
    return array_keys(cmsSections());
}

/** Slugify a string for news/career URLs. */
function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim((string) $text, '-');
    return $text ?: 'item-' . substr(bin2hex(random_bytes(4)), 0, 8);
}
