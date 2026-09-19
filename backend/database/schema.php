<?php

declare(strict_types=1);

/**
 * Single source of truth for the database schema.
 *
 * The same declaration is emitted as MySQL 8 DDL (production) or SQLite DDL
 * (local development / sandbox preview) by Migrator.php, so both drivers can
 * never drift apart.
 *
 * Column type vocabulary
 *   id                → BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
 *   bigint            → BIGINT
 *   int               → INT
 *   tinyint           → TINYINT
 *   bool              → TINYINT(1) (SQLite: INTEGER)
 *   string:N          → VARCHAR(N)
 *   text              → TEXT
 *   mediumtext        → MEDIUMTEXT (SQLite: TEXT)
 *   decimal:P,S       → DECIMAL(P,S)
 *   date              → DATE
 *   datetime          → DATETIME
 *   time              → TIME
 *   enum:a,b,c        → ENUM(...) (SQLite: VARCHAR(32))
 *   json              → JSON (SQLite: TEXT)
 *
 * Keys
 *   unique   → [['email'], ['a','b']]
 *   indexes  → [['is_active','sort_order']]
 *   foreigns → [['column', 'referenced_table', 'referenced_column', 'CASCADE|SET NULL|RESTRICT']]
 *
 * Table order matters (parents before children) so SQLite foreign keys resolve.
 */

return [
    /* --------------------------------------------------------------------- */
    /* Media library is referenced by almost everything, so it comes first.   */
    /* --------------------------------------------------------------------- */
    'media' => [
        'columns' => [
            'id' => 'id',
            'filename' => 'string:200',
            'original_name' => 'string:200',
            'path' => 'string:255',
            'url' => 'string:320',
            'mime_type' => 'string:100',
            'extension' => 'string:16',
            'size' => 'bigint',
            'width' => 'int?',
            'height' => 'int?',
            'folder' => 'string:120:default=general',
            'alt_text' => 'string:300?',
            'caption' => 'string:300?',
            'uploaded_by' => 'bigint?',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'indexes' => [['folder'], ['mime_type'], ['created_at']],
    ],
    'media_translations' => [
        'columns' => [
            'id' => 'id',
            'media_id' => 'bigint',
            'lang' => 'string:8',
            'alt_text' => 'string:300?',
            'caption' => 'string:300?',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'unique' => [['media_id', 'lang']],
        'foreigns' => [['media_id', 'media', 'id', 'CASCADE']],
    ],

    /* --------------------------------------------------------------------- */
    /* Identity & access                                                     */
    /* --------------------------------------------------------------------- */
    'admins' => [
        'columns' => [
            'id' => 'id',
            'name' => 'string:120',
            'email' => 'string:190',
            'password_hash' => 'string:255',
            'role' => 'enum:super_admin,admin,editor:default=admin',
            'avatar_media_id' => 'bigint?',
            'bio' => 'string:500?',
            'is_active' => 'bool:default=1',
            'must_change_password' => 'bool:default=0',
            'last_login_at' => 'datetime?',
            'last_login_ip' => 'string:45?',
            'failed_attempts' => 'int:default=0',
            'locked_until' => 'datetime?',
            'remember_token' => 'string:100?',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'unique' => [['email']],
        'indexes' => [['role'], ['is_active']],
        'foreigns' => [['avatar_media_id', 'media', 'id', 'SET NULL']],
    ],

    'admin_sessions' => [
        'columns' => [
            'id' => 'string:64',
            'admin_id' => 'bigint',
            'ip' => 'string:45?',
            'user_agent' => 'string:255?',
            'payload' => 'mediumtext?',
            'last_activity' => 'datetime',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'primary' => 'id',
        'indexes' => [['admin_id'], ['last_activity']],
        'foreigns' => [['admin_id', 'admins', 'id', 'CASCADE']],
    ],

    'login_attempts' => [
        'columns' => [
            'id' => 'id',
            'email' => 'string:190',
            'ip' => 'string:45?',
            'successful' => 'bool:default=0',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'indexes' => [['email', 'created_at'], ['ip', 'created_at']],
    ],

    'password_resets' => [
        'columns' => [
            'id' => 'id',
            'admin_id' => 'bigint',
            'token_hash' => 'string:255',
            'expires_at' => 'datetime',
            'used_at' => 'datetime?',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'indexes' => [['admin_id'], ['token_hash']],
        'foreigns' => [['admin_id', 'admins', 'id', 'CASCADE']],
    ],

    'audit_logs' => [
        'columns' => [
            'id' => 'id',
            'admin_id' => 'bigint?',
            'action' => 'string:80',
            'entity_type' => 'string:40?',
            'entity_id' => 'bigint?',
            'ip' => 'string:45?',
            'context' => 'json?',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'indexes' => [['admin_id'], ['action'], ['created_at']],
        'foreigns' => [['admin_id', 'admins', 'id', 'SET NULL']],
    ],

    /* --------------------------------------------------------------------- */
    /* Localisation & configuration                                          */
    /* --------------------------------------------------------------------- */
    'languages' => [
        'columns' => [
            'id' => 'id',
            'code' => 'string:8',
            'name' => 'string:64',
            'native_name' => 'string:64',
            'direction' => 'enum:ltr,rtl:default=ltr',
            'flag' => 'string:16?',
            'is_default' => 'bool:default=0',
            'is_active' => 'bool:default=1',
            'sort_order' => 'int:default=0',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'unique' => [['code']],
        'indexes' => [['is_active', 'sort_order']],
    ],

    'settings' => [
        'columns' => [
            'id' => 'id',
            'group_key' => 'string:40:default=general',
            'key_name' => 'string:100',
            'value_type' => 'enum:string,text,bool,int,json,html,media,color:default=string',
            'value' => 'mediumtext?',
            'is_public' => 'bool:default=1',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'unique' => [['key_name']],
        'indexes' => [['group_key', 'key_name'], ['is_public']],
    ],

    'setting_translations' => [
        'columns' => [
            'id' => 'id',
            'setting_id' => 'bigint',
            'lang' => 'string:8',
            'value' => 'mediumtext?',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'unique' => [['setting_id', 'lang']],
        'indexes' => [['lang']],
        'foreigns' => [['setting_id', 'settings', 'id', 'CASCADE']],
    ],

    'translations' => [
        'columns' => [
            'id' => 'id',
            'key_name' => 'string:150',
            'group_key' => 'string:40:default=general',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'unique' => [['key_name']],
        'indexes' => [['group_key']],
    ],

    'translation_values' => [
        'columns' => [
            'id' => 'id',
            'translation_id' => 'bigint',
            'lang' => 'string:8',
            'value' => 'mediumtext?',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'unique' => [['translation_id', 'lang']],
        'indexes' => [['lang']],
        'foreigns' => [['translation_id', 'translations', 'id', 'CASCADE']],
    ],

    /* --------------------------------------------------------------------- */
    /* Site structure                                                        */
    /* --------------------------------------------------------------------- */
    'pages' => [
        'columns' => [
            'id' => 'id',
            'key_name' => 'string:60',
            'template' => 'string:60?',
            'icon' => 'string:60?',
            'is_active' => 'bool:default=1',
            'sort_order' => 'int:default=0',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'unique' => [['key_name']],
        'indexes' => [['is_active', 'sort_order']],
    ],

    'page_translations' => [
        'columns' => [
            'id' => 'id',
            'page_id' => 'bigint',
            'lang' => 'string:8',
            'title' => 'string:200',
            'subtitle' => 'string:300?',
            'content' => 'mediumtext?',
            'seo_title' => 'string:200?',
            'seo_description' => 'string:320?',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'unique' => [['page_id', 'lang']],
        'indexes' => [['lang']],
        'foreigns' => [['page_id', 'pages', 'id', 'CASCADE']],
    ],

    'sections' => [
        'columns' => [
            'id' => 'id',
            'page_id' => 'bigint',
            'key_name' => 'string:60',
            'type_name' => 'string:40',
            'is_active' => 'bool:default=1',
            'sort_order' => 'int:default=0',
            'settings_json' => 'json?',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'unique' => [['page_id', 'key_name']],
        'indexes' => [['page_id', 'is_active', 'sort_order']],
        'foreigns' => [['page_id', 'pages', 'id', 'CASCADE']],
    ],

    'section_translations' => [
        'columns' => [
            'id' => 'id',
            'section_id' => 'bigint',
            'lang' => 'string:8',
            'eyebrow' => 'string:120?',
            'title' => 'string:220?',
            'subtitle' => 'string:320?',
            'body' => 'mediumtext?',
            'cta_label' => 'string:80?',
            'cta_url' => 'string:255?',
            'cta2_label' => 'string:80?',
            'cta2_url' => 'string:255?',
            'items_json' => 'json?',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'unique' => [['section_id', 'lang']],
        'indexes' => [['lang']],
        'foreigns' => [['section_id', 'sections', 'id', 'CASCADE']],
    ],

    'navigation' => [
        'columns' => [
            'id' => 'id',
            'location' => 'enum:header,footer_quick,footer_services,footer_company,footer_legal:default=header',
            'parent_id' => 'bigint?',
            'type_name' => 'enum:route,url,page,project_category,blog_category,anchor:default=route',
            'target_value' => 'string:255',
            'icon' => 'string:60?',
            'open_in_new_tab' => 'bool:default=0',
            'is_active' => 'bool:default=1',
            'sort_order' => 'int:default=0',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'indexes' => [['location', 'is_active', 'sort_order'], ['parent_id']],
        'foreigns' => [['parent_id', 'navigation', 'id', 'SET NULL']],
    ],

    'navigation_translations' => [
        'columns' => [
            'id' => 'id',
            'navigation_id' => 'bigint',
            'lang' => 'string:8',
            'label' => 'string:120',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'unique' => [['navigation_id', 'lang']],
        'indexes' => [['lang']],
        'foreigns' => [['navigation_id', 'navigation', 'id', 'CASCADE']],
    ],

    'social_links' => [
        'columns' => [
            'id' => 'id',
            'platform' => 'string:40',
            'url' => 'string:255',
            'icon' => 'string:60?',
            'color' => 'string:20?',
            'is_active' => 'bool:default=1',
            'sort_order' => 'int:default=0',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'indexes' => [['is_active', 'sort_order']],
    ],

    'social_link_translations' => [
        'columns' => [
            'id' => 'id',
            'social_link_id' => 'bigint',
            'lang' => 'string:8',
            'label' => 'string:80',
            'handle' => 'string:80?',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'unique' => [['social_link_id', 'lang']],
        'indexes' => [['lang']],
        'foreigns' => [['social_link_id', 'social_links', 'id', 'CASCADE']],
    ],

    /* --------------------------------------------------------------------- */
    /* Skills & services                                                     */
    /* --------------------------------------------------------------------- */
    'skill_categories' => [
        'columns' => [
            'id' => 'id',
            'key_name' => 'string:60',
            'icon' => 'string:60?',
            'is_active' => 'bool:default=1',
            'sort_order' => 'int:default=0',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'unique' => [['key_name']],
        'indexes' => [['is_active', 'sort_order']],
    ],

    'skill_category_translations' => [
        'columns' => [
            'id' => 'id',
            'category_id' => 'bigint',
            'lang' => 'string:8',
            'name' => 'string:120',
            'description' => 'string:300?',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'unique' => [['category_id', 'lang']],
        'indexes' => [['lang']],
        'foreigns' => [['category_id', 'skill_categories', 'id', 'CASCADE']],
    ],

    'skills' => [
        'columns' => [
            'id' => 'id',
            'category_id' => 'bigint?',
            'kind' => 'enum:skill,technology:default=skill',
            'icon' => 'string:60?',
            'level' => 'int:default=80',
            'proficiency' => 'string:40?',
            'years' => 'string:20?',
            'color' => 'string:20?',
            'is_featured' => 'bool:default=0',
            'is_active' => 'bool:default=1',
            'sort_order' => 'int:default=0',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'indexes' => [['category_id'], ['kind', 'is_active', 'sort_order'], ['is_featured']],
        'foreigns' => [['category_id', 'skill_categories', 'id', 'SET NULL']],
    ],

    'skill_translations' => [
        'columns' => [
            'id' => 'id',
            'skill_id' => 'bigint',
            'lang' => 'string:8',
            'name' => 'string:120',
            'description' => 'string:400?',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'unique' => [['skill_id', 'lang']],
        'indexes' => [['lang']],
        'foreigns' => [['skill_id', 'skills', 'id', 'CASCADE']],
    ],

    'services' => [
        'columns' => [
            'id' => 'id',
            'icon' => 'string:60?',
            'accent' => 'string:20?',
            'is_featured' => 'bool:default=0',
            'is_active' => 'bool:default=1',
            'sort_order' => 'int:default=0',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'indexes' => [['is_active', 'sort_order']],
    ],

    'service_translations' => [
        'columns' => [
            'id' => 'id',
            'service_id' => 'bigint',
            'lang' => 'string:8',
            'title' => 'string:160',
            'slug' => 'string:180',
            'summary' => 'string:400?',
            'description' => 'mediumtext?',
            'features_json' => 'json?',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'unique' => [['service_id', 'lang'], ['lang', 'slug']],
        'foreigns' => [['service_id', 'services', 'id', 'CASCADE']],
    ],

    /* --------------------------------------------------------------------- */
    /* Portfolio                                                             */
    /* --------------------------------------------------------------------- */
    'project_categories' => [
        'columns' => [
            'id' => 'id',
            'icon' => 'string:60?',
            'color' => 'string:20?',
            'is_active' => 'bool:default=1',
            'sort_order' => 'int:default=0',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'indexes' => [['is_active', 'sort_order']],
    ],

    'project_category_translations' => [
        'columns' => [
            'id' => 'id',
            'category_id' => 'bigint',
            'lang' => 'string:8',
            'name' => 'string:120',
            'slug' => 'string:140',
            'description' => 'string:300?',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'unique' => [['category_id', 'lang'], ['lang', 'slug']],
        'foreigns' => [['category_id', 'project_categories', 'id', 'CASCADE']],
    ],

    'projects' => [
        'columns' => [
            'id' => 'id',
            'category_id' => 'bigint?',
            'client' => 'string:160?',
            'project_url' => 'string:255?',
            'github_url' => 'string:255?',
            'cover_media_id' => 'bigint?',
            'tech_stack' => 'string:400?',
            'started_at' => 'date?',
            'completed_at' => 'date?',
            'is_featured' => 'bool:default=0',
            'is_active' => 'bool:default=1',
            'sort_order' => 'int:default=0',
            'views' => 'int:default=0',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'indexes' => [['category_id'], ['is_active', 'is_featured', 'sort_order'], ['completed_at']],
        'foreigns' => [
            ['category_id', 'project_categories', 'id', 'SET NULL'],
            ['cover_media_id', 'media', 'id', 'SET NULL'],
        ],
    ],

    'project_translations' => [
        'columns' => [
            'id' => 'id',
            'project_id' => 'bigint',
            'lang' => 'string:8',
            'title' => 'string:200',
            'slug' => 'string:200',
            'summary' => 'string:500?',
            'description' => 'mediumtext?',
            'challenge' => 'mediumtext?',
            'solution' => 'mediumtext?',
            'results' => 'mediumtext?',
            'seo_title' => 'string:200?',
            'seo_description' => 'string:320?',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'unique' => [['project_id', 'lang'], ['lang', 'slug']],
        'foreigns' => [['project_id', 'projects', 'id', 'CASCADE']],
    ],

    'project_images' => [
        'columns' => [
            'id' => 'id',
            'project_id' => 'bigint',
            'media_id' => 'bigint',
            'caption' => 'string:200?',
            'sort_order' => 'int:default=0',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'indexes' => [['project_id', 'sort_order']],
        'foreigns' => [
            ['project_id', 'projects', 'id', 'CASCADE'],
            ['media_id', 'media', 'id', 'CASCADE'],
        ],
    ],

    'project_technologies' => [
        'columns' => [
            'id' => 'id',
            'project_id' => 'bigint',
            'skill_id' => 'bigint',
            'sort_order' => 'int:default=0',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'unique' => [['project_id', 'skill_id']],
        'foreigns' => [
            ['project_id', 'projects', 'id', 'CASCADE'],
            ['skill_id', 'skills', 'id', 'CASCADE'],
        ],
    ],

    /* --------------------------------------------------------------------- */
    /* Timeline: experience, education, certifications                       */
    /* --------------------------------------------------------------------- */
    'experiences' => [
        'columns' => [
            'id' => 'id',
            'company' => 'string:160',
            'company_url' => 'string:255?',
            'company_logo_media_id' => 'bigint?',
            'location' => 'string:120?',
            'employment_type' => 'enum:full_time,part_time,contract,freelance,internship:default=full_time',
            'start_date' => 'date',
            'end_date' => 'date?',
            'is_current' => 'bool:default=0',
            'is_active' => 'bool:default=1',
            'sort_order' => 'int:default=0',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'indexes' => [['is_active', 'sort_order'], ['start_date']],
        'foreigns' => [['company_logo_media_id', 'media', 'id', 'SET NULL']],
    ],

    'experience_translations' => [
        'columns' => [
            'id' => 'id',
            'experience_id' => 'bigint',
            'lang' => 'string:8',
            'position' => 'string:160',
            'description' => 'mediumtext?',
            'responsibilities_json' => 'json?',
            'achievements_json' => 'json?',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'unique' => [['experience_id', 'lang']],
        'foreigns' => [['experience_id', 'experiences', 'id', 'CASCADE']],
    ],

    'education' => [
        'columns' => [
            'id' => 'id',
            'institution' => 'string:180',
            'institution_url' => 'string:255?',
            'logo_media_id' => 'bigint?',
            'location' => 'string:120?',
            'start_date' => 'date',
            'end_date' => 'date?',
            'is_current' => 'bool:default=0',
            'gpa' => 'string:20?',
            'is_active' => 'bool:default=1',
            'sort_order' => 'int:default=0',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'indexes' => [['is_active', 'sort_order']],
        'foreigns' => [['logo_media_id', 'media', 'id', 'SET NULL']],
    ],

    'education_translations' => [
        'columns' => [
            'id' => 'id',
            'education_id' => 'bigint',
            'lang' => 'string:8',
            'degree' => 'string:160',
            'field' => 'string:160?',
            'description' => 'mediumtext?',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'unique' => [['education_id', 'lang']],
        'foreigns' => [['education_id', 'education', 'id', 'CASCADE']],
    ],

    'certifications' => [
        'columns' => [
            'id' => 'id',
            'issuer' => 'string:180',
            'issuer_url' => 'string:255?',
            'credential_id' => 'string:120?',
            'credential_url' => 'string:255?',
            'image_media_id' => 'bigint?',
            'issue_date' => 'date?',
            'expiry_date' => 'date?',
            'is_active' => 'bool:default=1',
            'sort_order' => 'int:default=0',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'indexes' => [['is_active', 'sort_order']],
        'foreigns' => [['image_media_id', 'media', 'id', 'SET NULL']],
    ],

    'certification_translations' => [
        'columns' => [
            'id' => 'id',
            'certification_id' => 'bigint',
            'lang' => 'string:8',
            'title' => 'string:200',
            'description' => 'mediumtext?',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'unique' => [['certification_id', 'lang']],
        'foreigns' => [['certification_id', 'certifications', 'id', 'CASCADE']],
    ],

    'testimonials' => [
        'columns' => [
            'id' => 'id',
            'avatar_media_id' => 'bigint?',
            'rating' => 'int:default=5',
            'lang' => 'string:8:default=fa',
            'is_featured' => 'bool:default=0',
            'is_active' => 'bool:default=1',
            'sort_order' => 'int:default=0',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'indexes' => [['lang'], ['is_active', 'sort_order']],
        'foreigns' => [['avatar_media_id', 'media', 'id', 'SET NULL']],
    ],

    'testimonial_translations' => [
        'columns' => [
            'id' => 'id',
            'testimonial_id' => 'bigint',
            'lang' => 'string:8',
            'client_name' => 'string:160',
            'client_position' => 'string:160?',
            'company' => 'string:160?',
            'quote' => 'mediumtext',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'unique' => [['testimonial_id', 'lang']],
        'foreigns' => [['testimonial_id', 'testimonials', 'id', 'CASCADE']],
    ],

    /* --------------------------------------------------------------------- */
    /* Editorial                                                             */
    /* --------------------------------------------------------------------- */
    'blog_categories' => [
        'columns' => [
            'id' => 'id',
            'icon' => 'string:60?',
            'color' => 'string:20?',
            'is_active' => 'bool:default=1',
            'sort_order' => 'int:default=0',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'indexes' => [['is_active', 'sort_order']],
    ],

    'blog_category_translations' => [
        'columns' => [
            'id' => 'id',
            'category_id' => 'bigint',
            'lang' => 'string:8',
            'name' => 'string:120',
            'slug' => 'string:140',
            'description' => 'string:300?',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'unique' => [['category_id', 'lang'], ['lang', 'slug']],
        'foreigns' => [['category_id', 'blog_categories', 'id', 'CASCADE']],
    ],

    'blog_tags' => [
        'columns' => [
            'id' => 'id',
            'is_active' => 'bool:default=1',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'indexes' => [['is_active']],
    ],

    'blog_tag_translations' => [
        'columns' => [
            'id' => 'id',
            'tag_id' => 'bigint',
            'lang' => 'string:8',
            'name' => 'string:80',
            'slug' => 'string:100',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'unique' => [['tag_id', 'lang'], ['lang', 'slug']],
        'foreigns' => [['tag_id', 'blog_tags', 'id', 'CASCADE']],
    ],

    'blog_posts' => [
        'columns' => [
            'id' => 'id',
            'category_id' => 'bigint?',
            'author_id' => 'bigint?',
            'cover_media_id' => 'bigint?',
            'status' => 'enum:draft,published,scheduled:default=draft',
            'published_at' => 'datetime?',
            'is_featured' => 'bool:default=0',
            'allow_comments' => 'bool:default=0',
            'views' => 'int:default=0',
            'reading_time' => 'int:default=1',
            'canonical_url' => 'string:255?',
            'og_image_media_id' => 'bigint?',
            'sort_order' => 'int:default=0',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'indexes' => [['status', 'published_at'], ['category_id'], ['is_featured']],
        'foreigns' => [
            ['category_id', 'blog_categories', 'id', 'SET NULL'],
            ['author_id', 'admins', 'id', 'SET NULL'],
            ['cover_media_id', 'media', 'id', 'SET NULL'],
            ['og_image_media_id', 'media', 'id', 'SET NULL'],
        ],
    ],

    'blog_post_translations' => [
        'columns' => [
            'id' => 'id',
            'post_id' => 'bigint',
            'lang' => 'string:8',
            'title' => 'string:220',
            'slug' => 'string:220',
            'excerpt' => 'string:500?',
            'content' => 'mediumtext?',
            'seo_title' => 'string:200?',
            'seo_description' => 'string:320?',
            'keywords' => 'string:320?',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'unique' => [['post_id', 'lang'], ['lang', 'slug']],
        'foreigns' => [['post_id', 'blog_posts', 'id', 'CASCADE']],
    ],

    'blog_post_tags' => [
        'columns' => [
            'id' => 'id',
            'post_id' => 'bigint',
            'tag_id' => 'bigint',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'unique' => [['post_id', 'tag_id']],
        'foreigns' => [
            ['post_id', 'blog_posts', 'id', 'CASCADE'],
            ['tag_id', 'blog_tags', 'id', 'CASCADE'],
        ],
    ],

    /* --------------------------------------------------------------------- */
    /* Operations                                                            */
    /* --------------------------------------------------------------------- */
    'messages' => [
        'columns' => [
            'id' => 'id',
            'name' => 'string:160',
            'email' => 'string:190',
            'phone' => 'string:40?',
            'company' => 'string:160?',
            'subject' => 'string:200?',
            'message' => 'mediumtext',
            'budget' => 'string:60?',
            'service_interest' => 'string:120?',
            'ip' => 'string:45?',
            'user_agent' => 'string:255?',
            'locale' => 'string:8:default=fa',
            'is_read' => 'bool:default=0',
            'is_starred' => 'bool:default=0',
            'is_archived' => 'bool:default=0',
            'replied_at' => 'datetime?',
            'notes' => 'mediumtext?',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'indexes' => [['is_read', 'created_at'], ['email'], ['is_archived']],
    ],

    'resumes' => [
        'columns' => [
            'id' => 'id',
            'lang' => 'string:8',
            'title' => 'string:200',
            'file_media_id' => 'bigint?',
            'file_path' => 'string:255?',
            'version' => 'string:40?',
            'is_primary' => 'bool:default=1',
            'is_active' => 'bool:default=1',
            'downloads' => 'int:default=0',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'indexes' => [['lang', 'is_active']],
        'foreigns' => [['file_media_id', 'media', 'id', 'SET NULL']],
    ],

    'seo_metadata' => [
        'columns' => [
            'id' => 'id',
            'entity_type' => 'enum:global,page,section,project,post,service,experience,home:default=page',
            'entity_id' => 'bigint?',
            'lang' => 'string:8',
            'seo_title' => 'string:200?',
            'meta_description' => 'string:320?',
            'keywords' => 'string:320?',
            'canonical_url' => 'string:255?',
            'robots' => 'string:60:default=index,follow',
            'og_title' => 'string:200?',
            'og_description' => 'string:320?',
            'og_image_media_id' => 'bigint?',
            'twitter_card' => 'string:40:default=summary_large_image',
            'schema_json' => 'json?',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'unique' => [['entity_type', 'entity_id', 'lang']],
        'indexes' => [['entity_type', 'lang']],
        'foreigns' => [['og_image_media_id', 'media', 'id', 'SET NULL']],
    ],

    'analytics_visits' => [
        'columns' => [
            'id' => 'id',
            'path' => 'string:255',
            'lang' => 'string:8?',
            'entity_type' => 'string:40?',
            'entity_id' => 'bigint?',
            'ip_hash' => 'string:64?',
            'user_agent' => 'string:255?',
            'referrer' => 'string:255?',
            'created_at' => 'datetime?',
            'updated_at' => 'datetime?',
        ],
        'indexes' => [['created_at'], ['path'], ['entity_type', 'entity_id']],
    ],
];
