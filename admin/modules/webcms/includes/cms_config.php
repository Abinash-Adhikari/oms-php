<?php

/**
 * SB-Tech — Website CMS section definitions.
 * Each section maps to a table + typed fields. Field types:
 *   text, textarea, longtext, date, number, select, checkbox,
 *   image(name_col, loc_col[, aspect, shape, max]), file(name_col, loc_col),
 *   — image fields use the editor field (image_field_html): 'aspect' = default
 *   crop ratio (omit = free), 'shape' => 'circle' for avatars, 'max' = longest
 *   side in px (default 1600); the server re-encodes via store_image_upload().
 *   clients(link_table, link_fk) — zero/one/many clients stored in a link
 *   table: website clients (tbl_cms_clients) and/or free-text names.
 * Optional 'link_projects' => true adds a "Link portfolio items" row action
 * (used by the 'client' section; links live in tbl_cms_project_clients).
 * Optional 'order' is a static ORDER BY clause for the admin list; it
 * defaults to "`position`, `id` DESC" when the section has a 'position'
 * field, else "`id` DESC".
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
                'photo'       => ['type' => 'image', 'name' => 'photo_name', 'loc' => 'photo_location', 'aspect' => 16 / 9, 'max' => 2000],
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
            'label' => 'Portfolio Item',
            'fa'    => 'fa-project-diagram',
            'fields' => [
                'title'             => ['type' => 'text'],
                'clients'           => ['type' => 'clients', 'link_table' => 'tbl_cms_project_clients', 'link_fk' => 'project_id', 'hint' => 'Pick website clients or type a new name; leave empty for none'],
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
            'list' => ['title', 'clients', 'category', 'position', 'is_active'],
        ],
        'client' => [
            'table' => 'tbl_cms_clients',
            'label' => 'Client',
            'fa'    => 'fa-handshake',
            'fields' => [
                'name'         => ['type' => 'text'],
                'company_name' => ['type' => 'text'],
                'logo'         => ['type' => 'image', 'name' => 'logo_name', 'loc' => 'logo_location', 'hint' => 'PNG with transparent background works best', 'max' => 800],
                'description'  => ['type' => 'textarea'],
                'website_url'  => ['type' => 'text', 'hint' => 'https://…'],
                'position'     => ['type' => 'number'],
                'is_active'    => ['type' => 'checkbox'],
            ],
            'list' => ['name', 'company_name', 'position', 'is_active'],
            'link_projects' => true,
        ],
        'staff' => [
            'table' => 'tbl_cms_staffs',
            'label' => 'Team member',
            'fa'    => 'fa-user-tie',
            'fields' => [
                'name'       => ['type' => 'text'],
                'designation'=> ['type' => 'text'],
                'short_bio'  => ['type' => 'longtext'],
                'image'      => ['type' => 'image', 'name' => 'image_name', 'loc' => 'image_location', 'aspect' => 1, 'shape' => 'circle', 'max' => 800],
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
                'image'     => ['type' => 'image', 'name' => 'image_name', 'loc' => 'image_location', 'aspect' => 16 / 9],
                'news_date' => ['type' => 'date'],
                'is_active' => ['type' => 'checkbox'],
            ],
            'list' => ['title', 'news_date', 'is_active'],
            'order' => '`news_date` DESC, `id` DESC',
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
            'order' => '`notice_date` DESC, `id` DESC',
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

/**
 * Clients of the given rows for a 'clients' field, keyed by row id.
 * Website clients show their current name (falls back to the stored snapshot).
 * Table/column names come from cms_config.php only (never request input).
 *
 * @param array<int, int> $rowIds
 * @return array<int, list<array{client_id: ?int, name: string}>>
 */
function cmsLinkedClients(Database $db, array $field, array $rowIds): array
{
    if ($rowIds === []) {
        return [];
    }
    $rows = $db->select(
        'SELECT l.`' . $field['link_fk'] . '` AS row_id, l.`cms_client_id` AS client_id, COALESCE(c.`name`, l.`client_name`) AS name
         FROM `' . $field['link_table'] . '` l
         LEFT JOIN `tbl_cms_clients` c ON c.`id` = l.`cms_client_id`
         WHERE l.`' . $field['link_fk'] . '` IN (' . implode(',', array_fill(0, count($rowIds), '?')) . ')
         ORDER BY l.`position`, l.`id`',
        array_values(array_map('intval', $rowIds))
    );
    $map = [];
    foreach ($rows as $r) {
        $map[(int) $r['row_id']][] = [
            'client_id' => $r['client_id'] !== null ? (int) $r['client_id'] : null,
            'name'      => (string) $r['name'],
        ];
    }
    return $map;
}

/** Form value for a linked client: "client:<id>" for website clients, else the name. */
function cmsClientValue(array $client): string
{
    return $client['client_id'] !== null ? 'client:' . $client['client_id'] : $client['name'];
}
